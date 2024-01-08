<?php

session_start();

include("database_connection.php");
include("user_processing.php");
include("bot_responses.php");

/*
// Database connection
function connectToDatabase() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "chatbot";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        return null;
    }
}
*/


/*
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the user's message from the POST data
    $userMessage = $_POST['message'];

    // Increment the counter if less than 10 minutes have passed since the last message
    if (isset($_SESSION['last_message_time']) && (time() - $_SESSION['last_message_time'] < 600)) {
        $_SESSION['message_count'] += 1;
    } else {
        // Reset the counter if 10 or more minutes have passed
        $_SESSION['message_count'] = 1;
    }

    // Update the timestamp of the last message
    $_SESSION['last_message_time'] = time();

   // Store the user's message in the conversation history
   $_SESSION['conversation'][] = ['user' => $userMessage];

   // Process the user's message
   $botResponse = processMessage($userMessage, $_SESSION['message_count']);

   // Store the bot's response in the conversation history
   $_SESSION['conversation'][] = ['bot' => $botResponse];

    // Send back the bot's response
    echo $botResponse;
}
*/

/*
// Function to process user message
function processMessage($message, $messageCount) {

    // Normalize the message to handle contractions
    $message = normalizeMessage($message);

    // Check for specific sequences in the conversation history
    $response = checkForPatterns($_SESSION['conversation']);
    if ($response) return $response;

    // Connect to the database
    $conn = connectToDatabase();
    if (!$conn) return "I'm sorry, something went wrong. Please try again later.";

    // Split the user's message into sentences (parts)
    $sentences = explode('.', $message);

    // Iterate through the sentences and search for responses
    foreach ($sentences as $sentence) {
        // Split the sentence into words and take the first word
        $words = explode(' ', trim($sentence));
        $firstWord = $words[0];

        if (stripos($message, 'command:LetMeTeachYou') !== false) {
            $_SESSION['teaching_mode'] = true;
            $_SESSION['teaching_stage'] = 'question'; // Initialize teaching stage
            return "Teach me!";
        }
         
        if (stripos($message, 'command:stop') !== false && isset($_SESSION['teaching_mode'])) {
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

        if (stripos($message, 'What is the time?') !== false) {
            return getCurrentTime();
        }
        
        // Check for a command in the message
        if (stripos($message, 'command:') !== false) {
            $commandParts = explode('command:', $message);
            $commandName = trim(explode(' ', $commandParts[1])[0]); // Get the word after "command:"
            $response = executeCommand($commandName);
            if ($response) return $response;
        }

        // Check if the sentence contains a basic math expression
        if (preg_match('/\d+\s*[+\-\/*]\s*\d+/', $sentence)) {
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
*/
// Function to search for a response in a specific table
/*
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

function checkForPatterns($conversation) {
    $conn = connectToDatabase();
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
*/
/*
// --------------------- LEARN MODE --------------------------------------------

function addPatternToDatabase($question, $response, $keyword) {
    $conn = connectToDatabase();
    if (!$conn) return false;

    // Fetch the table name for the given keyword
    $stmt = $conn->prepare("SELECT table_name FROM keywords WHERE keyword = :keyword");
    $stmt->bindParam(':keyword', $keyword, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() == 0) return false; // Keyword does not exist

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tableName = $row['table_name'];

    // Insert the pattern and response into the relevant table
    $sql = "INSERT INTO $tableName (phrase, response) VALUES (:question, :response)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':question', $question, PDO::PARAM_STR);
    $stmt->bindParam(':response', $response, PDO::PARAM_STR);
    return $stmt->execute();
}

function createKeywordTable($keyword) {
    $conn = connectToDatabase();
    if (!$conn) return false;

    // Determine the table name (customize this part according to your naming convention)
    $tableName = 'table_' . $keyword;

    // Create the table
    $sql = "CREATE TABLE $tableName (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phrase VARCHAR(255) NOT NULL,
        response VARCHAR(255) NOT NULL
    )";
    if ($conn->exec($sql) === false) return false;

    // Insert the keyword into the keywords table
    $sql = "INSERT INTO keywords (keyword, table_name) VALUES (:keyword, :table_name)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':keyword', $keyword, PDO::PARAM_STR);
    $stmt->bindParam(':table_name', $tableName, PDO::PARAM_STR);
    return $stmt->execute();
}
*/
/*
// ---------------------- MATH RELATED ----------------------------------------

function calculateMathExpression($expression) {
    // Using preg_match to capture the operands and the operator
    if (preg_match('/(\d+)\s*([+\-\/*])\s*(\d+)/', $expression, $matches)) {
        $operand1 = $matches[1];
        $operator = $matches[2];
        $operand2 = $matches[3];

        switch ($operator) {
            case '+':
                return $operand1 + $operand2;
            case '-':
                return $operand1 - $operand2;
            case '*':
                return $operand1 * $operand2;
            case '/':
                if ($operand2 != 0) {
                    return $operand1 / $operand2;
                } else {
                    return "Division by zero is not allowed.";
                }
            default:
                return "Operator not supported.";
        }
    } else {
        return "Invalid math expression.";
    }
}
*/

/*
// ---------------------- USER CALLED FUNCTIONS ---------------------------------

// Function to execute a command
function executeCommand($commandName) {
    // Connect to the database
    $conn = connectToDatabase();
    if (!$conn) return "I'm sorry, something went wrong. Please try again later.";

    // Prepare a query to search for the command
    $sql = "SELECT command_name FROM commands WHERE command_name = :command";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':command', $commandName, PDO::PARAM_STR);
    $stmt->execute();

    if ($commandName === 'time' || $commandName === 'getTime') {
        return getCurrentTime();
    }

    // If the command is found, execute the corresponding function
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $functionName = $row['command_name'];
        
        if (function_exists($functionName)) {
            return $functionName(); // Call the function by its name
        } else {
            return "I'm sorry, the command exists but the function does not.";
        }
    }

    return "I'm sorry, I don't recognize that command.";
}

// Example function for the "resetCounter" command
function resetCounter() {
    $_SESSION['message_count'] = 0;
    return "Counter has been reset.";
}

// Function to show the current value of the message counter
function showCounter() {
    if (isset($_SESSION['message_count'])) {
        return "Current message count: " . $_SESSION['message_count'];
    } else {
        return "Message counter is not set.";
    }
}

function getCurrentTime() {
    // Get the current time in a specific format
    $currentTime = date("h:i:sa"); // Example output: 03:34:05pm
    return "The current time is " . $currentTime;
}
*/


?>

