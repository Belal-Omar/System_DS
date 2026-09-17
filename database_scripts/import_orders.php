<?php
session_start();
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_FILES['orderFile']) || $_FILES['orderFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed.']);
    exit;
}

$file = $_FILES['orderFile']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === FALSE) {
    echo json_encode(['success' => false, 'message' => 'Could not open file.']);
    exit;
}

$successCount = 0;
$errors = [];
$rowNumber = 0;



// Helper function to find product by name
function findProduct($conn, $productName) {
    $productName = trim($productName);
    
    // Exact match is safer to avoid confusion between similar names
    $stmt = $conn->prepare("SELECT * FROM products WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($product = $result->fetch_assoc()) {
        return $product;
    }
    
    return null;
}

// 1. رقم/تاريخ (Date/ID)
// 2. اسم العميل (Customer Name)
// 3. المنتجات (Products) - This contains ID, Name, Color/Size, Qty mixed
// 4. المنطقة (Region)
// 5. مدينة الشحن (Shipping City)
// 6. المجموع (Total)
// 7. الإجمالي (Grand Total)
// 8. الحالة (Status)
// 9. سبب الإلغاء/الإرجاع (Reason)
// 10. تعليقات الإدارة (Admin Comments)
// 11. إجراءات (Actions)

// Skip Header if exists (Assuming first row is header)
// We'll peek at the first row to see if it looks like a header
$firstRow = fgetcsv($handle, 1000, ",");
if ($firstRow && strpos($firstRow[0], 'رقم') === false && strpos($firstRow[0], '#') === false && strpos($firstRow[0], 'تاريخ') === false) {
    // Not a header, rewind
    rewind($handle);
}

// Default user ID for imported orders (current logged in user, or first admin/marketer)
$userId = $_SESSION['user_id'] ?? 1; // Fallback to ID 1

while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
    $rowNumber++;
    
    // Check if empty row
    if (empty(implode('', $data))) continue;

    try {
        // New Mapping based on Latest Image (Step 269):
        // A: ID
        // B: Customer Name
        // C: Phone
        // D: Product Name
        // E: Color
        // F: Size
        // G: Qty
        // H: Region
        // I: Shipping City
        // J: Full Address (العنوان بالتفصيل)
        // K: Commission (العمولة)
        // L: Status
        
        $customerName     = clean_input($data[1] ?? 'Unknown');
        $customerPhone    = clean_input($data[2] ?? '');
        $productNameRaw   = clean_input($data[3] ?? '');
        $color            = clean_input($data[4] ?? '');
        $size             = clean_input($data[5] ?? '');
        $qtyInput         = $data[6] ?? 1;
        $region           = clean_input($data[7] ?? '');
        $shippingCityName = clean_input($data[8] ?? '');
        $fullAddress      = clean_input($data[9] ?? '');
        $excelCommission  = floatval(preg_replace('/[^\d.]/', '', $data[10] ?? 0));
        $status           = clean_input($data[11] ?? 'قيد الانتظار');
        
        // Use region if full address is empty
        if (empty($fullAddress)) $fullAddress = $region;

        // Ensure phone number format
        $customerPhone = preg_replace('/[^\d+]/', '', $customerPhone);
        
        // Quantity
        $qty = intval($qtyInput);
        if ($qty <= 0) $qty = 1;
        
        // Product Name clean up
        $productName = trim($productNameRaw);
        
        if (empty($productName)) {
             $errors[] = "Row $rowNumber: Product name empty.";
             continue; 
        }

        // Find Product in DB
        $dbProduct = findProduct($conn, $productName);
        if (!$dbProduct) {
             $errors[] = "Row $rowNumber: Product '$productName' not found.";
             continue;
        }
        
        // Check Stock (Main and Variant)
        if ($dbProduct['stock'] < $qty) {
            $errors[] = "Row $rowNumber: Insufficient total stock for '{$dbProduct['name']}'. Needed: $qty, Available: {$dbProduct['stock']}.";
            continue; 
        }

        $hasVariant = (!empty($color) || !empty($size));
        if ($hasVariant) {
            $invStmt = $conn->prepare("SELECT quantity FROM product_inventory WHERE product_id = ? AND color = ? AND size = ?");
            $invStmt->bind_param("iss", $dbProduct['id'], $color, $size);
            $invStmt->execute();
            $invRes = $invStmt->get_result();
            if ($invItem = $invRes->fetch_assoc()) {
                if ($invItem['quantity'] < $qty) {
                    $errors[] = "Row $rowNumber: Insufficient variant stock for '{$dbProduct['name']}' ($color/$size). Needed: $qty, Available: {$invItem['quantity']}.";
                    continue;
                }
            } else {
                // If variant not found but product has inventory records, it's an error
                $hasInvCheck = $conn->query("SELECT id FROM product_inventory WHERE product_id = {$dbProduct['id']} LIMIT 1");
                if ($hasInvCheck && $hasInvCheck->num_rows > 0) {
                    $errors[] = "Row $rowNumber: Variant ($color/$size) not found in inventory for '{$dbProduct['name']}'.";
                    continue;
                }
            }
        }

        // Fetch Shipping Cost and City ID
        $shippingCityId = null;
        $shippingCost = 0;
        if (!empty($shippingCityName)) {
            $cityStmt = $conn->prepare("SELECT id, shipping_cost FROM shipping_cities WHERE city_name LIKE ? LIMIT 1");
            $cityParam = "%" . $shippingCityName . "%";
            $cityStmt->bind_param("s", $cityParam);
            $cityStmt->execute();
            $cityRes = $cityStmt->get_result();
            if ($cityRow = $cityRes->fetch_assoc()) {
                $shippingCityId = $cityRow['id'];
                $shippingCost = floatval($cityRow['shipping_cost']);
            }
        }
        
        // Calculation Logic:
        // User Request: Commission in Excel is PER UNIT.
        // Special Commission: Deducted from the Excel Commission.
        
        $productPrice = floatval($dbProduct['price']);
        $specialCommissionDeduction = floatval($dbProduct['special_commission'] ?? 0);
        
        // excelCommission is the Gross Unit Commission
        // netUnitCommission is what the marketer actually gets
        $grossUnitCommission = $excelCommission;
        $netUnitCommission = $grossUnitCommission - $specialCommissionDeduction;

        $itemBaseTotal = $productPrice * $qty;
        
        // Totals for Order Record
        $totalCommission = $netUnitCommission * $qty; // This is the NET commission total for balance
        $totalSpecialCommission = $specialCommissionDeduction * $qty; // Total deduction
        
        $orderTotal = $itemBaseTotal + $totalCommission + $shippingCost; // Note: Does order total include the deduction? Usually Order Total = Price + Shipping. Commission is internal. 
        // Wait, current formula: $orderTotal = $itemBaseTotal + $totalCommission + $shippingCost;
        // If I reduce $totalCommission, $orderTotal might decrease. 
        // BUT, usually "Total" is what the customer pays. 
        // If customer pays 100 (Price) + 10 (Shipping) = 110. Commission is usually inside Price or added if it's a markup.
        // User said: "((Price + Unit Commission) * Qty) + Shipping". So Commission IS added to price for customer.
        // So if we deduct from marketer, does the customer still pay the gross commission?
        // "this commission belongs to the marketer and is deducted from him".
        // This likely means the Customer Pays Gross. Marketer Gets Net.
        // So Order Total should probably use Gross Commission.
        
        $totalGrossCommission = $grossUnitCommission * $qty;
        $orderTotal = $itemBaseTotal + $totalGrossCommission + $shippingCost;

        // Insert Order
        // Added total_special_commission to schema
        $stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, region, address, shipping_city_id, total, commission_total, total_special_commission, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issssiddds", $userId, $customerName, $customerPhone, $region, $fullAddress, $shippingCityId, $orderTotal, $totalCommission, $totalSpecialCommission, $status);
        
        if ($stmt->execute()) {
            $orderId = $conn->insert_id;
            
            // Insert Order Item with Color and Size
            // Store Net Unit Commission and Special Commission
            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, commission, special_commission, color, size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $itemStmt->bind_param("iiidddss", $orderId, $dbProduct['id'], $qty, $productPrice, $grossUnitCommission, $specialCommissionDeduction, $color, $size);
            $itemStmt->execute();
            
            // Deduct Stock
            $conn->query("UPDATE products SET stock = stock - $qty WHERE id = {$dbProduct['id']}");
            if ($hasVariant) {
                $updateInv = $conn->prepare("UPDATE product_inventory SET quantity = quantity - ? WHERE product_id = ? AND color = ? AND size = ?");
                $updateInv->bind_param("iiss", $qty, $dbProduct['id'], $color, $size);
                $updateInv->execute();
            }
            
            $successCount++;
        } else {
            $errors[] = "Row $rowNumber: DB error inserting order: " . $stmt->error;
        }

    } catch (Exception $e) {
        $errors[] = "Row $rowNumber: Error processing: " . $e->getMessage();
    }
}

fclose($handle);

echo json_encode([
    'success' => true,
    'message' => "Imported $successCount orders successfully.",
    'errors' => $errors
]);
?>
