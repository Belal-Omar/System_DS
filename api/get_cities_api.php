<?php
// Simple API to get cities - No BOM, No encoding issues
header('Content-Type: application/json; charset=utf-8');

try {
    $conn = new mysqli("127.0.0.1", "u497700233_medhatomar5555", "BelalOmar49988155$", "u497700233_System", 3306);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    $result = $conn->query("SELECT id, city_name, shipping_cost FROM shipping_cities ORDER BY city_name ASC");
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $cities = [];
    while ($row = $result->fetch_assoc()) {
        $cities[] = [
            'id' => (int)$row['id'],
            'city_name' => $row['city_name'],
            'shipping_cost' => (float)$row['shipping_cost']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cities' => $cities,
        'count' => count($cities)
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
