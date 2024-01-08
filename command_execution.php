<?php

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


?>