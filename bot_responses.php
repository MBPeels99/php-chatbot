<?php

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

?>