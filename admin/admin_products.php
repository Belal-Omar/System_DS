<?php
// ملف: admin_products.php (مُحدّث بالصور المتعددة والوصف المنظم)
// ملاحظة: session_start() يتم استدعاؤه من admin_panel.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include(__DIR__ . '/core/config.php");
include(__DIR__ . '/core/helpers.php");

// AJAX handler for getting product data
if (isset($_GET['get_product_data'])) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    error_log("AJAX request received for product data: " . $_GET['get_product_data']);
    
    $product_id = intval($_GET['get_product_data']);
    
    // Create product_marketers table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS product_marketers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        marketer_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_product_marketer (product_id, marketer_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (marketer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $product_query = $conn->prepare("SELECT * FROM products WHERE id = ?");
    if (!$product_query) {
        error_log("Prepare failed: " . $conn->error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database prepare failed']);
        exit;
    }
    
    $product_query->bind_param("i", $product_id);
    if (!$product_query->execute()) {
        error_log("Execute failed: " . $product_query->error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database execute failed']);
        exit;
    }
    
    $product = $product_query->get_result()->fetch_assoc();
    
    if ($product) {
        error_log("Product found: " . $product['name']);
        
        // Get product marketers
        $marketers_query = $conn->prepare("SELECT marketer_id FROM product_marketers WHERE product_id = ?");
        $marketers_query->bind_param("i", $product_id);
        $marketers_query->execute();
        $marketers_result = $marketers_query->get_result();
        
        $marketers = [];
        while ($row = $marketers_result->fetch_assoc()) {
            $marketers[] = $row['marketer_id'];
        }
        
        error_log("Marketers found: " . count($marketers));

        // Get product images
        $images_query = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
        $images_query->bind_param("i", $product_id);
        $images_query->execute();
        $images_result = $images_query->get_result();
        
        $images = [];
        while ($row = $images_result->fetch_assoc()) {
            $images[] = $row;
        }
        
        // Get product shippers
        $shippers = [];
        $shippers_query = $conn->prepare("SELECT shipping_company_id, stock FROM product_shippers WHERE product_id = ?");
        if ($shippers_query) {
            $shippers_query->bind_param("i", $product_id);
            $shippers_query->execute();
            $shippers_result = $shippers_query->get_result();
            
            while ($row = $shippers_result->fetch_assoc()) {
                $shippers[] = $row;
            }
        }
        
        // Get product inventory (colors, sizes, quantities)
        $inventory = [];
        $inv_query = $conn->prepare("SELECT color, size, quantity FROM product_inventory WHERE product_id = ?");
        if ($inv_query) {
            $inv_query->bind_param("i", $product_id);
            $inv_query->execute();
            $inv_result = $inv_query->get_result();
            while ($row = $inv_result->fetch_assoc()) {
                $inventory[] = $row;
            }
        }
        
        error_log("Inventory items found: " . count($inventory));
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product' => $product,
            'marketers' => $marketers,
            'images' => $images,
            'shippers' => $shippers,
            'inventory' => $inventory,
            'merchant_commission' => $product['merchant_commission'] ?? 0
        ]);
    } else {
        error_log("Product not found: " . $product_id);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
    exit;
}

// الحصول على إحصائيات المنتجات
$product_counts = get_product_counts($conn);

// الحصول على شركات الشحن
$shipping_companies = get_shipping_companies($conn);

// التأكد من وجود عمود merchant_commission
$check_merchant_comm = $conn->query("SHOW COLUMNS FROM products LIKE 'merchant_commission'");
if ($check_merchant_comm && $check_merchant_comm->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN merchant_commission DECIMAL(10, 2) DEFAULT 0.00 AFTER commission");
}

// معالجة إضافة منتج جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = clean_input($_POST['name']);
    $description = clean_input($_POST['description']);
    $price = floatval($_POST['price']);
    $commission = floatval($_POST['commission']);
    $merchant_commission = floatval($_POST['merchant_commission'] ?? 0);
    $special_commission = floatval($_POST['special_commission'] ?? 0);
    $category = clean_input($_POST['category']);
    $stock = intval($_POST['stock']);
    $shipping_company = clean_input($_POST['shipping_company'] ?? '');
    
    // التحقق من وجود عمود status قبل استخدامه
    $check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status_column = ($check_status && $check_status->num_rows > 0);
    $status = $has_status_column ? clean_input($_POST['status'] ?? 'active') : 'active'; // حالة المنتج (مفعل/غير مفعل)
    
    // البحث عن التاجر (باستخدام merchant_id مباشرة)
    $merchant_id = null;
    if (!empty($_POST['merchant_id'])) {
        $merchant_id = intval($_POST['merchant_id']);
    }
    
    // إضافة المنتدلع user_id إذا تم العثور على تاجر
    try {
        if ($merchant_id && $has_status_column) {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, commission, merchant_commission, special_commission, category, stock, status, shipping_company, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (1): " . $conn->error);
            $stmt->bind_param("ssddddssisi", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $status, $shipping_company, $merchant_id);
        } elseif ($merchant_id && !$has_status_column) {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, commission, merchant_commission, special_commission, category, stock, shipping_company, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (2): " . $conn->error);
            $stmt->bind_param("ssddddssii", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $shipping_company, $merchant_id);
        } elseif (!$merchant_id && $has_status_column) {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, commission, merchant_commission, special_commission, category, stock, status, shipping_company) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (3): " . $conn->error);
            $stmt->bind_param("ssddddssis", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $status, $shipping_company);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, commission, merchant_commission, special_commission, category, stock, shipping_company) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (4): " . $conn->error);
            $stmt->bind_param("ssddddssi", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $shipping_company);
        }
        
        if ($stmt->execute()) {
        $product_id = $conn->insert_id;
        
        // رسالة نجاح مع معلومات التاجر
        if ($merchant_id) {
            $merchant_info = $conn->query("SELECT fullname, email FROM users WHERE id = $merchant_id")->fetch_assoc();
            $success = "تم إضافة المنتج بنجاح للتاجر: " . ($merchant_info['fullname'] ?? '') . " (" . ($merchant_info['email'] ?? '') . ")";
        } else {
            $success = "تم إضافة المنتج بنجاح!";
        }
        
        // Check if product was created and show image info
        $check_product = $conn->query("SELECT id, name, image FROM products WHERE id = $product_id");
        if ($check_product && $check_product->num_rows > 0) {
            $product_info = $check_product->fetch_assoc();
            $success .= " | المنتج: " . $product_info['name'] . " | الصورة: " . ($product_info['image'] ?? 'NULL');
        }
        
        // التعامل مع رفع الصور المتعددة
        $main_image_path = null;
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = 'imgs/';
            $is_first = true;
            
            // Debug: Show what we received
            error_log("Image upload attempt - Files: " . print_r($_FILES['images'], true));
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] == 0) {
                    $image_name = time() . '_' . $key . '_' . basename($_FILES['images']['name'][$key]);
                    $image_path = $upload_dir . $image_name;
                    
                    error_log("Processing image: $image_name -> $image_path");
                    
                    if (move_uploaded_file($tmp_name, $image_path)) {
                        error_log("Image uploaded successfully: $image_path");
                        
                        // Save first image as main image in products table
                        if ($is_first) {
                            $main_image_path = $image_name;
                            error_log("Setting main image: $image_name for product ID: $product_id");
                            // Update products table with main image
                            $update_stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $image_name, $product_id);
                            if ($update_stmt->execute()) {
                                error_log("Main image saved to database successfully");
                            } else {
                                error_log("Failed to save main image: " . $update_stmt->error);
                            }
                        }
                        
                        // Also save in product_images table
                        $is_main = $is_first ? 1 : 0;
                        $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                        $img_stmt->bind_param("isi", $product_id, $image_path, $is_main);
                        $img_stmt->execute();
                        $is_first = false;
                    } else {
                        error_log("Failed to upload image: $image_name, Error: " . $_FILES['images']['error'][$key]);
                    }
                } else {
                    error_log("Image upload error for key $key: " . $_FILES['images']['error'][$key]);
                }
            }
        } else {
            error_log("No images uploaded or images array is empty");
        }
        
        // Fix: Sync main image from product_images to products table
        $sync_query = "UPDATE products p 
                       SET p.image = (SELECT SUBSTRING_INDEX(pi.image_path, '/', -1) 
                                     FROM product_images pi 
                                     WHERE pi.product_id = p.id AND pi.is_main = 1 
                                     LIMIT 1)
                       WHERE p.image IS NULL AND p.id = ?";
        $sync_stmt = $conn->prepare($sync_query);
        $sync_stmt->bind_param("i", $product_id);
        $sync_stmt->execute();
        error_log("Synced main image for product ID: $product_id");
        
        // إضافة المخزون (الألوان والمقاسات)
        if (isset($_POST['colors']) && isset($_POST['sizes']) && isset($_POST['quantities'])) {
            for ($i = 0; $i < count($_POST['colors']); $i++) {
                $color = clean_input($_POST['colors'][$i]);
                $size = clean_input($_POST['sizes'][$i]);
                $quantity = intval($_POST['quantities'][$i]);
                
                if (!empty($color) && !empty($size) && $quantity > 0) {
                    $inv_stmt = $conn->prepare("INSERT INTO product_inventory (product_id, color, size, quantity) VALUES (?, ?, ?, ?)");
                    $inv_stmt->bind_param("issi", $product_id, $color, $size, $quantity);
                    $inv_stmt->execute();
                }
            }
        }
        
        // Handle marketers assignment
        if (isset($_POST['marketers']) && !empty($_POST['marketers'])) {
            // Create product_marketers table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS product_marketers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                marketer_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_product_marketer (product_id, marketer_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (marketer_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Delete existing marketers for this product
            $conn->query("DELETE FROM product_marketers WHERE product_id = $product_id");
            
            // Insert new marketers
            foreach ($_POST['marketers'] as $marketer_id) {
                $marketer_id = intval($marketer_id);
                if ($marketer_id > 0) {
                    $pm_stmt = $conn->prepare("INSERT INTO product_marketers (product_id, marketer_id) VALUES (?, ?)");
                    $pm_stmt->bind_param("ii", $product_id, $marketer_id);
                    $pm_stmt->execute();
                }
            }
        }
        
        // Handle product shippers
        if (isset($_POST['shippers']) && is_array($_POST['shippers'])) {
            $total_assigned = 0;
            $empty_shippers = [];
            
            foreach ($_POST['shippers'] as $index => $shipper_id) {
                $shipper_id = intval($shipper_id);
                if ($shipper_id > 0) {
                    if (isset($_POST['shipper_stocks'][$index]) && $_POST['shipper_stocks'][$index] !== '') {
                        $total_assigned += intval($_POST['shipper_stocks'][$index]);
                    } else {
                        $empty_shippers[] = $index;
                    }
                }
            }
            
            $remaining_stock = max(0, $stock - $total_assigned);
            $stock_per_empty = count($empty_shippers) > 0 ? floor($remaining_stock / count($empty_shippers)) : 0;
            $extra_stock = count($empty_shippers) > 0 ? $remaining_stock % count($empty_shippers) : 0;
            
            foreach ($_POST['shippers'] as $index => $shipper_id) {
                $shipper_id = intval($shipper_id);
                if ($shipper_id > 0) {
                    if (isset($_POST['shipper_stocks'][$index]) && $_POST['shipper_stocks'][$index] !== '') {
                        $stock_alloc = intval($_POST['shipper_stocks'][$index]);
                    } else {
                        $stock_alloc = $stock_per_empty;
                        if ($extra_stock > 0) {
                            $stock_alloc++;
                            $extra_stock--;
                        }
                    }
                    
                    $ps_stmt = $conn->prepare("INSERT INTO product_shippers (product_id, shipping_company_id, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = ?");
                    $ps_stmt->bind_param("iiii", $product_id, $shipper_id, $stock_alloc, $stock_alloc);
                    $ps_stmt->execute();
                }
            }
        }
        
        // تحديث الإحصائيات بعد الإضافة
        $product_counts = get_product_counts($conn);
    } else {
        $error = "فشل في إضافة المنتج: " . $stmt->error;
    }
    } catch (Throwable $e) {
        $error = "حدث خطأ جسيم أثناء الإضافة: " . $e->getMessage();
        error_log("Add Product Throwable: " . $e->getMessage());
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// معالجة تحديث منتج
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $product_id = intval($_POST['product_id']);
    $name = clean_input($_POST['name']);
    $description = clean_input($_POST['description']);
    $price = floatval($_POST['price']);
    $commission = floatval($_POST['commission']);
    $special_commission = floatval($_POST['special_commission'] ?? 0);
    $merchant_commission = floatval($_POST['merchant_commission'] ?? 0);
    $category = clean_input($_POST['category']);
    $stock = intval($_POST['stock']);
    $shipping_company = clean_input($_POST['shipping_company'] ?? '');
    
    // البحث عن التاجر (باستخدام merchant_id مباشرة)
    $merchant_id = null;
    if (!empty($_POST['merchant_id'])) {
        $merchant_id = intval($_POST['merchant_id']);
    }
    
    // التحقق من وجود عمود status قبل استخدامه
    $check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $has_status_column = ($check_status && $check_status->num_rows > 0);
    $status = $has_status_column ? clean_input($_POST['status'] ?? 'active') : 'active'; // حالة المنتج (مفعل/غير مفعل)
    
    // تحديث المنتدلع التاجر
    try {
        if ($merchant_id && $has_status_column) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, commission = ?, merchant_commission = ?, special_commission = ?, category = ?, stock = ?, status = ?, shipping_company = ?, user_id = ? WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed (1): " . $conn->error);
            $stmt->bind_param("ssddddssssii", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $status, $shipping_company, $merchant_id, $product_id);
        } elseif ($merchant_id && !$has_status_column) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, commission = ?, merchant_commission = ?, special_commission = ?, category = ?, stock = ?, shipping_company = ?, user_id = ? WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed (2): " . $conn->error);
            $stmt->bind_param("ssddddsssii", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $shipping_company, $merchant_id, $product_id);
        } elseif (!$merchant_id && $has_status_column) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, commission = ?, merchant_commission = ?, special_commission = ?, category = ?, stock = ?, status = ?, shipping_company = ?, user_id = NULL WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed (3): " . $conn->error);
            $stmt->bind_param("ssddddssssi", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $status, $shipping_company, $product_id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, commission = ?, merchant_commission = ?, special_commission = ?, category = ?, stock = ?, shipping_company = ?, user_id = NULL WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare failed (4): " . $conn->error);
            $stmt->bind_param("ssddddsssi", $name, $description, $price, $commission, $merchant_commission, $special_commission, $category, $stock, $shipping_company, $product_id);
        }
        
        if ($stmt->execute()) {
            // التعامل مع رفع الصور الجديدة
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $upload_dir = 'imgs/';

            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] == 0) {
                    $image_name = time() . '_' . $key . '_' . basename($_FILES['images']['name'][$key]);
                    $image_path = $upload_dir . $image_name;
                    
                    if (move_uploaded_file($tmp_name, $image_path)) {
                        $is_main = 0; // الصور الجديدة ليست رئيسية
                        $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                        $img_stmt->bind_param("isi", $product_id, $image_path, $is_main);
                        $img_stmt->execute();
                    }
                }
            }
        }
        
        // حذف المخزون القديم وإضافة الجديد
        $conn->query("DELETE FROM product_inventory WHERE product_id = $product_id");
        
        // إضافة المخزون الجديد
        if (isset($_POST['colors']) && isset($_POST['sizes']) && isset($_POST['quantities'])) {
            for ($i = 0; $i < count($_POST['colors']); $i++) {
                $color = clean_input($_POST['colors'][$i]);
                $size = clean_input($_POST['sizes'][$i]);
                $quantity = intval($_POST['quantities'][$i]);
                
                if (!empty($color) && !empty($size) && $quantity > 0) {
                    $inv_stmt = $conn->prepare("INSERT INTO product_inventory (product_id, color, size, quantity) VALUES (?, ?, ?, ?)");
                    $inv_stmt->bind_param("issi", $product_id, $color, $size, $quantity);
                    $inv_stmt->execute();
                }
            }
        }
        
        // Handle marketers assignment
        if (isset($_POST['marketers']) && !empty($_POST['marketers'])) {
            // Create product_marketers table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS product_marketers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                marketer_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_product_marketer (product_id, marketer_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (marketer_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Delete existing marketers for this product
            $conn->query("DELETE FROM product_marketers WHERE product_id = $product_id");
            
            // Insert new marketers
            foreach ($_POST['marketers'] as $marketer_id) {
                $marketer_id = intval($marketer_id);
                if ($marketer_id > 0) {
                    $pm_stmt = $conn->prepare("INSERT INTO product_marketers (product_id, marketer_id) VALUES (?, ?)");
                    $pm_stmt->bind_param("ii", $product_id, $marketer_id);
                    $pm_stmt->execute();
                }
            }
        } else {
            // If no marketers selected, delete all existing marketers for this product
            $conn->query("DELETE FROM product_marketers WHERE product_id = $product_id");
        }
        
        // Handle product shippers for update
        $conn->query("DELETE FROM product_shippers WHERE product_id = $product_id");
        if (isset($_POST['shippers']) && is_array($_POST['shippers'])) {
            $total_assigned = 0;
            $empty_shippers = [];
            
            foreach ($_POST['shippers'] as $index => $shipper_id) {
                $shipper_id = intval($shipper_id);
                if ($shipper_id > 0) {
                    if (isset($_POST['shipper_stocks'][$index]) && $_POST['shipper_stocks'][$index] !== '') {
                        $total_assigned += intval($_POST['shipper_stocks'][$index]);
                    } else {
                        $empty_shippers[] = $index;
                    }
                }
            }
            
            $remaining_stock = max(0, $stock - $total_assigned);
            $stock_per_empty = count($empty_shippers) > 0 ? floor($remaining_stock / count($empty_shippers)) : 0;
            $extra_stock = count($empty_shippers) > 0 ? $remaining_stock % count($empty_shippers) : 0;
            
            foreach ($_POST['shippers'] as $index => $shipper_id) {
                $shipper_id = intval($shipper_id);
                if ($shipper_id > 0) {
                    if (isset($_POST['shipper_stocks'][$index]) && $_POST['shipper_stocks'][$index] !== '') {
                        $stock_alloc = intval($_POST['shipper_stocks'][$index]);
                    } else {
                        $stock_alloc = $stock_per_empty;
                        if ($extra_stock > 0) {
                            $stock_alloc++;
                            $extra_stock--;
                        }
                    }
                    
                    $ps_stmt = $conn->prepare("INSERT INTO product_shippers (product_id, shipping_company_id, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = ?");
                    $ps_stmt->bind_param("iiii", $product_id, $shipper_id, $stock_alloc, $stock_alloc);
                    $ps_stmt->execute();
                }
            }
        }
        
        // تحديث ألوان الصور الحالية
        if (isset($_POST['image_colors'])) {
            foreach ($_POST['image_colors'] as $img_id => $color_name) {
                $img_id = intval($img_id);
                $color_name = clean_input($color_name);
                $conn->query("UPDATE product_images SET color_name = '$color_name' WHERE id = $img_id");
            }
        }
        
        $success = "تم تحديث المنتج بنجاح!";
        $product_counts = get_product_counts($conn);
    } else {
        $error = "فشل في تحديث المنتج: " . $stmt->error;
    }
    } catch (Throwable $e) {
        $error = "حدث خطأ جسيم أثناء التحديث: " . $e->getMessage();
        error_log("Update Throwable: " . $e->getMessage());
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// معالجة حذف صورة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_id = intval($_POST['delete_image']);
    $conn->query("DELETE FROM product_images WHERE id = $image_id");
    $success = "تم حذف الصورة بنجاح!";
}

// معالجة تعيين صورة رئيسية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_main_image'])) {
    $image_id = intval($_POST['set_main_image']);
    $product_id = intval($_POST['product_id'] ?? 0);
    
    $conn->query("UPDATE product_images SET is_main = 0 WHERE product_id = $product_id");
    $conn->query("UPDATE product_images SET is_main = 1 WHERE id = $image_id");
    $success = "تم تعيين الصورة كرئيسية بنجاح!";
}

// التحقق من وجود عمود status
$has_status_column = false;
$check_status = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($check_status && $check_status->num_rows > 0) {
    $has_status_column = true;
}

// معالجة تغيير حالة المنتج (تفعيل/تعطيل)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && $has_status_column) {
    $product_id = intval($_POST['toggle_status']);
    $current_status = $conn->query("SELECT status FROM products WHERE id = $product_id")->fetch_assoc()['status'] ?? 'active';
    $new_status = $current_status == 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE products SET status = '$new_status' WHERE id = $product_id");
    $success = "تم " . ($new_status == 'active' ? 'تفعيل' : 'تعطيل') . " المنتج بنجاح!";
    $product_counts = get_product_counts($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && !$has_status_column) {
    $error = "يرجى تشغيل ملف add_status_to_products.php أولاً لإضافة عمود status";
}

// معالجة حذف منتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['delete_product']);
    $conn->query("DELETE FROM products WHERE id = $product_id");
    $success = "تم حذف المنتج بنجاح!";
    $product_counts = get_product_counts($conn);
}

// معالجة إدارة المدن ومصاريف الشحن
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_city'])) {
    $city_name = clean_input($_POST['city_name']);
    $shipping_cost = floatval($_POST['shipping_cost']);
    
    // التحقق من وجود الجدول
    $conn->query("CREATE TABLE IF NOT EXISTS shipping_cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        city_name VARCHAR(100) NOT NULL UNIQUE,
        shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $conn->prepare("INSERT INTO shipping_cities (city_name, shipping_cost) VALUES (?, ?)");
    $stmt->bind_param("sd", $city_name, $shipping_cost);
    
    if ($stmt->execute()) {
        $success = "تم إضافة المدينة بنجاح!";
    } else {
        $error = "فشل في إضافة المدينة: " . $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_city'])) {
    $city_id = intval($_POST['city_id']);
    $city_name = clean_input($_POST['city_name']);
    $shipping_cost = floatval($_POST['shipping_cost']);
    
    $stmt = $conn->prepare("UPDATE shipping_cities SET city_name = ?, shipping_cost = ? WHERE id = ?");
    $stmt->bind_param("sdi", $city_name, $shipping_cost, $city_id);
    
    if ($stmt->execute()) {
        $success = "تم تحديث المدينة بنجاح!";
    } else {
        $error = "فشل في تحديث المدينة: " . $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_city'])) {
    $city_id = intval($_POST['delete_city']);
    $conn->query("DELETE FROM shipping_cities WHERE id = $city_id");
    $success = "تم حذف المدينة بنجاح!";
}

// جلب المدن
$cities = [];
$table_check = $conn->query("SHOW TABLES LIKE 'shipping_cities'");
if ($table_check && $table_check->num_rows > 0) {
    $cities_result = $conn->query("SELECT * FROM shipping_cities ORDER BY city_name ASC");
    if ($cities_result) {
        $cities = $cities_result->fetch_all(MYSQLI_ASSOC);
    }
}

// جلب بيانات المدينة للتعديل
$edit_city = null;
if (isset($_GET['edit_city'])) {
    $city_id = intval($_GET['edit_city']);
    $edit_result = $conn->query("SELECT * FROM shipping_cities WHERE id = $city_id");
    $edit_city = $edit_result->fetch_assoc();
}

// جلب بيانات المنتج للتعديل
$edit_product = null;
$edit_inventory = [];
$edit_images = [];
if (isset($_GET['edit'])) {
    $product_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM products WHERE id = $product_id");
    $edit_product = $edit_result->fetch_assoc();
    
    $inventory_result = $conn->query("SELECT * FROM product_inventory WHERE product_id = $product_id");
    $edit_inventory = $inventory_result->fetch_all(MYSQLI_ASSOC);
    
    $images_result = $conn->query("SELECT * FROM product_images WHERE product_id = $product_id ORDER BY is_main DESC, id ASC");
    $edit_images = $images_result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">إدارة المنتجات</h1>
    <button onclick="openModal()" class="bg-[#4b6b2f] text-white px-4 py-3 rounded-lg hover:bg-[#3a5524] flex items-center transition">
        <i class='bx bx-plus mr-2'></i>
        إضافة منتج جديد
    </button>
</div>

<!-- رسائل النجاح/الخطأ -->
<?php if(isset($success)): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-check-circle mr-2'></i>
    <?= $success ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
    <i class='bx bx-error-alt mr-2'></i>
    <?= $error ?>
</div>
<?php endif; ?>

<!-- إحصائيات سريعة -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card p-6 border-l-4 border-l-blue-500">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                <i class='bx bx-package text-blue-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">إجمالي المنتجات</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $product_counts['total'] ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-green-500">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg mr-4">
                <i class='bx bx-check-circle text-green-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">منتجات متوفرة</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $product_counts['available'] ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-yellow-500">
        <div class="flex items-center">
            <div class="p-3 bg-yellow-100 rounded-lg mr-4">
                <i class='bx bx-error-circle text-yellow-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">منخفضة المخزون</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $product_counts['low_stock'] ?></h3>
            </div>
        </div>
    </div>
    
    <div class="stat-card p-6 border-l-4 border-l-red-500">
        <div class="flex items-center">
            <div class="p-3 bg-red-100 rounded-lg mr-4">
                <i class='bx bx-x-circle text-red-600 text-2xl'></i>
            </div>
            <div>
                <p class="text-gray-600">غير متوفرة</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $product_counts['out_of_stock'] ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- جدول المنتجات -->
<div class="stat-card p-6">
    <h3 class="text-xl font-bold mb-4 text-gray-800">قائمة المنتجات</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                    <th class="p-4 text-right text-gray-700 font-bold">#</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الصورة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">اسم المنتج</th>
                    <th class="p-4 text-right text-gray-700 font-bold">التاجر</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الفئة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">السعر</th>
                    <th class="p-4 text-right text-gray-700 font-bold">العمولة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">المخزون</th>
                    <?php if ($has_status_column): ?>
                    <th class="p-4 text-right text-gray-700 font-bold">الحالة</th>
                    <?php endif; ?>
                    <th class="p-4 text-right text-gray-700 font-bold">شركة الشحن</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $products = $conn->query("
                    SELECT p.*, pi.image_path, u.fullname as merchant_name,
                           (SELECT GROUP_CONCAT(sc.name SEPARATOR '، ') 
                            FROM product_shippers ps 
                            JOIN shipping_companies sc ON ps.shipping_company_id = sc.id 
                            WHERE ps.product_id = p.id) as shipper_names
                    FROM products p 
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1 
                    LEFT JOIN users u ON p.user_id = u.id
                    ORDER BY p.created_at DESC
                ");
                while($product = $products->fetch_assoc()):
                    $stock_status = $product['stock'] > 10 ? 'جيد' : ($product['stock'] > 0 ? 'منخفض' : 'نفذ');
                    $stock_status_color = $product['stock'] > 10 ? 'bg-green-500' : ($product['stock'] > 0 ? 'bg-yellow-500' : 'bg-red-500');
                    $product_status = ($has_status_column && isset($product['status'])) ? $product['status'] : 'active';
                    $product_status_text = $product_status == 'active' ? 'مفعل' : 'غير مفعل';
                    $product_status_color = $product_status == 'active' ? 'bg-green-500' : 'bg-gray-500';
                ?>
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="p-4 text-gray-600"><?= $product['id'] ?></td>
                    <td class="p-4">
                        <?php if($product['image_path']): ?>
                            <img src="<?= $product['image_path'] ?>" alt="<?= $product['name'] ?>" class="w-12 h-12 object-cover rounded-lg border border-gray-200">
                        <?php else: ?>
                            <div class="w-12 h-12 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                                <i class='bx bx-image text-gray-400'></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="text-gray-800 font-medium"><?= $product['name'] ?></div>
                        <div class="text-gray-500 text-sm mt-1"><?= substr($product['description'], 0, 50) ?>...</div>
                    </td>
                    <td class="p-4">
                        <?php if(!empty($product['merchant_name'])): ?>
                            <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">
                                <?= htmlspecialchars($product['merchant_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-medium">
                                عام
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                            <?= $product['category'] ?>
                        </span>
                    </td>
                    <td class="p-4 font-bold text-green-600"><?= number_format($product['price'], 2) ?> د.ل</td>
                    <td class="p-4 font-bold text-purple-600">
                        <?= number_format($product['commission'], 2) ?> د.ل
                        <?php if($product['special_commission'] > 0): ?>
                            <br><span class="text-xs text-red-500" title="عمولة خاصة (خصم من المسوق)">-<?= number_format($product['special_commission'], 2) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center justify-center space-x-2">
                            <span class="font-medium"><?= $product['stock'] ?></span>
                            <span class="px-2 py-1 rounded-full text-xs text-white <?= $stock_status_color ?>">
                                <?= $stock_status ?>
                            </span>
                        </div>
                    </td>
                    <?php if ($has_status_column): ?>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-white <?= $product_status_color ?> text-sm font-medium">
                            <?= $product_status_text ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="p-4 text-gray-600">
                        <?php 
                        if (!empty($product['shipper_names'])) {
                            echo htmlspecialchars($product['shipper_names']);
                        } elseif (!empty($product['shipping_company'])) {
                            // Backwards compatibility for older products
                            echo htmlspecialchars($product['shipping_company']);
                        } else {
                            echo '<span class="text-xs bg-gray-100 px-2 py-1 rounded">الكل</span>';
                        }
                        ?>
                    </td>
                    <td class="p-4">
                        <div class="flex space-x-2 space-x-reverse">
                            <?php if ($has_status_column): ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="toggle_status" value="<?= $product['id'] ?>">
                                <button type="submit"
                               class="<?= $product_status == 'active' ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600' ?> text-white px-3 py-2 rounded-lg text-sm flex items-center transition"
                               title="<?= $product_status == 'active' ? 'تعطيل' : 'تفعيل' ?>">
                                <i class='bx bx-<?= $product_status == 'active' ? 'pause' : 'play' ?> mr-1'></i>
                                <?= $product_status == 'active' ? 'تعطيل' : 'تفعيل' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button onclick="openEditModal(<?= $product['id'] ?>)" 
                               class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm flex items-center transition">
                                <i class='bx bx-edit mr-1'></i>
                                تعديل
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_product" value="<?= $product['id'] ?>">
                                <button type="submit"
                               class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 text-sm flex items-center transition">
                                <i class='bx bx-trash mr-1'></i>
                                حذف
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal إضافة منتج جديد -->
<div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
            <h3 class="text-2xl font-bold text-gray-800">إضافة منتج جديد</h3>
            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="add_product" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- المعلومات الأساسية -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">التاجر (اختياري)</label>
                        <select name="merchant_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                            <option value="">اختر التاجر (أو اترك فارغاً لمنتج عام)</option>
                            <?php
                            $merchants_query = $conn->query("SELECT id, fullname, email FROM users WHERE user_type = 'تاجر' ORDER BY fullname");
                            if ($merchants_query && $merchants_query->num_rows > 0) {
                                while ($merchant = $merchants_query->fetch_assoc()) {
                                    echo "<option value='" . $merchant['id'] . "'>" . htmlspecialchars($merchant['fullname'] . ' (' . $merchant['email'] . ')') . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">اختر التاجر من القائمة أو اتركه فارغاً لمنتج عام</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">المسوقون (اختياري)</label>
                        <div class="border border-gray-300 rounded-lg p-3 max-h-32 overflow-y-auto">
                            <?php
                            $marketers_query = $conn->query("SELECT id, fullname, email FROM users WHERE user_type = 'مسوق' ORDER BY fullname");
                            if ($marketers_query && $marketers_query->num_rows > 0) {
                                while ($marketer = $marketers_query->fetch_assoc()) {
                                    echo '<div class="flex items-center mb-2">';
                                    echo '<input type="checkbox" name="marketers[]" value="' . $marketer['id'] . '" class="ml-2" id="marketer_' . $marketer['id'] . '">';
                                    echo '<label for="marketer_' . $marketer['id'] . '" class="text-sm text-gray-700 cursor-pointer">';
                                    echo htmlspecialchars($marketer['fullname'] . ' (' . $marketer['email'] . ')');
                                    echo '</label>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="text-sm text-gray-500">لا يوجد مسوقون مسجلون</p>';
                            }
                            ?>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">اختر المسوقين من القائمة أو اترك فارغاً لعدم تحديد مسوقين</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">اسم المنتج *</label>
                        <input type="text" name="name" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">وصف المنتج *</label>
                        <textarea name="description" id="description" rows="8" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition"
                                  placeholder="اكتب وصف المنتج هنا..."></textarea>
                        <div class="mt-2 flex space-x-2 space-x-reverse">
                            <button type="button" onclick="insertSizeTable()" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                                إضافة جدول المقاسات
                            </button>
                            <button type="button" onclick="insertFeatures()" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                إضافة المميزات
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">الفئة *</label>
                        <input type="text" name="category" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition"
                               placeholder="مثال: ملابس رجالي">
                    </div>
                </div>
                
                <!-- الأسعار والمخزون -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">السعر (د.ل) *</label>
                            <input type="number" name="price" step="0.01" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        </div>
                        
                        <!-- Shipping handled via multi-shipper block below -->
                        
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة المسوق (د.ل) *</label>
                            <input type="number" name="commission" step="0.01" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        </div>

                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة التاجر (د.ل) *</label>
                            <input type="number" name="merchant_commission" step="0.01" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition"
                                   placeholder="الربح المخصص للتاجر">
                        </div>

                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة خاصة (خصم من المسوق) - اختياري</label>
                            <div class="relative">
                                <input type="number" name="special_commission" step="0.01" value="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition">
                                <div class="absolute left-3 top-3 text-gray-400">د.ل</div>
                            </div>
                            <p class="text-xs text-red-500 mt-1">هذه القيمة سيتم خصمها من عمولة المسوق عند بيع المنتج</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2 font-medium">الكمية الإجمالية *</label>
                        <input type="number" name="stock" id="product-stock" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                    </div>
                    
                    <?php if ($has_status_column): ?>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">حالة المنتج *</label>
                        <select name="status" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                            <option value="active">مفعل</option>
                            <option value="inactive">غير مفعل</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-yellow-800 text-sm">
                            <i class='bx bx-info-circle'></i> 
                            يرجى تشغيل ملف <code>add_status_to_products.php</code> لإضافة خاصية تفعيل/تعطيل المنتجات
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">صور المنتج (يمكن اختيار أكثر من صورة)</label>
                        <input type="file" name="images[]" multiple accept="image/*"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        <p class="text-sm text-gray-500 mt-1">أول صورة سيتم اعتبارها الصورة الرئيسية</p>
                    </div>
                </div>
            </div>
            
            <!-- المخزون التفصيلي -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <label class="block text-gray-700 font-medium text-lg">المخزون التفصيلي (الألوان والمقاسات)</label>
                    <button type="button" onclick="addInventoryItem()" class="bg-[#4b6b2f] text-white px-3 py-1 rounded text-sm hover:bg-[#3a5524] transition">
                        <i class='bx bx-plus mr-1'></i> إضافة لون/مقاس
                    </button>
                </div>
                <div class="space-y-3" id="inventory-items">
                    <!-- سيتم إضافة حقول المخزون هنا ديناميكياً -->
                </div>
            </div>
            
            <!-- تخصيص شركات الشحن والمخزون -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <label class="block text-gray-700 font-medium text-lg">شركات الشحن المدعومة وتخصيص المخزون</label>
                    <button type="button" onclick="addShipperRow()" class="bg-[#4b6b2f] text-white px-3 py-1 rounded text-sm hover:bg-[#3a5524] transition">
                        <i class='bx bx-plus mr-1'></i> إضافة شركة شحن
                    </button>
                </div>
                <div class="space-y-3" id="shippers-container">
                    <!-- سيتم إضافة حقول شركات الشحن هنا ديناميكياً -->
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    * إذا تركت كمية المخزون فارغة، سيتم اعتبار الشركة مشتركة في المخزون العام للمنتج.
                </p>
            </div>
            
            <div class="flex justify-end space-x-3 space-x-reverse mt-8 pt-6 border-t border-gray-200">
                <button type="button" onclick="closeModal()" 
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-[#4b6b2f] text-white rounded-lg hover:bg-[#3a5524] transition font-medium flex items-center">
                    <i class='bx bx-plus mr-2'></i>
                    إضافة المنتج
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal التعديل -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-5xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
            <h3 class="text-2xl font-bold text-gray-800">تعديل المنتج</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="update_product" value="1">
            <input type="hidden" name="product_id" id="edit-product-id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- المعلومات الأساسية -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">التاجر (اختياري)</label>
                        <select name="merchant_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                            <option value="">اختر التاجر (أو اترك فارغاً لمنتج عام)</option>
                            <?php
                            $merchants_query = $conn->query("SELECT id, fullname, email FROM users WHERE user_type = 'تاجر' ORDER BY fullname");
                            if ($merchants_query && $merchants_query->num_rows > 0) {
                                while ($merchant = $merchants_query->fetch_assoc()) {
                                    echo "<option value='" . $merchant['id'] . "'>" . htmlspecialchars($merchant['fullname'] . ' (' . $merchant['email'] . ')') . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">اختر التاجر من القائمة أو اتركه فارغاً لمنتج عام</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">المسوقون (اختياري)</label>
                        <div class="border border-gray-300 rounded-lg p-3 max-h-32 overflow-y-auto">
                            <?php
                            $marketers_query = $conn->query("SELECT id, fullname, email FROM users WHERE user_type = 'مسوق' ORDER BY fullname");
                            if ($marketers_query && $marketers_query->num_rows > 0) {
                                while ($marketer = $marketers_query->fetch_assoc()) {
                                    echo '<div class="flex items-center mb-2">';
                                    echo '<input type="checkbox" name="marketers[]" value="' . $marketer['id'] . '" class="ml-2" id="edit_marketer_' . $marketer['id'] . '">';
                                    echo '<label for="edit_marketer_' . $marketer['id'] . '" class="text-sm text-gray-700 cursor-pointer">';
                                    echo htmlspecialchars($marketer['fullname'] . ' (' . $marketer['email'] . ')');
                                    echo '</label>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="text-sm text-gray-500">لا يوجد مسوقون مسجلون</p>';
                            }
                            ?>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">اختر المسوقين من القائمة أو اترك فارغاً لعدم تحديد مسوقين</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">اسم المنتج *</label>
                        <input type="text" name="name" required 
                               id="edit-product-name"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">وصف المنتج *</label>
                        <textarea name="description" id="edit-description" rows="8" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition"
                                  placeholder="اكتب وصف المنتج هنا..."></textarea>
                        <div class="mt-2 flex space-x-2 space-x-reverse">
                            <button type="button" onclick="insertSizeTable('edit')" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                                إضافة جدول المقاسات
                            </button>
                            <button type="button" onclick="insertFeatures('edit')" class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                إضافة المميزات
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">الفئة *</label>
                        <input type="text" name="category" required 
                               id="edit-product-category"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition"
                               placeholder="مثال: ملابس رجالي">
                    </div>
                </div>
                
                <!-- الأسعار والمخزون -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">السعر (د.ل) *</label>
                            <input type="number" name="price" step="0.01" required 
                                   id="edit-product-price"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        </div>
                        
                        <div class="hidden">
                            <label class="block text-gray-700 mb-2 font-medium">شركة الشحن</label>
                            <input type="hidden" name="shipping_company" value="">
                        </div>
                        
                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة المسوق (د.ل) *</label>
                            <input type="number" name="commission" step="0.01" required 
                                   id="edit-product-commission"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        </div>

                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة التاجر (د.ل) *</label>
                            <input type="number" name="merchant_commission" id="edit-product-merchant-commission" step="0.01" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>

                        <div class="col-span-2 md:col-span-1">
                            <label class="block text-gray-700 mb-2 font-medium">عمولة خاصة (خصم من المسوق) - اختياري</label>
                            <div class="relative">
                                <input type="number" name="special_commission" step="0.01" value="0"
                                       id="edit-special-commission"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 transition">
                                <div class="absolute left-3 top-3 text-gray-400">د.ل</div>
                            </div>
                            <p class="text-xs text-red-500 mt-1">هذه القيمة سيتم خصمها من عمولة المسوق عند بيع المنتج</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">الكمية الإجمالية *</label>
                        <input type="number" name="stock" required 
                               id="edit-product-stock"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                    </div>
                    
                    <?php if ($has_status_column): ?>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">حالة المنتج *</label>
                        <select name="status" required 
                                id="edit-product-status"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                            <option value="active">مفعل</option>
                            <option value="inactive">غير مفعل</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-yellow-800 text-sm">
                            <i class='bx bx-info-circle'></i> 
                            يرجى تشغيل ملف <code>add_status_to_products.php</code> لإضافة خاصية تفعيل/تعطيل المنتجات
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">صور المنتج (يمكن اختيار أكثر من صورة)</label>
                        <input type="file" name="images[]" id="edit-product-images-input" multiple accept="image/*"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                        <p class="text-sm text-gray-500 mt-1">أول صورة سيتم اعتبارها الصورة الرئيسية</p>
                        
                        <!-- حاوية الصور الحالية -->
                        <div id="edit-images-container" class="mt-4 grid grid-cols-2 gap-4 border-t pt-4">
                            <!-- سيتم تعبئة الصور هنا عبر JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- المخزون التفصيلي -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <label class="block text-gray-700 font-medium text-lg">المخزون التفصيلي (الألوان والمقاسات)</label>
                    <button type="button" onclick="addEditInventoryItem()" class="bg-[#4b6b2f] text-white px-3 py-1 rounded text-sm hover:bg-[#3a5524] transition">
                        <i class='bx bx-plus mr-1'></i> إضافة لون/مقاس
                    </button>
                </div>
                <div class="space-y-3" id="edit-inventory-items">
                    <!-- سيتم إضافة حقول المخزون هنا ديناميكياً -->
                </div>
            </div>
            
            <!-- تخصيص شركات الشحن والمخزون (تعديل) -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <label class="block text-gray-700 font-medium text-lg">شركات الشحن المدعومة وتخصيص المخزون</label>
                    <button type="button" onclick="addEditShipperRow()" class="bg-[#4b6b2f] text-white px-3 py-1 rounded text-sm hover:bg-[#3a5524] transition">
                        <i class='bx bx-plus mr-1'></i> إضافة شركة شحن
                    </button>
                </div>
                <div class="space-y-3" id="edit-shippers-container">
                    <!-- سيتم إضافة حقول شركات الشحن هنا ديناميكياً عبر AJAX -->
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    * إذا تركت كمية المخزون فارغة، سيتم اعتبار الشركة مشتركة في المخزون العام للمنتج.
                </p>
            </div>
            
            <div class="flex justify-end space-x-3 space-x-reverse mt-8 pt-6 border-t border-gray-200">
                <button type="button" onclick="closeEditModal()" 
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-[#4b6b2f] text-white rounded-lg hover:bg-[#3a5524] transition font-medium flex items-center">
                    <i class='bx bx-save mr-2'></i>
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let inventoryCount = 1;
let editInventoryCount = <?= count($edit_inventory) ?: 1 ?>;

// فتح modal التعديل تلقائياً عند طلب التعديل
<?php if(isset($_GET['edit']) && $edit_product): ?>
document.addEventListener('DOMContentLoaded', function() {
    openEditModal();
});
<?php endif; ?>

function openModal() {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('productModal').classList.add('flex');
    
    // Initialize first row for inventory if empty
    const inventoryContainer = document.getElementById('inventory-items');
    if (inventoryContainer && inventoryContainer.children.length === 0) {
        addInventoryItem();
    }
    
    // Initialize first row for shippers if empty
    const shippersContainer = document.getElementById('shippers-container');
    if (shippersContainer && shippersContainer.children.length === 0) {
        addShipperRow();
    }
}

function closeModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productModal').classList.remove('flex');
}

function openEditModal(product_id) {
    console.log('Opening edit modal for product:', product_id);
    
    // Fetch product data via AJAX and populate modal
    fetch('admin_products.php?get_product_data=' + product_id)
        .then(response => {
            console.log('Response received:', response);
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.success) {
                // Populate modal fields with product data using IDs
                const productIdField = document.getElementById('edit-product-id');
                if (productIdField) productIdField.value = data.product.id;
                
                const nameField = document.getElementById('edit-product-name');
                if (nameField) nameField.value = data.product.name;
                
                const descField = document.getElementById('edit-description');
                if (descField) descField.value = data.product.description;
                
                const categoryField = document.getElementById('edit-product-category');
                if (categoryField) categoryField.value = data.product.category;
                
                const priceField = document.getElementById('edit-product-price');
                if (priceField) priceField.value = data.product.price;
                
                const commissionField = document.getElementById('edit-product-commission');
                if (commissionField) commissionField.value = data.product.commission;
                
                const merchantCommissionField = document.getElementById('edit-product-merchant-commission');
                if (merchantCommissionField) merchantCommissionField.value = data.merchant_commission || data.product.merchant_commission || 0;
                
                const stockField = document.getElementById('edit-product-stock');
                if (stockField) stockField.value = data.product.stock;
                
                // Set special commission if exists
                const specialCommissionInput = document.getElementById('edit-special-commission');
                if (specialCommissionInput) {
                    specialCommissionInput.value = data.product.special_commission || 0;
                }
                
                // Set merchant id if exists - Target specifically within editModal
                const merchantInput = document.querySelector('#editModal select[name="merchant_id"]');
                if (merchantInput) {
                    merchantInput.value = data.product.user_id || '';
                }
                
                // Set shipping company - kept for backwards compat hidden field
                const shippingSelect = document.querySelector('#editModal input[name="shipping_company"]');
                if (shippingSelect) shippingSelect.value = data.product.shipping_company || '';
                
                // Set Inventory Items (Colors, Sizes, Quantities)
                const inventoryItemsContainer = document.getElementById('edit-inventory-items');
                if (inventoryItemsContainer) {
                    inventoryItemsContainer.innerHTML = ''; // clear current
                    if (data.inventory && data.inventory.length > 0) {
                        editInventoryCount = 0; // Reset counter for populated items
                        data.inventory.forEach(item => {
                            addEditInventoryItem(item.color, item.size, item.quantity);
                        });
                    } else {
                        editInventoryCount = 0;
                        addEditInventoryItem(); // Add one empty row if none exists
                    }
                }
                
                // Set Product Shippers
                const editShippersContainer = document.getElementById('edit-shippers-container');
                if (editShippersContainer) {
                    editShippersContainer.innerHTML = ''; // clear current
                    
                    if (data.shippers && data.shippers.length > 0) {
                        data.shippers.forEach(shipper => {
                            addEditShipperRow(shipper.shipping_company_id, shipper.stock);
                        });
                    } else {
                        // Add at least one empty row
                        addEditShipperRow('', '');
                    }
                }
                
                // Set status if available
                const statusSelect = document.getElementById('edit-product-status');
                if (statusSelect) {
                    statusSelect.value = data.product.status || 'active';
                }
                
                // Set marketers checkboxes
                const marketerCheckboxes = document.querySelectorAll('#editModal input[name="marketers[]"]');
                marketerCheckboxes.forEach(checkbox => {
                    checkbox.checked = data.marketers.includes(parseInt(checkbox.value));
                });
                
                // Render existing images
                const imagesContainer = document.getElementById('edit-images-container');
                if (imagesContainer) {
                    imagesContainer.innerHTML = '';
                    if (data.images && data.images.length > 0) {
                        data.images.forEach(image => {
                            const imgDiv = document.createElement('div');
                            imgDiv.className = 'relative group border rounded-lg p-2 bg-gray-50';
                            
                            const isMainBadge = image.is_main == 1 ? '<span class="absolute top-1 right-1 bg-green-500 text-white text-[10px] px-1 rounded">رئيسية</span>' : '';
                            
                            imgDiv.innerHTML = `
                                <img src="${image.image_path}" class="w-full h-32 object-cover rounded mb-2">
                                ${isMainBadge}
                                <div class="space-y-2">
                                    <input type="text" name="image_colors[${image.id}]" 
                                           value="${image.color_name || ''}" 
                                           placeholder="اللون المرتبط"
                                           class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f]">
                                    <div class="flex justify-between items-center">
                                        <button type="button" onclick="setMainImage(${image.id}, ${data.product.id})" 
                                                class="text-[10px] text-blue-600 hover:underline">
                                            تعيين كرئيسية
                                        </button>
                                        <button type="button" onclick="deleteProductImage(${image.id}, this)" 
                                                class="text-[10px] text-red-600 hover:underline">
                                            حذف
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="existing_image_ids[]" value="${image.id}">
                            `;
                            imagesContainer.appendChild(imgDiv);
                        });
                    } else {
                        imagesContainer.innerHTML = '<p class="col-span-2 text-sm text-gray-500 text-center italic">لا توجد صور حالياً</p>';
                    }
                }
                
                console.log('Modal populated successfully');
                
                // Open the modal
                const modal = document.getElementById('editModal');
                console.log('Modal element:', modal);
                console.log('Modal classes before:', modal.className);
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                
                console.log('Modal classes after:', modal.className);
                console.log('Modal should be visible now');
            } else {
                console.error('Server returned error:', data.error);
                showError('حدث خطأ في جلب بيانات المنتج: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            showError('حدث خطأ في جلب بيانات المنتج');
        });
}

function postProductAction(fields) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin_panel.php?page=products';
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = csrf.content;
        form.appendChild(tokenInput);
    }
    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function setMainImage(imageId, productId) {
    if (confirm('هل أنت متأكد من تعيين هذه الصورة كصورة رئيسية؟')) {
        postProductAction({ set_main_image: imageId, product_id: productId });
    }
}

function deleteProductImage(imageId, button) {
    if (confirm('هل أنت متأكد من حذف هذه الصورة؟')) {
        postProductAction({ delete_image: imageId });
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
    // إعادة التوجيه لإزالة معلمة edit من الرابط
    window.location.href = '?page=products';
}

function addInventoryItem(color = '', size = '', quantity = '') {
    inventoryCount++;
    const inventoryItems = document.getElementById('inventory-items');
    const newItem = document.createElement('div');
    newItem.className = 'grid grid-cols-12 gap-3 items-center bg-gray-50 p-4 rounded-lg';
    newItem.innerHTML = `
        <div class="col-span-4">
            <input type="text" name="colors[]" placeholder="اللون" value="${color}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-4">
            <input type="text" name="sizes[]" placeholder="المقاس" value="${size}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-3">
            <input type="number" name="quantities[]" placeholder="الكمية" value="${quantity}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-1">
            <button type="button" onclick="removeInventoryItem(this)" class="bg-red-500 text-white p-2 rounded hover:bg-red-600">
                <i class='bx bx-minus'></i>
            </button>
        </div>
    `;
    inventoryItems.appendChild(newItem);
}

function removeInventoryItem(button) {
    if (inventoryCount > 1) {
        button.closest('.grid').remove();
        inventoryCount--;
    }
}

function addEditInventoryItem(color = '', size = '', quantity = '') {
    editInventoryCount++;
    const inventoryItems = document.getElementById('edit-inventory-items');
    const newItem = document.createElement('div');
    newItem.className = 'grid grid-cols-12 gap-3 items-center bg-gray-50 p-4 rounded-lg';
    newItem.innerHTML = `
        <div class="col-span-4">
            <input type="text" name="colors[]" placeholder="اللون" value="${color}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-4">
            <input type="text" name="sizes[]" placeholder="المقاس" value="${size}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-3">
            <input type="number" name="quantities[]" placeholder="الكمية" value="${quantity}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-[#4b6b2f] text-sm">
        </div>
        <div class="col-span-1">
            <button type="button" onclick="removeEditInventoryItem(this)" class="bg-red-500 text-white p-2 rounded hover:bg-red-600">
                <i class='bx bx-minus'></i>
            </button>
        </div>
    `;
    inventoryItems.appendChild(newItem);
}

function removeEditInventoryItem(button) {
    if (editInventoryCount > 1) {
        button.closest('.grid').remove();
        editInventoryCount--;
    }
}

// Shipper Row Functions
const shippingCompaniesOptions = `
    <option value="">اختر شركة الشحن</option>
    <?php foreach ($shipping_companies as $company): ?>
        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
    <?php endforeach; ?>
`;

function addShipperRow() {
    const container = document.getElementById('shippers-container');
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-12 gap-3 items-center bg-gray-50 p-4 rounded-lg shipper-row';
    newRow.innerHTML = `
        <div class="col-span-7">
            <select name="shippers[]" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 text-sm">
                ${shippingCompaniesOptions}
            </select>
        </div>
        <div class="col-span-4">
            <input type="number" name="shipper_stocks[]" placeholder="الكمية المخصصة (اتركه فارغاً للاشتراك في المخزون العام)" 
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 text-sm">
        </div>
        <div class="col-span-1 text-center">
            <button type="button" onclick="removeShipperRow(this)" class="text-red-500 hover:text-red-700 p-2">
                <i class='bx bx-trash border border-red-500 rounded p-1'></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
}

function removeShipperRow(button) {
    const rows = document.querySelectorAll('#shippers-container .shipper-row');
    if (rows.length > 1) {
        button.closest('.shipper-row').remove();
    } else {
        // Clear instead of removing last row
        const row = button.closest('.shipper-row');
        row.querySelector('select').value = '';
        row.querySelector('input').value = '';
    }
}

function addEditShipperRow(companyId = '', stock = '') {
    const container = document.getElementById('edit-shippers-container');
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-12 gap-3 items-center bg-gray-50 p-4 rounded-lg edit-shipper-row';
    
    // Create the select element directly to set its value later
    const selectHtml = `
        <select name="shippers[]" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 text-sm shipper-select">
            ${shippingCompaniesOptions}
        </select>
    `;
    
    newRow.innerHTML = `
        <div class="col-span-7">
            ${selectHtml}
        </div>
        <div class="col-span-4">
            <input type="number" name="shipper_stocks[]" placeholder="الكمية المخصصة" value="${stock !== null ? stock : ''}"
                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 text-sm">
        </div>
        <div class="col-span-1 text-center">
            <button type="button" onclick="removeEditShipperRow(this)" class="text-red-500 hover:text-red-700 p-2">
                <i class='bx bx-trash border border-red-500 rounded p-1'></i>
            </button>
        </div>
    `;
    
    container.appendChild(newRow);
    
    // Set the selected value
    if (companyId) {
        newRow.querySelector('.shipper-select').value = companyId;
    }
}

function removeEditShipperRow(button) {
    const rows = document.querySelectorAll('#edit-shippers-container .edit-shipper-row');
    if (rows.length > 1) {
        button.closest('.edit-shipper-row').remove();
    } else {
        // Clear instead of removing last row
        const row = button.closest('.edit-shipper-row');
        row.querySelector('select').value = '';
        row.querySelector('input').value = '';
    }
}

// دالة لإضافة جدول المقاسات
function insertSizeTable(type = 'add') {
    const textarea = type === 'edit' ? document.getElementById('edit-description') : document.getElementById('description');
    const sizeTable = `جاكت رجالي
الخامة وترتوڤ مبطن فرو داخلي
المقاسات كبيرة من أول L حتى 3XL

📋 جدول المقاسات:
┌──────────┬─────────────────┐
│ المقاس   │ الوزن المناسب   │
├──────────┼─────────────────┤
│ L        │ من 50 إلى 65 كيلو │
├──────────┼─────────────────┤
│ XL       │ من 65 إلى 75 كيلو │
├──────────┼─────────────────┤
│ 2XL      │ من 75 إلى 90 كيلو │
├──────────┼─────────────────┤
│ 3XL      │ من 90 إلى 105 كيلو│
└──────────┴─────────────────┘`;
    
    const startPos = textarea.selectionStart;
    const endPos = textarea.selectionEnd;
    const currentValue = textarea.value;
    
    textarea.value = currentValue.substring(0, startPos) + sizeTable + currentValue.substring(endPos);
    textarea.focus();
}

// دالة لتوزيع المخزون المتبقي تلقائياً بين شركات الشحن
function updateShipperStocksDistribution(modalId = 'productModal') {
    const mainModal = document.getElementById(modalId);
    if (!mainModal) return;
    
    const stockInputId = modalId === 'editModal' ? 'edit-product-stock' : 'product-stock';
    const totalStock = parseInt(document.getElementById(stockInputId).value) || 0;
    const stockInputs = mainModal.querySelectorAll('input[name="shipper_stocks[]"]');
    
    let totalAssigned = 0;
    let emptyInputs = [];
    
    stockInputs.forEach(input => {
        if (input.value !== '' && !isNaN(input.value)) {
            totalAssigned += parseInt(input.value);
        } else {
            emptyInputs.push(input);
        }
    });
    
    const remainingStock = Math.max(0, totalStock - totalAssigned);
    const perShipper = emptyInputs.length > 0 ? Math.floor(remainingStock / emptyInputs.length) : 0;
    const extra = emptyInputs.length > 0 ? remainingStock % emptyInputs.length : 0;
    
    emptyInputs.forEach((input, index) => {
        let amount = perShipper;
        if (index < extra) amount++;
        // Update placeholder only, so the user knows what will happen on save
        input.placeholder = `البيانات المتبقية: ${amount}`;
    });
}

// Attach listeners for distribution feedback
document.addEventListener('input', function(e) {
    if (e.target.name === 'stock' || e.target.name === 'shipper_stocks[]') {
        const modal = e.target.closest('#productModal, #editModal');
        if (modal) {
            updateShipperStocksDistribution(modal.id);
        }
    }
});

// Also run it when something changes in the structure
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
            updateShipperStocksDistribution('productModal');
            updateShipperStocksDistribution('editModal');
        }
    });
});

const config = { childList: true, subtree: true };
const shippersAdd = document.getElementById('shippers-container');
const shippersEdit = document.getElementById('edit-shippers-container');
if (shippersAdd) observer.observe(shippersAdd, config);
if (shippersEdit) observer.observe(shippersEdit, config);

</script>

<!-- قسم إدارة المدن ومصاريف الشحن -->
<div class="stat-card p-6 mt-8">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-bold text-gray-800">إدارة المدن ومصاريف الشحن</h3>
        <button onclick="openCityModal()" class="bg-[#4b6b2f] text-white px-4 py-2 rounded-lg hover:bg-[#3a5524] flex items-center transition text-sm">
            <i class='bx bx-plus mr-2'></i>
            إضافة مدينة
        </button>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                    <th class="p-4 text-right text-gray-700 font-bold">#</th>
                    <th class="p-4 text-right text-gray-700 font-bold">اسم المدينة</th>
                    <th class="p-4 text-right text-gray-700 font-bold">مصاريف الشحن (د.ل)</th>
                    <th class="p-4 text-right text-gray-700 font-bold">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($cities) > 0): ?>
                    <?php foreach($cities as $city): ?>
                    <tr class="border-b hover:bg-gray-50 transition">
                        <td class="p-4 text-gray-600"><?= $city['id'] ?? '' ?></td>
                        <td class="p-4 font-medium"><?= htmlspecialchars($city['city_name'] ?? '') ?></td>
                        <td class="p-4 font-bold text-green-600"><?= number_format($city['shipping_cost'] ?? 0, 2) ?> د.ل</td>
                        <td class="p-4">
                            <div class="flex space-x-2 space-x-reverse">
                                <a href="?page=products&edit_city=<?= $city['id'] ?>" 
                                   class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm flex items-center transition">
                                    <i class='bx bx-edit mr-1'></i>
                                    تعديل
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه المدينة؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_city" value="<?= $city['id'] ?>">
                                    <button type="submit"
                                   class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 text-sm flex items-center transition">
                                    <i class='bx bx-trash mr-1'></i>
                                    حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-500">
                            <i class='bx bx-map text-4xl mb-2 block'></i>
                            لا توجد مدن مسجلة. يرجى إضافة مدن أولاً.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal إضافة/تعديل مدينة -->
<div id="cityModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
            <h3 class="text-2xl font-bold text-gray-800"><?= $edit_city ? 'تعديل مدينة' : 'إضافة مدينة جديدة' ?></h3>
            <button onclick="closeCityModal()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        
        <form method="POST">
            <?= csrf_field() ?>
            <?php if($edit_city): ?>
                <input type="hidden" name="update_city" value="1">
                <input type="hidden" name="city_id" value="<?= $edit_city['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="add_city" value="1">
            <?php endif; ?>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">اسم المدينة *</label>
                    <input type="text" name="city_name" required 
                           value="<?= $edit_city ? htmlspecialchars($edit_city['city_name']) : '' ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">مصاريف الشحن (د.ل) *</label>
                    <input type="number" name="shipping_cost" step="0.01" required 
                           value="<?= $edit_city ? ($edit_city['shipping_cost'] ?? 0) : '' ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#4b6b2f] focus:ring-2 focus:ring-[#4b6b2f] focus:ring-opacity-20 transition">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 space-x-reverse mt-8 pt-6 border-t border-gray-200">
                <button type="button" onclick="closeCityModal()" 
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition font-medium">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-3 bg-[#4b6b2f] text-white rounded-lg hover:bg-[#3a5524] transition font-medium flex items-center">
                    <i class='bx bx-save mr-2'></i>
                    <?= $edit_city ? 'حفظ التعديلات' : 'إضافة المدينة' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// فتح modal المدينة تلقائياً عند طلب التعديل
<?php if(isset($_GET['edit_city']) && $edit_city): ?>
document.addEventListener('DOMContentLoaded', function() {
    openCityModal();
});
<?php endif; ?>

function openCityModal() {
    document.getElementById('cityModal').classList.remove('hidden');
    document.getElementById('cityModal').classList.add('flex');
}

function closeCityModal() {
    document.getElementById('cityModal').classList.add('hidden');
    document.getElementById('cityModal').classList.remove('flex');
    // إعادة التوجيه لإزالة معلمة edit_city من الرابط
    if (window.location.href.includes('edit_city')) {
        window.location.href = '?page=products';
    }
}
</script>