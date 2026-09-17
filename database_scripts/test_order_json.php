<?php
// Test script to verify JSON response works
ob_start();

header("Content-Type: application/json; charset=UTF-8");

// Test response
$response = [
    "success" => true,
    "message" => "Test successful",
    "data" => [
        "test" => "JSON response is working"
    ]
];

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
