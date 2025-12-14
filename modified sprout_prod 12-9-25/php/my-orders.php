<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin (prevent admin from accessing user orders page)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: Admin-Dashboard.php");
    exit();
}

// Check session timeout (30 minutes)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: Login-Form.php?error=session_expired");
    exit();
}

// Update session time
$_SESSION['login_time'] = time();

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$dbname = "sprout_productions";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);  
}

// Get user ID
$userId = $_SESSION['user_id'] ?? null;

// Get cart count for header
$cartCount = 0;
if ($userId) {
    $countQuery = "SELECT SUM(quantity) as total_items FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $countData = $countResult->fetch_assoc();
    $cartCount = $countData['total_items'] ?? 0;
    $stmt->close();
}

// Fetch user's orders from database
$orders = [];
$totalSpent = 0;
$totalOrders = 0;

if ($userId) {
    // Fetch orders from database
    $ordersQuery = "SELECT 
        o.id as order_id,
        o.order_number,
        o.total_amount,
        o.status,
        o.payment_method,
        o.created_at,
        o.shipping_address_id,
        o.payment_receipt,
        ua.full_name,
        ua.street_address,
        ua.barangay,
        ua.city,
        ua.province,
        ua.region,
        ua.postal_code,
        ua.phone_number
    FROM orders o
    LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($ordersQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get order items from order_items table
        $orderItems = [];
        $itemsQuery = "SELECT 
            oi.product_id,
            oi.quantity,
            oi.price,
            oi.discount_percent,
            oi.is_discounted,
            p.name as product_name,
            p.image_path
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?";
        
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param("i", $row['order_id']);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        while ($item = $itemsResult->fetch_assoc()) {
            // Calculate item price with discount
            $originalPrice = $item['price'];
            $discountedPrice = $originalPrice;
            
            if ($item['is_discounted'] && $item['discount_percent'] > 0) {
                $discountedPrice = $originalPrice * (1 - $item['discount_percent'] / 100);
            }
            
            // Check if review already exists for this product in this order
            $hasReview = false;
            $reviewId = null;
            $rating = 0;
            $reviewText = '';
            
            // First, check if sproutReviews table exists
            $checkTable = $conn->query("SHOW TABLES LIKE 'sproutReviews'");
            if ($checkTable->num_rows > 0) {
                $reviewQuery = "SELECT id, rating, review_text FROM sproutReviews WHERE order_id = ? AND product_id = ? AND user_id = ?";
                $reviewStmt = $conn->prepare($reviewQuery);
                $reviewStmt->bind_param("iii", $row['order_id'], $item['product_id'], $userId);
                $reviewStmt->execute();
                $reviewResult = $reviewStmt->get_result();
                if ($reviewResult->num_rows > 0) {
                    $hasReview = true;
                    $reviewData = $reviewResult->fetch_assoc();
                    $reviewId = $reviewData['id'];
                    $rating = $reviewData['rating'];
                    $reviewText = $reviewData['review_text'];
                }
                $reviewStmt->close();
            }
            
            $orderItems[] = [
                'product_id' => $item['product_id'],
                'name' => $item['product_name'] ?? 'Unknown Product',
                'quantity' => $item['quantity'],
                'price' => $discountedPrice,
                'original_price' => $originalPrice,
                'is_discounted' => $item['is_discounted'],
                'discount_percent' => $item['discount_percent'],
                'image_path' => $item['image_path'],
                'has_review' => $hasReview,
                'review_id' => $reviewId,
                'rating' => $rating,
                'review_text' => $reviewText
            ];
        }
        $itemsStmt->close();
        
        // Format shipping address
        $shippingAddress = '';
        if ($row['full_name']) {
            $shippingAddress = $row['full_name'] . ', ' . 
                            $row['street_address'] . ', ' . 
                            $row['barangay'] . ', ' . 
                            $row['city'] . ', ' . 
                            $row['province'] . ', ' . 
                            $row['region'] . ' ' . 
                            $row['postal_code'];
        }
        
        // Get receipt path
        $receiptPath = $row['payment_receipt'];
        $hasReceipt = false;
        $cleanedReceiptPath = '';
        
        if (!empty($receiptPath) && $receiptPath !== 'NULL' && strtolower($receiptPath) !== 'null') {
            $receiptPath = trim($receiptPath);
            $cleanedReceiptPath = $receiptPath;
            
            $cleanedReceiptPath = str_replace('\\', '/', $cleanedReceiptPath);
            $cleanedReceiptPath = ltrim($cleanedReceiptPath, '/');
            
            $possiblePaths = [
                '../uploads/receipts/' . basename($cleanedReceiptPath),
                '../uploads/' . basename($cleanedReceiptPath),
                'uploads/receipts/' . basename($cleanedReceiptPath),
                'uploads/' . basename($cleanedReceiptPath),
                $cleanedReceiptPath,
                '../' . $cleanedReceiptPath,
                '../../' . $cleanedReceiptPath
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path) && is_file($path)) {
                    $cleanedReceiptPath = $path;
                    $hasReceipt = true;
                    break;
                }
            }
            
            if (!$hasReceipt) {
                $currentDir = dirname(__FILE__);
                $absolutePath = realpath($currentDir . '/' . $cleanedReceiptPath);
                if ($absolutePath && file_exists($absolutePath)) {
                    $cleanedReceiptPath = $absolutePath;
                    $hasReceipt = true;
                } else {
                    $filename = basename($cleanedReceiptPath);
                    $relativePath = '../uploads/receipts/' . $filename;
                    if (file_exists($relativePath)) {
                        $cleanedReceiptPath = $relativePath;
                        $hasReceipt = true;
                    }
                }
            }
        }
        
        // Check if all items are reviewed
        $allItemsReviewed = true;
        foreach ($orderItems as $item) {
            if (!$item['has_review']) {
                $allItemsReviewed = false;
                break;
            }
        }
        
        $orderData = [
            'id' => $row['order_id'],
            'order_number' => $row['order_number'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'payment_method' => $row['payment_method'],
            'created_at' => $row['created_at'],
            'shipping_address_id' => $row['shipping_address_id'],
            'payment_receipt' => $cleanedReceiptPath,
            'has_receipt' => $hasReceipt,
            'shipping_address' => $shippingAddress,
            'phone_number' => $row['phone_number'],
            'address' => [
                'full_name' => $row['full_name'],
                'street_address' => $row['street_address'],
                'barangay' => $row['barangay'],
                'city' => $row['city'],
                'province' => $row['province'],
                'region' => $row['region'],
                'postal_code' => $row['postal_code']
            ],
            'items' => $orderItems,
            'all_items_reviewed' => $allItemsReviewed
        ];
        
        // Calculate total spent
        $status = strtolower($row['status']);
        $paymentMethod = strtolower($row['payment_method']);
        
        $shouldInclude = false;
        
        if ($status !== 'cancelled' && $status !== 'refunded') {
            if (in_array($paymentMethod, ['gcash', 'bank transfer', 'bank_transfer', 'bank'])) {
                if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'completed'])) {
                    $shouldInclude = true;
                }
            } elseif (in_array($paymentMethod, ['cod', 'cash on delivery'])) {
                if (in_array($status, ['delivered', 'completed'])) {
                    $shouldInclude = true;
                }
            } else {
                if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'completed'])) {
                    $shouldInclude = true;
                }
            }
        }
        
        if ($shouldInclude) {
            $totalSpent += $row['total_amount'];
        }
        
        $totalOrders++;
        $orders[] = $orderData;
    }
    $stmt->close();
}

// Handle review submission
if (isset($_POST['submit_review']) && $userId) {
    $orderNumber = $_POST['order_number'] ?? '';
    $productId = $_POST['product_id'] ?? '';
    $rating = $_POST['rating'] ?? 0;
    $reviewText = $_POST['review_text'] ?? '';
    $reviewId = $_POST['review_id'] ?? null;
    
    if (!empty($orderNumber) && !empty($productId) && $rating > 0) {
        // Validate rating
        $rating = min(max(intval($rating), 1), 5);
        
        // Clean review text
        $reviewText = trim($conn->real_escape_string($reviewText));
        
        // Get order ID
        $orderQuery = "SELECT id FROM orders WHERE order_number = ? AND user_id = ?";
        $orderStmt = $conn->prepare($orderQuery);
        $orderStmt->bind_param("si", $orderNumber, $userId);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        
        if ($orderResult->num_rows > 0) {
            $orderData = $orderResult->fetch_assoc();
            $orderId = $orderData['id'];
            
            // Check if review already exists for update
            if ($reviewId) {
                // Update existing review
                $updateQuery = "UPDATE sproutReviews SET 
                    rating = ?, 
                    review_text = ?, 
                    updated_at = NOW() 
                    WHERE id = ? AND user_id = ? AND order_id = ? AND product_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("isiisi", $rating, $reviewText, $reviewId, $userId, $orderId, $productId);
                
                if ($updateStmt->execute()) {
                    header("Location: my-orders.php?success=Review+updated+successfully");
                    exit();
                } else {
                    header("Location: my-orders.php?error=Failed+to+update+review");
                    exit();
                }
                $updateStmt->close();
            } else {
                // Insert new review
                // First, check if sproutReviews table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'sproutReviews'");
                if ($checkTable->num_rows == 0) {
                    // Create the table if it doesn't exist
                    $createTable = "CREATE TABLE sproutReviews (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        order_id INT NOT NULL,
                        product_id INT NOT NULL,
                        rating INT NOT NULL,
                        review_text TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user_order_product (user_id, order_id, product_id)
                    )";
                    
                    if (!$conn->query($createTable)) {
                        header("Location: my-orders.php?error=Failed+to+create+reviews+table");
                        exit();
                    }
                }
                
                // Check if review already exists (prevent duplicate)
                $checkReview = $conn->prepare("SELECT id FROM sproutReviews WHERE user_id = ? AND order_id = ? AND product_id = ?");
                $checkReview->bind_param("iii", $userId, $orderId, $productId);
                $checkReview->execute();
                $checkResult = $checkReview->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Review already exists, update it
                    $existingReview = $checkResult->fetch_assoc();
                    $existingId = $existingReview['id'];
                    
                    $updateQuery = "UPDATE sproutReviews SET 
                        rating = ?, 
                        review_text = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("isi", $rating, $reviewText, $existingId);
                    
                    if ($updateStmt->execute()) {
                        header("Location: my-orders.php?success=Review+updated+successfully");
                        exit();
                    } else {
                        header("Location: my-orders.php?error=Failed+to+update+review");
                        exit();
                    }
                    $updateStmt->close();
                } else {
                    // Insert new review
                    $insertQuery = "INSERT INTO sproutReviews (user_id, order_id, product_id, rating, review_text) 
                                   VALUES (?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertQuery);
                    $insertStmt->bind_param("iiiis", $userId, $orderId, $productId, $rating, $reviewText);
                    
                    if ($insertStmt->execute()) {
                        header("Location: my-orders.php?success=Review+submitted+successfully");
                        exit();
                    } else {
                        header("Location: my-orders.php?error=Failed+to+submit+review");
                        exit();
                    }
                    $insertStmt->close();
                }
                $checkReview->close();
            }
        } else {
            header("Location: my-orders.php?error=Order+not+found+or+doesn't+belong+to+you");
            exit();
        }
        $orderStmt->close();
    } else {
        header("Location: my-orders.php?error=Please+provide+a+rating+and+select+a+product");
        exit();
    }
}

// Handle order cancellation if requested
if (isset($_POST['cancel_order']) && $userId) {
    $orderNumber = $_POST['order_number'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (!empty($orderNumber)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Check if order exists and belongs to user
            $checkOrder = $conn->prepare("SELECT id, status FROM orders WHERE order_number = ? AND user_id = ?");
            $checkOrder->bind_param("si", $orderNumber, $userId);
            $checkOrder->execute();
            $orderResult = $checkOrder->get_result();
            
            if ($orderResult->num_rows === 0) {
                throw new Exception("Order not found or doesn't belong to you.");
            }
            
            $orderData = $orderResult->fetch_assoc();
            $orderId = $orderData['id'];
            $currentStatus = $orderData['status'];
            
            // 2. Check if order can be cancelled (only pending orders can be cancelled)
            if (strtolower($currentStatus) !== 'pending') {
                throw new Exception("Only pending orders can be cancelled.");
            }
            
            // 3. Get all items from the order
            $getItems = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $getItems->bind_param("i", $orderId);
            $getItems->execute();
            $itemsResult = $getItems->get_result();
            
            // 4. Return stock for each item
            while ($item = $itemsResult->fetch_assoc()) {
                $updateStock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $updateStock->bind_param("ii", $item['quantity'], $item['product_id']);
                $updateStock->execute();
                $updateStock->close();
            }
            
            // 5. Update order status to cancelled
            $updateOrder = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
            $updateOrder->bind_param("i", $orderId);
            $updateOrder->execute();
            
            // 6. Store cancellation reason if provided
            if (!empty($reason)) {
                // Check if order_cancellations table exists
                $checkCancellationsTable = $conn->query("SHOW TABLES LIKE 'order_cancellations'");
                if ($checkCancellationsTable->num_rows > 0) {
                    $insertReason = $conn->prepare("INSERT INTO order_cancellations (order_id, reason, cancelled_by, cancelled_at) VALUES (?, ?, 'user', NOW())");
                    $insertReason->bind_param("is", $orderId, $reason);
                    $insertReason->execute();
                    $insertReason->close();
                }
            }
            
            // 7. Commit transaction
            $conn->commit();
            
            // Redirect to refresh page and show success message
            header("Location: my-orders.php?success=Order+cancelled+successfully+and+stock+has+been+returned");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errorMessage = $e->getMessage();
            header("Location: my-orders.php?error=" . urlencode($errorMessage));
            exit();
        }
    }
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date
function formatDate($dateString) {
    return date('M d, Y', strtotime($dateString));
}

// Function to get status badge class
function getStatusClass($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'processing':
        case 'preparing':
            return 'status-processing';
        case 'shipped':
        case 'in_transit':
            return 'status-shipped';
        case 'delivered':
        case 'completed':
            return 'status-delivered';
        case 'cancelled':
        case 'refunded':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Sprout Productions</title>
    <link rel="stylesheet" href="../css/my-orders.css">
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        .orders-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 20px;
            margin-top: 120px;
        }

        .orders-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-family: 'Georgia', serif;
            color: #000;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .orders-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .orders-table-container {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .orders-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .orders-table tr:hover {
            background: #f9fafb;
        }

        .order-id {
            font-weight: 600;
            color: #000;
            font-size: 0.875rem;
        }

        .order-date {
            color: #666666;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 300px;
        }

        .item-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            padding: 0.25rem 0;
        }

        .item-name {
            flex: 1;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-quantity {
            color: #666666;
            font-size: 0.75rem;
            min-width: 30px;
        }

        .item-price {
            color: #27ae60;
            font-weight: 500;
            font-size: 0.75rem;
            min-width: 60px;
            text-align: right;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.65rem;
            margin-right: 3px;
        }

        .discount-badge {
            background: #ff4444;
            color: white;
            font-size: 0.65rem;
            padding: 1px 4px;
            border-radius: 3px;
            margin-left: 3px;
        }

        .order-total {
            font-weight: 600;
            color: #000;
            font-size: 0.875rem;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .order-payment {
            color: #666666;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
            min-width: 120px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: #f3f4f6;
            color: #374151;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #e5e7eb;
            color: #9ca3af;
        }

        .action-btn:disabled:hover {
            transform: none;
            background: #e5e7eb;
        }

        .action-btn:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }

        .view-btn:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .cancel-btn:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .review-btn:hover {
            background: #fef3c7;
            color: #d97706;
        }

        .receipt-btn:hover {
            background: #e0f2fe;
            color: #0369a1;
        }

        .empty-orders {
            text-align: center;
            padding: 4rem 2rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .empty-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .empty-orders h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .empty-orders p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .shop-now-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #000;
            color: white;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .shop-now-btn:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Success/Error Messages */
        .alert-message {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0.75rem;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .orders-table-container {
                overflow-x: auto;
            }
            
            .orders-table {
                min-width: 1200px;
            }
            
            .orders-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .orders-container {
                margin-top: 140px;
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .orders-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }

        /* Order Details Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #000;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .close-modal:hover {
            color: #000;
        }

        .modal-body {
            padding: 24px;
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
        }

        .order-info-section {
            margin-bottom: 1.5rem;
        }

        .info-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .info-value {
            color: #666666;
            font-size: 0.875rem;
        }

        .order-items-section {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #fff;
        }

        .item-details {
            flex: 1;
        }

        .item-details h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.875rem;
            color: #374151;
        }

        .item-quantity-price {
            font-size: 0.75rem;
            color: #666666;
        }

        .order-summary {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .summary-row.total {
            font-weight: 600;
            font-size: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        /* Receipt Preview */
        .receipt-preview {
            margin-top: 1rem;
        }

        .receipt-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .receipt-image:hover {
            transform: scale(1.02);
        }

        .no-receipt {
            color: #999;
            font-style: italic;
            font-size: 0.875rem;
        }

        /* Fullscreen Receipt Modal */
        .fullscreen-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 3000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .fullscreen-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .close-fullscreen {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
            z-index: 3001;
        }

        /* Cancellation Modal */
        .cancellation-modal .modal-content {
            max-width: 500px;
        }

        .cancellation-form {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: #0066cc;
            color: white;
        }

        .btn-primary:hover {
            background: #0052a3;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        /* Review Modal */
        .review-modal .modal-content {
            max-width: 600px;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 2rem;
            margin: 1rem 0;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            margin-right: 5px;
        }

        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffd700;
        }

        .star-rating input:checked + label {
            color: #ffd700;
        }

        .review-form {
            padding: 1.5rem;
        }

        .product-selector {
            margin-bottom: 1.5rem;
        }

        .product-option {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .product-option:hover {
            background: #f9fafb;
            border-color: #0066cc;
        }

        .product-option.selected {
            background: #e0f2fe;
            border-color: #0066cc;
        }

        .product-option input[type="radio"] {
            margin-right: 0.75rem;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .product-quantity {
            font-size: 0.875rem;
            color: #666666;
        }

        .review-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            margin-left: 0.5rem;
        }

        .review-status.reviewed {
            background: #d4edda;
            color: #155724;
        }

        .review-status.not-reviewed {
            background: #fff3cd;
            color: #856404;
        }

        .rating-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .rating-hint {
            font-size: 0.875rem;
            color: #666666;
            margin-top: 0.25rem;
        }

        .review-text-container {
            margin-top: 1.5rem;
        }

        /* Discount styling */
        .discount-info {
            font-size: 0.75rem;
            color: #ff4444;
            margin-top: 2px;
        }

        .savings-badge {
            background: #4CAF50;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }

        /* Clickable receipt in table */
        .receipt-link {
            color: #0066cc;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            background: #e0f2fe;
            transition: all 0.2s;
        }

        .receipt-link:hover {
            background: #bae6fd;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 102, 204, 0.2);
        }

        .receipt-icon {
            font-size: 0.9rem;
        }

        /* Receipt not found styling */
        .receipt-not-found {
            color: #dc2626;
            font-size: 0.75rem;
            font-style: italic;
        }

        /* Review button styling */
        .review-btn.has-review {
            background: #d4edda;
            color: #155724;
        }

        .review-btn.has-review:hover {
            background: #c3e6cb;
            color: #0c4720;
        }

        .review-btn.disabled {
            background: #e5e7eb !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
        }

        .review-btn.disabled:hover {
            transform: none !important;
            background: #e5e7eb !important;
        }

        /* Stars in review status */
        .stars-display {
            display: inline-block;
            font-size: 0.9rem;
            color: #ffd700;
            margin-left: 5px;
        }

        .stars-display i.fas {
            margin-right: 1px;
        }
    </style>
</head>
<body>
    <!-- Fixed Header -->
    <header class="sticky-header">
        <!-- Top Bar with User Info and Logout -->
        <div class="top-bar">
            <div class="container">
                <div class="top-bar-content">
                    <div class="user-info-with-icon">
                        <img src="../images/user_logo.png" alt="User" class="user-icon-small">
                        <span class="welcome-text">Welcome,</span>
                        <span class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?> (<?php echo $_SESSION['role']; ?>)</span>
                    </div>
                    <div class="top-bar-actions">
                        <a href="logout.php" class="logout-link-no-icon">
                            Logout
                        </a>
                        <img src="../images/close_logo.png" alt="Close" class="close-icon">
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Navigation -->
        <div class="main-navigation">
            <div class="container">
                <div class="nav-content">
                    <!-- Logo -->
                    <div class="logo">
                        <a href="Landing-Page-Section.php" class="logo-link">
                            <img src="../images/sprout logo bg-removed 3.png" alt="Sprout Logo" class="logo-img">
                            <span class="logo-text">SPROUT PRODUCTIONS</span>
                        </a>
                    </div>

                    <!-- Center Navigation Menu -->
                    <nav class="center-nav">
                        <ul class="nav-menu">
                            <li><a href="New-Arrival-Section.php">New Arrivals</a></li>
                            <li><a href="Best-Sellers-Section.php">Best Sellers</a></li>
                            <li><a href="Limited-Time-Offers.php">Special Offers</a></li>
                            <li><a href="my-orders.php" class="active">My Orders</a></li>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <a href="cart-section.php" class="icon-link">
                                <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                                <span id="cart-badge" class="icon-badge"><?php echo $cartCount; ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="orders-container">
        <div class="orders-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-bag"></i> My Orders
            </h1>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="orders-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $totalOrders; ?></span>
                    <span class="stat-label">Total Orders</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo formatCurrency($totalSpent); ?></span>
                    <span class="stat-label">Total Spent*</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php 
                        $pendingOrders = array_filter($orders, function($order) {
                            return strtolower($order['status']) === 'pending';
                        });
                        echo count($pendingOrders);
                        ?>
                    </span>
                    <span class="stat-label">Pending Orders</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php 
                        $deliveredOrders = array_filter($orders, function($order) {
                            return strtolower($order['status']) === 'delivered' || strtolower($order['status']) === 'completed';
                        });
                        echo count($deliveredOrders);
                        ?>
                    </span>
                    <span class="stat-label">Delivered Orders</span>
                </div>
            </div>
            <p style="font-size: 0.85rem; color: #666; margin-top: -1rem; margin-bottom: 1rem;">
                *Total spent includes: Pending/Delivered GCash/Bank orders and Delivered COD orders. Excludes cancelled orders and pending COD orders.
            </p>
        </div>

        <?php if (!empty($orders)): ?>
            <div class="orders-table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <th>Receipt</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="order-id"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td class="order-date"><?php echo formatDate($order['created_at']); ?></td>
                                <td class="order-items">
                                    <div class="items-list">
                                        <?php if (!empty($order['items'])): ?>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="item-row">
                                                    <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                                    <span class="item-quantity">x<?php echo $item['quantity']; ?></span>
                                                    <span class="item-price">
                                                        <?php if ($item['is_discounted'] && $item['original_price'] > $item['price']): ?>
                                                            <span class="original-price"><?php echo formatCurrency($item['original_price']); ?></span>
                                                            <?php echo formatCurrency($item['price']); ?>
                                                            <span class="discount-badge">-<?php echo $item['discount_percent']; ?>%</span>
                                                        <?php else: ?>
                                                            <?php echo formatCurrency($item['price']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php if ($item['has_review']): ?>
                                                        <span class="stars-display" title="Reviewed">
                                                            <?php 
                                                            $rating = $item['rating'];
                                                            $fullStars = floor($rating);
                                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                                            
                                                            for ($i = 1; $i <= 5; $i++): 
                                                                if ($i <= $fullStars): ?>
                                                                    <i class="fas fa-star"></i>
                                                                <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                                                    <i class="fas fa-star-half-alt"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="item-row">
                                                <span class="item-name">No item details available</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="order-total"><?php echo formatCurrency($order['total_amount']); ?></td>
                                <td class="order-status">
                                    <span class="status-badge <?php echo getStatusClass($order['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                    </span>
                                </td>
                                <td class="order-payment">
                                    <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>
                                </td>
                                <td class="order-receipt">
                                    <?php if ($order['has_receipt'] && !empty($order['payment_receipt'])): ?>
                                        <?php 
                                        // Clean the path for JavaScript
                                        $cleanReceiptPath = str_replace('\\', '/', $order['payment_receipt']);
                                        $encodedReceiptPath = htmlspecialchars($cleanReceiptPath, ENT_QUOTES);
                                        ?>
                                        <a href="javascript:void(0);" 
                                           class="receipt-link" 
                                           onclick="viewReceipt('<?php echo $encodedReceiptPath; ?>')"
                                           title="View Receipt - Click to view fullscreen">
                                            <i class="fas fa-receipt receipt-icon"></i> View Receipt
                                        </a>
                                    <?php else: ?>
                                        <span class="no-tracking">No Receipt</span>
                                        <?php if (!empty($order['payment_receipt'])): ?>
                                            <div class="receipt-not-found">
                                                File not found: <?php echo basename($order['payment_receipt']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="order-actions">
                                    <button class="action-btn view-btn" title="View Details" onclick="viewOrderDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (strtolower($order['status']) === 'pending'): ?>
                                        <button class="action-btn cancel-btn" title="Cancel Order" onclick="showCancellationModal('<?php echo $order['order_number']; ?>')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (strtolower($order['status']) === 'delivered' || strtolower($order['status']) === 'completed'): ?>
                                        <?php 
                                        $hasAnyReview = false;
                                        $allItemsReviewed = $order['all_items_reviewed'];
                                        foreach ($order['items'] as $item) {
                                            if ($item['has_review']) {
                                                $hasAnyReview = true;
                                                break;
                                            }
                                        }
                                        ?>
                                        <button class="action-btn review-btn <?php echo $hasAnyReview ? 'has-review' : ''; ?> <?php echo $allItemsReviewed ? 'disabled' : ''; ?>" 
                                                title="<?php 
                                                    if ($allItemsReviewed) {
                                                        echo 'All items reviewed';
                                                    } else {
                                                        echo $hasAnyReview ? 'Write/Edit Review' : 'Write Review';
                                                    }
                                                ?>" 
                                                onclick="<?php echo $allItemsReviewed ? 'void(0);' : "showReviewModal('" . $order['order_number'] . "', " . htmlspecialchars(json_encode($order['items'])) . ")"; ?>"
                                                <?php echo $allItemsReviewed ? 'disabled' : ''; ?>>
                                            <i class="fas fa-star"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-orders">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders yet.</p>
                <a href="Landing-Page-Section.php" class="shop-now-btn">
                    <i class="fas fa-shopping-cart"></i> Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Order Details Modal -->
    <div class="modal-overlay" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <button class="close-modal" onclick="closeModal('orderDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Content will be loaded here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div class="modal-overlay cancellation-modal" id="cancellationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cancel Order</h2>
                <button class="close-modal" onclick="closeModal('cancellationModal')">&times;</button>
            </div>
            <form id="cancellationForm" method="POST" action="" class="cancellation-form">
                <input type="hidden" name="cancel_order" value="1">
                <input type="hidden" name="order_number" id="cancellationOrderNumber">
                
                <div class="form-group">
                    <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                    <p><strong>Note:</strong> The product quantities will be returned to stock.</p>
                </div>
                
                <div class="form-group">
                    <label for="cancellationReason" class="form-label">Reason for Cancellation (Optional):</label>
                    <textarea id="cancellationReason" name="reason" class="form-control form-textarea" placeholder="Please provide a reason for cancellation..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancellationModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal-overlay review-modal" id="reviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Write a Review</h2>
                <button class="close-modal" onclick="closeModal('reviewModal')">&times;</button>
            </div>
            <form id="reviewForm" method="POST" action="" class="review-form">
                <input type="hidden" name="submit_review" value="1">
                <input type="hidden" name="order_number" id="reviewOrderNumber">
                <input type="hidden" name="review_id" id="reviewId">
                <input type="hidden" name="product_id" id="reviewProductId">
                
                <div class="form-group product-selector">
                    <label class="form-label">Select Product to Review:</label>
                    <div id="productOptions">
                        <!-- Product options will be loaded here by JavaScript -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rating:</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" />
                        <label for="star5" title="5 stars">★</label>
                        <input type="radio" id="star4" name="rating" value="4" />
                        <label for="star4" title="4 stars">★</label>
                        <input type="radio" id="star3" name="rating" value="3" />
                        <label for="star3" title="3 stars">★</label>
                        <input type="radio" id="star2" name="rating" value="2" />
                        <label for="star2" title="2 stars">★</label>
                        <input type="radio" id="star1" name="rating" value="1" />
                        <label for="star1" title="1 star">★</label>
                    </div>
                    <p class="rating-hint">Click on a star to rate this product (1-5 stars)</p>
                </div>
                
                <div class="form-group review-text-container">
                    <label for="reviewText" class="form-label">Your Review (Optional):</label>
                    <textarea id="reviewText" name="review_text" class="form-control form-textarea" 
                              placeholder="Share your experience with this product... What did you like or dislike? Would you recommend it to others?" 
                              rows="4"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fullscreen Receipt Modal -->
    <div class="fullscreen-modal" id="fullscreenReceiptModal">
        <button class="close-fullscreen" onclick="closeFullscreenModal()">&times;</button>
        <img id="fullscreenReceiptImage" class="fullscreen-image" src="" alt="Payment Receipt">
        <div id="receiptError" style="color: white; text-align: center; display: none;">
            <p>Unable to load receipt image.</p>
            <p>Please check if the file exists: <span id="receiptPath"></span></p>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>SPROUT PRODUCTIONS</h3>
                <p class="footer-description">
                    Proudly Bisaya. Proudly Bisdak. Style with Soul. Rooted in Bisaya Pride. Bisaya-Born. Culture-Worn.
                </p>
                <div class="social-icons">
                    <div class="social-icon-fb"></div>
                    <div class="social-icon-insta"></div>
                    <div class="social-icon-github"></div>
                    <div class="social-icon-twitter"></div>
                </div>
            </div>

            <div class="footer-column">
                <h3>COMPANY</h3>
                <ul class="footer-links">
                    <li><a href="#">About</a></li>
                    <li><a href="#">Features</a></li>
                    <li><a href="#">Works</a></li>
                    <li><a href="#">Career</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h3>HELP</h3>
                <ul class="footer-links">
                    <li><a href="#">Customer Support</a></li>
                    <li><a href="#">Delivery Details</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h3>FAQ</h3>
                <ul class="footer-links">
                    <li><a href="#">Account</a></li>
                    <li><a href="#">Manage Deliveries</a></li>
                    <li><a href="#">Orders</a></li>
                    <li><a href="#">Payments</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            Sprout Productions © 2000-2024, All Rights Reserved<br>
            We Stand For Quality
        </div>
    </footer>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Update cart badge
    function updateCartBadge() {
        fetch('landing-page-section.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_cart_count'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartBadge = document.getElementById('cart-badge');
                if (cartBadge) {
                    cartBadge.textContent = data.item_count;
                }
            }
        });
    }
    
    // Initialize cart badge
    updateCartBadge();
    
    // Close icon functionality
    document.querySelector('.close-icon').addEventListener('click', function() {
        window.location.href = 'Landing-Page-Section.php';
    });
});

// View order details
function viewOrderDetails(order) {
    const modal = document.getElementById('orderDetailsModal');
    const content = document.getElementById('orderDetailsContent');
    
    // Format order date
    const orderDate = new Date(order.created_at);
    const formattedDate = orderDate.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Build shipping address string
    let shippingAddress = 'Not available';
    if (order.address && order.address.full_name) {
        shippingAddress = `
            ${order.address.full_name}<br>
            ${order.address.street_address}<br>
            ${order.address.barangay}, ${order.address.city}<br>
            ${order.address.province}, ${order.address.region} ${order.address.postal_code}
        `;
    }
    
    // Build receipt section
    let receiptSection = '';
    if (order.has_receipt && order.payment_receipt) {
        // Clean receipt path for JavaScript
        const cleanReceiptPath = order.payment_receipt.replace(/\\/g, '/');
        const receiptUrl = getReceiptUrl(cleanReceiptPath);
        
        receiptSection = `
            <div class="order-info-section">
                <span class="info-label">Payment Receipt</span>
                <div class="receipt-preview">
                    <img src="${receiptUrl}" 
                        alt="Payment Receipt" 
                        class="receipt-image"
                        onclick="viewFullscreenReceipt('${receiptUrl.replace(/'/g, "\\'")}')"
                        onerror="this.onerror=null; this.src='../images/default-receipt.jpg'; this.style.cursor='default'; this.onclick=null;">
                    <p style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Click on the receipt to view fullscreen
                    </p>
                </div>
            </div>
        `;
    }
    
    // Calculate totals and build items list
    let subtotal = 0;
    let totalSavings = 0;
    let itemsHtml = '';
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            
            // Calculate savings if discounted
            let savings = 0;
            let discountInfo = '';
            if (item.is_discounted && item.original_price > item.price) {
                const originalTotal = item.original_price * item.quantity;
                savings = originalTotal - itemTotal;
                totalSavings += savings;
                discountInfo = `
                    <div class="discount-info">
                        Saved: ${formatCurrency(savings)} (${item.discount_percent}% off)
                    </div>
                `;
            }
            
            // Get image path
            let imagePath = '../images/default-product.jpg';
            if (item.image_path) {
                imagePath = getImageUrl(item.image_path);
            }
            
            // Price display
            let priceDisplay = '';
            if (item.is_discounted && item.original_price > item.price) {
                priceDisplay = `
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="text-decoration: line-through; color: #999; font-size: 0.85em;">
                            ${formatCurrency(item.original_price)}
                        </span>
                        <span style="color: #27ae60; font-weight: 600;">
                            ${formatCurrency(item.price)}
                        </span>
                        <span class="discount-badge" style="font-size: 0.7em; padding: 1px 4px;">
                            -${item.discount_percent}%
                        </span>
                    </div>
                `;
            } else {
                priceDisplay = `<span style="color: #27ae60; font-weight: 600;">${formatCurrency(item.price)}</span>`;
            }
            
            // Review status with stars
            let reviewStatus = '';
            if (item.has_review) {
                const rating = item.rating || 0;
                let starsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= rating) {
                        starsHtml += '<i class="fas fa-star" style="color: #ffd700; font-size: 0.9em;"></i>';
                    } else if (i - 0.5 <= rating) {
                        starsHtml += '<i class="fas fa-star-half-alt" style="color: #ffd700; font-size: 0.9em;"></i>';
                    } else {
                        starsHtml += '<i class="far fa-star" style="color: #ffd700; font-size: 0.9em;"></i>';
                    }
                }
                reviewStatus = `
                    <div style="margin-top: 3px;">
                        <span class="review-status reviewed" style="font-size: 0.7em;">
                            Reviewed ${starsHtml}
                        </span>
                    </div>
                `;
            } else {
                reviewStatus = `<span class="review-status not-reviewed" style="font-size: 0.7em;">Not Reviewed</span>`;
            }
            
            itemsHtml += `
                <div class="order-item">
                    <img src="${imagePath}" 
                        alt="${item.name}" 
                        class="item-image"
                        onerror="this.src='../images/default-product.jpg'">
                    <div class="item-details">
                        <h4>${item.name}</h4>
                        <div class="item-quantity-price">
                            Quantity: ${item.quantity} × ${priceDisplay} = ${formatCurrency(itemTotal)}
                            ${reviewStatus}
                            ${discountInfo}
                        </div>
                    </div>
                </div>
            `;
        });
    } else {
        itemsHtml = '<p>No item details available</p>';
    }
    
    // Fixed shipping fee - ₱15 (NO TAX)
    const shipping = 15;
    const calculatedTotal = subtotal + shipping;
    
    // Build modal content
    content.innerHTML = `
        <div class="order-details-grid">
            <div>
                <div class="order-info-section">
                    <span class="info-label">Order Information</span>
                    <p class="info-value">
                        <strong>Order Number:</strong> ${order.order_number}<br>
                        <strong>Date:</strong> ${formattedDate}<br>
                        <strong>Status:</strong> <span class="status-badge ${getStatusClass(order.status)}">${order.status}</span><br>
                        <strong>Payment Method:</strong> ${order.payment_method || 'Not specified'}
                    </p>
                </div>
                
                <div class="order-info-section">
                    <span class="info-label">Shipping Address</span>
                    <p class="info-value">${shippingAddress}</p>
                </div>
                
                <div class="order-info-section">
                    <span class="info-label">Contact Information</span>
                    <p class="info-value">
                        <strong>Phone:</strong> ${order.phone_number || 'Not available'}<br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?>
                    </p>
                </div>
                
                ${receiptSection}
            </div>
            
            <div>
                <div class="order-items-section">
                    <h3 style="margin-top: 0; margin-bottom: 1rem;">
                        Order Items 
                        ${totalSavings > 0 ? `<span class="savings-badge">Saved: ${formatCurrency(totalSavings)}</span>` : ''}
                    </h3>
                    ${itemsHtml}
                </div>
                
                <div class="order-summary">
                    <h3 style="margin-top: 0; margin-bottom: 1rem;">Order Summary</h3>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>${formatCurrency(subtotal)}</span>
                    </div>
                    ${totalSavings > 0 ? `
                    <div class="summary-row" style="color: #4CAF50;">
                        <span>Discount Savings:</span>
                        <span>-${formatCurrency(totalSavings)}</span>
                    </div>
                    ` : ''}
                    <div class="summary-row">
                        <span>Delivery Fee:</span>
                        <span>${formatCurrency(shipping)}</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>${formatCurrency(order.total_amount)}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

// Show review modal
function showReviewModal(orderNumber, items) {
    const modal = document.getElementById('reviewModal');
    const orderNumberInput = document.getElementById('reviewOrderNumber');
    const productOptions = document.getElementById('productOptions');
    const reviewForm = document.getElementById('reviewForm');
    
    // Set order number
    orderNumberInput.value = orderNumber;
    
    // Clear previous product options
    productOptions.innerHTML = '';
    
    // Filter items that haven't been reviewed yet
    const unreviewedItems = items.filter(item => !item.has_review);
    const reviewedItems = items.filter(item => item.has_review);
    
    // If all items are reviewed, show reviewed items for editing
    const displayItems = unreviewedItems.length > 0 ? unreviewedItems : reviewedItems;
    
    // Create product options
    displayItems.forEach((item, index) => {
        const isSelected = index === 0;
        const isReviewed = item.has_review;
        
        const option = document.createElement('div');
        option.className = `product-option ${isSelected ? 'selected' : ''}`;
        
        // Create stars display for reviewed items
        let starsDisplay = '';
        if (isReviewed && item.rating) {
            const rating = item.rating;
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    starsDisplay += '<i class="fas fa-star" style="color: #ffd700; font-size: 0.9em;"></i>';
                } else if (i - 0.5 <= rating) {
                    starsDisplay += '<i class="fas fa-star-half-alt" style="color: #ffd700; font-size: 0.9em;"></i>';
                } else {
                    starsDisplay += '<i class="far fa-star" style="color: #ffd700; font-size: 0.9em;"></i>';
                }
            }
        }
        
        option.innerHTML = `
            <input type="radio" 
                   id="product_${item.product_id}" 
                   name="selected_product" 
                   value="${item.product_id}" 
                   data-review-id="${item.review_id || ''}"
                   data-rating="${item.rating || 0}"
                   data-review-text="${item.review_text ? item.review_text.replace(/"/g, '&quot;') : ''}"
                   ${isSelected ? 'checked' : ''}>
            <div class="product-info">
                <div class="product-name">${item.name}</div>
                <div class="product-quantity">Quantity: ${item.quantity} × ${formatCurrency(item.price)} each</div>
                ${isReviewed && item.rating ? `<div style="font-size: 0.8em; color: #666; margin-top: 2px;">Rating: ${starsDisplay}</div>` : ''}
            </div>
            <span class="review-status ${isReviewed ? 'reviewed' : 'not-reviewed'}">
                ${isReviewed ? 'Reviewed' : 'Not Reviewed'}
            </span>
        `;
        
        // Add click event to select product
        option.addEventListener('click', function() {
            // Remove selected class from all options
            document.querySelectorAll('.product-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Update form fields
            updateReviewFormFields(item);
        });
        
        productOptions.appendChild(option);
        
        // If this is the first item, update form fields
        if (isSelected) {
            updateReviewFormFields(item);
        }
    });
    
    // If no items found (shouldn't happen)
    if (displayItems.length === 0) {
        productOptions.innerHTML = '<p>No products available for review.</p>';
    }
    
    // Set form action
    reviewForm.action = window.location.href;
    
    // Show modal
    modal.style.display = 'flex';
}

// Update review form fields based on selected product
function updateReviewFormFields(item) {
    const productIdInput = document.getElementById('reviewProductId');
    const reviewIdInput = document.getElementById('reviewId');
    const ratingInputs = document.querySelectorAll('input[name="rating"]');
    const reviewText = document.getElementById('reviewText');
    
    // Set product ID
    productIdInput.value = item.product_id;
    
    // Set review ID if exists
    if (item.review_id) {
        reviewIdInput.value = item.review_id;
    } else {
        reviewIdInput.value = '';
    }
    
    // Set rating if exists
    if (item.rating && item.rating > 0) {
        ratingInputs.forEach(input => {
            if (parseInt(input.value) === parseInt(item.rating)) {
                input.checked = true;
            } else {
                input.checked = false;
            }
        });
    } else {
        ratingInputs.forEach(input => {
            input.checked = false;
        });
    }
    
    // Set review text if exists
    if (item.review_text) {
        reviewText.value = item.review_text;
    } else {
        reviewText.value = '';
    }
}

// Show cancellation modal
function showCancellationModal(orderNumber) {
    const modal = document.getElementById('cancellationModal');
    const orderNumberInput = document.getElementById('cancellationOrderNumber');
    const form = document.getElementById('cancellationForm');
    
    orderNumberInput.value = orderNumber;
    form.action = window.location.href;
    modal.style.display = 'flex';
}

// Get proper receipt URL
function getReceiptUrl(receiptPath) {
    if (!receiptPath) return '../images/default-receipt.jpg';
    
    // Clean the path
    receiptPath = receiptPath.trim().replace(/\\/g, '/');
    
    // If it's already a full URL, return it
    if (receiptPath.startsWith('http://') || receiptPath.startsWith('https://')) {
        return receiptPath;
    }
    
    // If path starts with uploads/, return it as is
    if (receiptPath.startsWith('uploads/')) {
        return '../' + receiptPath;
    }
    
    // If path contains uploads/receipts, use it
    if (receiptPath.includes('uploads/receipts/')) {
        // Check if it needs a prefix
        if (!receiptPath.startsWith('../')) {
            return '../' + receiptPath;
        }
        return receiptPath;
    }
    
    // If it's just a filename, put it in the receipts folder
    const filename = receiptPath.split('/').pop();
    if (filename === receiptPath) {
        return '../uploads/receipts/' + filename;
    }
    
    // Default: return as is
    return receiptPath;
}

// Get proper image URL
function getImageUrl(imagePath) {
    if (!imagePath) return '../images/default-product.jpg';
    
    imagePath = imagePath.trim();
    
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
    }
    
    if (imagePath.startsWith('../')) {
        return imagePath;
    }
    
    if (imagePath.startsWith('/')) {
        return '..' + imagePath;
    }
    
    return '../' + imagePath;
}

// View receipt
function viewReceipt(receiptPath) {
    console.log('Viewing receipt:', receiptPath);
    
    // Clean the receipt path
    receiptPath = receiptPath.trim().replace(/\\/g, '/');
    
    // Get the proper URL
    let receiptUrl = getReceiptUrl(receiptPath);
    
    console.log('Formatted receipt URL:', receiptUrl);
    
    // Show in fullscreen modal
    viewFullscreenReceipt(receiptUrl);
}

// View receipt in fullscreen modal
function viewFullscreenReceipt(imageUrl) {
    console.log('Opening fullscreen receipt modal:', imageUrl);
    const modal = document.getElementById('fullscreenReceiptModal');
    const image = document.getElementById('fullscreenReceiptImage');
    const errorDiv = document.getElementById('receiptError');
    const receiptPathSpan = document.getElementById('receiptPath');
    
    // Reset display
    errorDiv.style.display = 'none';
    image.style.display = 'block';
    image.src = '';
    
    // Show modal immediately
    modal.style.display = 'flex';
    
    // Clean the URL
    imageUrl = imageUrl.trim();
    
    // Create array of possible paths to try
    const possibleUrls = [
        imageUrl,
        '../' + imageUrl,
        '../../' + imageUrl,
        'uploads/receipts/' + basename(imageUrl),
        '../uploads/receipts/' + basename(imageUrl),
        '../../uploads/receipts/' + basename(imageUrl),
        '../' + basename(imageUrl),
        '../../' + basename(imageUrl)
    ];
    
    console.log('Trying possible URLs:', possibleUrls);
    
    // Function to try loading image
    function tryLoadImage(urlIndex) {
        if (urlIndex >= possibleUrls.length) {
            // All paths failed
            console.error('All receipt paths failed');
            image.style.display = 'none';
            errorDiv.style.display = 'block';
            receiptPathSpan.textContent = imageUrl;
            return;
        }
        
        const currentUrl = possibleUrls[urlIndex];
        console.log('Trying URL #' + urlIndex + ':', currentUrl);
        
        // Set the image source
        image.src = currentUrl;
        
        // Set up onload handler
        image.onload = function() {
            console.log('Successfully loaded receipt from:', currentUrl);
        };
        
        // Set up onerror handler
        image.onerror = function() {
            console.log('Failed to load from:', currentUrl);
            // Try next URL after a short delay
            setTimeout(() => tryLoadImage(urlIndex + 1), 100);
        };
    }
    
    // Start trying from first URL
    tryLoadImage(0);
}

// Close fullscreen modal
function closeFullscreenModal() {
    const modal = document.getElementById('fullscreenReceiptModal');
    const image = document.getElementById('fullscreenReceiptImage');
    
    // Reset image source
    image.src = '';
    modal.style.display = 'none';
}

// Helper function to get basename
function basename(path) {
    return path.split('/').pop().split('\\').pop();
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
    if (event.target.classList.contains('fullscreen-modal')) {
        closeFullscreenModal();
    }
};

// Escape key to close modal
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        closeFullscreenModal();
    }
});

// Helper function to format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Helper function to get status class
function getStatusClass(status) {
    status = status.toLowerCase();
    switch (status) {
        case 'pending':
            return 'status-pending';
        case 'processing':
        case 'preparing':
            return 'status-processing';
        case 'shipped':
        case 'in_transit':
            return 'status-shipped';
        case 'delivered':
        case 'completed':
            return 'status-delivered';
        case 'cancelled':
        case 'refunded':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}
    </script>
</body>
</html>
<?php 
// Close database connection
if ($conn) {
    $conn->close();
}
?>