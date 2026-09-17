<?php
// اختبار بسيط جداً بدون أي includes
header("Content-Type: application/json; charset=UTF-8");
echo json_encode(["success" => true, "message" => "Test working"], JSON_UNESCAPED_UNICODE);
?>
