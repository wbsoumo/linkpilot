<?php
// backend/api/auth/get_captcha.php

require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse('error', 'Method not allowed', [], 405);
}

try {
    // Generate random math question
    $num1 = random_int(1, 15);
    $num2 = random_int(1, 10);
    $operators = ['+', '-', '*'];
    $op = $operators[array_rand($operators)];
    $question = "$num1 $op $num2";
    
    // Calculate answer
    $answer = 0;
    switch ($op) {
        case '+':
            $answer = $num1 + $num2;
            break;
        case '-':
            $answer = $num1 - $num2;
            break;
        case '*':
            $answer = $num1 * $num2;
            break;
    }
    
    // Set expiration to 5 minutes from now
    $expiry = time() + 300;
    
    // Encrypt answer and expiry into token
    $payload = json_encode([
        'answer' => $answer,
        'expiry' => $expiry
    ]);
    
    $captchaToken = encryptData($payload);
    
    sendJsonResponse('success', 'Captcha generated successfully', [
        'question' => "What is $num1 $op $num2?",
        'captcha_token' => $captchaToken
    ]);

} catch (Exception $e) {
    sendJsonResponse('error', 'Failed to generate captcha: ' . $e->getMessage(), [], 500);
}
