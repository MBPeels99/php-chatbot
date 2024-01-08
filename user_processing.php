<?php

// Function to process user message
function processMessage($message, $messageCount) {

   // Connect to the database
   $conn = connectToDatabase();
   if (!$conn) return "I'm sorry, something went wrong. Please try again later.";

    // Normalize the message to handle contractions
    $message = normalizeMessage($message);

    // Check for specific sequences in the conversation history
    $response = checkForPatterns($_SESSION['conversation'],$conn);
    if ($response) return $response;

    // Split the user's message into sentences (parts)
    $sentences = explode('.', $message);

    // Iterate through the sentences and search for responses
    foreach ($sentences as $sentence) {
        $entities = extractSubjectObject($sentence,$conn);
        $subject = $entities['subject'];
        $object = $entities['object'];

        // Split the sentence into words and take the first word
        $words = explode(' ', trim($sentence));
        $firstWord = $words[0];

        if (stripos($sentence, 'command:LetMeTeachYou') !== false) {
            $_SESSION['teaching_mode'] = true;
            $_SESSION['teaching_stage'] = 'question';
            return "Teach me!";
        }
         
        if (stripos($sentence, 'command:stop') !== false && isset($_SESSION['teaching_mode'])) {
            $_SESSION['teaching_mode'] = false;
            $_SESSION['teaching_stage'] = null;
            return "Teaching mode has been stopped.";
        }
        
        if (isset($_SESSION['teaching_mode']) && $_SESSION['teaching_mode']) {
            switch ($_SESSION['teaching_stage']) {
                case 'question':
                    $_SESSION['user_question'] = $message;
                    $_SESSION['teaching_stage'] = 'response';
                    return "What would my response be?";
                case 'response':
                    $_SESSION['user_response'] = $message;
                    $_SESSION['teaching_stage'] = 'keyword';
                    return "Is this under certain keywords?";
                    case 'keyword':
                        $keyword = trim($message);
                        $stmt = $conn->prepare("SELECT table_name FROM keywords WHERE keyword = :keyword");
                        $stmt->bindParam(':keyword', $keyword, PDO::PARAM_STR);
                        $stmt->execute();
                        
                        if ($stmt->rowCount() == 0) {
                            $_SESSION['teaching_stage'] = 'add_keyword';
                            $_SESSION['potential_keyword'] = $keyword;
                            return "The keyword '$keyword' does not exist. Would you like to add this keyword? (yes/no)";
                        } else {
                            // Continue with the existing logic if the keyword exists
                            if (addPatternToDatabase($_SESSION['user_question'], $_SESSION['user_response'], $keyword)) {
                                $_SESSION['teaching_stage'] = 'continue';
                                return "Successfully added! Would you like to teach more? (yes/no)";
                            } else {
                                $_SESSION['teaching_stage'] = null;
                                $_SESSION['teaching_mode'] = false;
                                return "Sorry, something went wrong. Teaching mode has been disabled.";
                            }
                        }
                    case 'add_keyword':
                        if (strtolower($message) == 'yes') {
                            $keyword = $_SESSION['potential_keyword'];
                            if (createKeywordTable($keyword)) {
                                return "Keyword added successfully! Now, let's continue teaching.";
                            } else {
                                $_SESSION['teaching_stage'] = null;
                                $_SESSION['teaching_mode'] = false;
                                return "Failed to add keyword. Teaching mode has been disabled.";
                            }
                        } else {
                            $_SESSION['teaching_stage'] = 'continue';
                            return "Would you like to teach more? (yes/no)";
                        }
                case 'continue':
                    if (strtolower($message) == 'yes') {
                        $_SESSION['teaching_stage'] = 'question';
                        return "Teach me!";
                    } else {
                        $_SESSION['teaching_stage'] = null;
                        $_SESSION['teaching_mode'] = false;
                        return "Thank you for teaching me!";
                    }
                default:
                    $_SESSION['teaching_stage'] = 'question';
                    return "Teach me!";
            }
        }

        if (stripos($sentence, 'What is the time?') !== false) {
            return getCurrentTime();
        }
        
        // Check for a command in the message
        if (stripos($sentence, 'command:') !== false) {
            include("command_execution.php");
            $commandParts = explode('command:', $message);
            $commandName = trim(explode(' ', $commandParts[1])[0]); // Get the word after "command:"
            $response = executeCommand($commandName);
            if ($response) return $response;
        }

        // Check if the sentence contains a basic math expression
        if (preg_match('/\d+\s*[+\-\/*]\s*\d+/', $sentence)) {
            include("math_functions.php");
            $result = calculateMathExpression($sentence);
            return "The result of the expression is: " . $result;
        }

        // If the message count is less than 10, check for greetings
        if ($messageCount < 10) {
            // Split the user's message into sentences (parts)
            $sentences = explode('.', $message);

            // Iterate through the sentences
            foreach ($sentences as $sentence) {
                // Trim the sentence to remove leading/trailing whitespace
                $sentence = trim($sentence);

                // Prepare a query to search for a response in the Greetings table
                $sql = "SELECT response FROM Greetings WHERE phrase = :phrase";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':phrase', $sentence, PDO::PARAM_STR);
                $stmt->execute();

                // If a response is found, return it
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $row['response'];
                }
            }
        }

        // Handle other keywords
        $sql = "SELECT table_name FROM keywords WHERE keyword = :keyword";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':keyword', $firstWord, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $tableName = $row['table_name'];
            $response = searchInTable($conn, $tableName, $sentence);
            if ($response) return $response;
        }
    }

    // If no response was found, return a default response
    return "I'm sorry, I don't understand that message.";
}

function searchInTable($conn, $tableName, $sentence) {
    $sql = "SELECT response FROM " . $tableName . " WHERE phrase LIKE :phrase";
    $stmt = $conn->prepare($sql);

    // Add wildcards to search for the sentence within the phrase
    $searchTerm = '%' . $sentence . '%';
    $stmt->bindParam(':phrase', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Fetch all matching responses
        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Choose a random response
        $randomKey = array_rand($responses);
        return $responses[$randomKey]['response'];
    }

    return null;
}

function checkForPatterns($conversation,$conn) {
    $sql = "SELECT pattern, response FROM ConversationPatterns";
    $stmt = $conn->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pattern = $row['pattern'];
        $response = $row['response'];

        // Compare pattern against the conversation history
        if (matchPattern($pattern, $conversation)) {
            return $response;
        }
    }

    return null;
}


// Will normalize some words for better usage
function normalizeMessage($message) {
    $contractions = array(
        "What's" => "What is",
        "It's" => "It is",
        // Add other contractions here
    );

    return str_replace(array_keys($contractions), $contractions, $message);
}

function extractSubjectObject($sentence, $conn) {
    // Query for verbs
    $sql = "SELECT base_form, past_simple, past_participle, present_participle, third_person_singular FROM verbs";
    $stmt = $conn->query($sql);
    $verbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Flatten the verb forms
    $all_verbs = array();
    foreach ($verbs as $verb) {
        $all_verbs = array_merge($all_verbs, array_values($verb));
    }

    // Split sentence
    $words = explode(' ', trim($sentence));
    $subject = null;
    $object = null;

    // Search for subject and object
    foreach ($words as $index => $word) {
        if (in_array($word, $all_verbs)) {
            $subject = $words[$index - 1];
            $object = isset($words[$index + 1]) ? $words[$index + 1] : null;
            
            // Add or update the relationship to the database
            $subjectId = getOrAddSubject($subject, $conn);
            $objectId = getOrAddObject($object, $conn);
            updateSubjectObjectRelationship($subjectId, $objectId, $conn);

            break;
        }
    }

    return array('subject' => $subject, 'object' => $object);
}




function matchPattern($pattern, $conversation) {
    // Escape special characters first
    $pattern = preg_quote($pattern, '/');

    // Replace the percent symbol with a regular expression pattern to match any sequence of characters
    $pattern = str_replace('%', '.*?', $pattern);

    // Concatenate the conversation into a single string
    $conversationString = '';
    foreach ($conversation as $message) {
        if (isset($message['user'])) {
            $conversationString .= ' ' . $message['user'];
        }
        if (isset($message['bot'])) {
            $conversationString .= ' ' . $message['bot'];
        }
    }
    $conversationString = trim($conversationString); // Remove leading/trailing spaces

    // Check if the pattern matches the conversation
    return preg_match('/' . preg_quote($pattern, '/') . '/', $conversationString) === 1;
}

function getOrAddSubject($subject, $conn) {
    // Check if subject exists
    $sql = "SELECT id FROM subjects WHERE name = :subject";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $stmt->execute();
    $subjectId = $stmt->fetchColumn();

    if (!$subjectId) {
        // Insert new subject
        $sql = "INSERT INTO subjects (name) VALUES (:subject)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->execute();
        $subjectId = $conn->lastInsertId();
    }

    return $subjectId;
}

function getOrAddObject($object, $conn) {
    // Check if object exists
    $sql = "SELECT id FROM objects WHERE name = :object";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':object', $object, PDO::PARAM_STR);
    $stmt->execute();
    $objectId = $stmt->fetchColumn();

    if (!$objectId) {
        // Insert new object
        $sql = "INSERT INTO objects (name) VALUES (:object)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':object', $object, PDO::PARAM_STR);
        $stmt->execute();
        $objectId = $conn->lastInsertId();
    }

    return $objectId;
}

function updateSubjectObjectRelationship($subjectId, $objectId, $conn) {
    // Check if relationship exists
    $sql = "SELECT frequency FROM subjectobjectrelationships WHERE subject_id = :subjectId AND object_id = :objectId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':subjectId', $subjectId, PDO::PARAM_INT);
    $stmt->bindParam(':objectId', $objectId, PDO::PARAM_INT);
    $stmt->execute();
    $frequency = $stmt->fetchColumn();

    if ($frequency !== false) {
        // Update frequency
        $frequency++;
        $sql = "UPDATE subjectobjectrelationships SET frequency = :frequency WHERE subject_id = :subjectId AND object_id = :objectId";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':frequency', $frequency, PDO::PARAM_INT);
        $stmt->bindParam(':subjectId', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':objectId', $objectId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Insert new relationship
        $sql = "INSERT INTO subjectobjectrelationships (subject_id, object_id, frequency) VALUES (:subjectId, :objectId, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':subjectId', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':objectId', $objectId, PDO::PARAM_INT);
        $stmt->execute();
    }
}


?>