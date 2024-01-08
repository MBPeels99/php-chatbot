<?php

// ---------------------- MATH RELATED ----------------------------------------

function calculateMathExpression($expression) {
    // Validate the expression to contain only numbers, operators, and parentheses
    if (preg_match('/^[\d+\-\/*\s\(\).]+$/', $expression)) {
        // Replace division by zero with an error message
        $expression = preg_replace('/\/\s*0/', 'division_by_zero()', $expression);

        // Create a custom error handler for division by zero
        function division_by_zero() {
            return "Division by zero is not allowed.";
        }

        try {
            // Evaluate the expression
            $result = eval("return $expression;");
            return $result;
        } catch (ParseError $e) {
            return "An error occurred while evaluating the expression.";
        }
    } else {
        return "Invalid math expression.";
    }
}


?>