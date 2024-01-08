<?php

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

?>