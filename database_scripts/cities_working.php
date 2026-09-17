<?php
// نسخة عاملة من simple_get_cities.php
header('Content-Type: application/json; charset=utf-8');

try {
    $servername = "127.0.0.1";
    $username   = "u497700233_medhatomar5555";
    $password   = "BelalOmar49988155$";
    $dbname     = "u497700233_System";
    
    $conn = new mysqli($servername, $username, $password, $dbname, 3306);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    $sql = "SELECT id, city_name, shipping_cost FROM shipping_cities ORDER BY city_name ASC";
    $result = $conn->query($sql);
    
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
        'message' => 'Cities loaded successfully',
        'cities' => $cities,
        'count' => count($cities)
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
