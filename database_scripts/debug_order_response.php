<?php
// Debug script to check what's being outputted before JSON
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Capture any output before we start
ob_start();

// Test the JSON response
header("Content-Type: application/json; charset=UTF-8");

// Test simple JSON
echo json_encode([
    "success" => true,
    "message" => "Test response",
    "debug" => "Testing JSON output"
], JSON_UNESCAPED_UNICODE);

// Check if there was any output before
$output_before = ob_get_contents();
if (!empty($output_before)) {
    file_put_contents('debug_output.log', "Output before JSON: " . $output_before);
}

ob_end_flush();
?>
