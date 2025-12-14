<?php
// Start session and authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<script>
        alert("⚠️ ADMIN ACCESS REQUIRED\\n\\nPlease log in as an administrator first!");
        window.location.href = "Login-Form.php";
    </script>';
    exit();
}

// Check if user is admin
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    echo '<script>
        alert("⛔ ACCESS DENIED\\n\\nYou don\'t have administrator privileges!");
        window.location.href = "Landing-Page-Section.php";
    </script>';
    exit();
}

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$dbname = "sprout_productions";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_order_details':
            getOrderDetails($conn);
            break;
        case 'update_status':
            updateOrderStatus($conn);
            break;
        case 'search_orders':
            searchOrders($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// Function to get order details with discounted price
function getOrderDetails($conn) {
    $order_id = $_POST['order_id'];
    
    // Get order details - FIXED: Using CONCAT to create customer name from user ID
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            u.email,
            u.id as user_id,
            CONCAT('Customer #', u.id) as customer_name,
            ua.full_name,
            ua.street_address,
            ua.barangay,
            ua.city,
            ua.province,
            ua.region,
            ua.postal_code,
            ua.phone_number
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    $order = $order_result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    // Format shipping address
    $shipping_address = '';
    if ($order['full_name']) {
        $shipping_address = $order['full_name'] . ', ' . 
                          $order['street_address'] . ', ' . 
                          $order['barangay'] . ', ' . 
                          $order['city'] . ', ' . 
                          $order['province'] . ', ' . 
                          $order['region'] . ' ' . 
                          $order['postal_code'];
    }
    $order['formatted_shipping_address'] = $shipping_address;
    
    // Get receipt path and check if it exists
    $receiptPath = $order['payment_receipt'];
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
    
    $order['payment_receipt_path'] = $cleanedReceiptPath;
    $order['has_receipt'] = $hasReceipt;
    
    // Get order items with discounted price
    $stmt = $conn->prepare("
        SELECT 
            oi.*, 
            p.name as product_name, 
            p.price as original_price,
            COALESCE(p.discount_price, p.price) as discounted_price,
            CASE 
                WHEN p.discount_price IS NOT NULL THEN 'Yes'
                ELSE 'No'
            END as is_discounted,
            ROUND(((p.price - COALESCE(p.discount_price, p.price)) / p.price) * 100, 0) as discount_percentage
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
}

// Function to update order status
function updateOrderStatus($conn) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $current_status = $_POST['current_status'] ?? '';
    
    // Check if order is cancelled or delivered - prevent updating
    if (strtolower($current_status) === 'cancelled' || strtolower($current_status) === 'delivered') {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot update status of ' . $current_status . ' orders'
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        
        // If status changed to 'delivered', update product sold_count
        if ($new_status === 'delivered') {
            updateProductSoldCount($order_id, $conn);
        }
        
        // If status changed to 'cancelled', return stock to inventory
        if (strtolower($new_status) === 'cancelled') {
            returnStockToInventory($order_id, $conn);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully!'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order status: ' . $e->getMessage()
        ]);
    }
}

// Function to search orders (AJAX) - FIXED: Using CONCAT for customer name
function searchOrders($conn) {
    $search_query = $_POST['search_query'] ?? '';
    $status_filter = $_POST['status_filter'] ?? 'all';
    
    $query = "
        SELECT 
            o.id,
            o.order_number,
            o.total_amount,
            o.status,
            o.created_at,
            o.payment_method,
            u.email,
            u.id as user_id,
            CONCAT('Customer #', u.id) as customer_name,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE 1=1
    ";
    
    if ($status_filter !== 'all') {
        $query .= " AND o.status = ?";
    }
    
    if (!empty($search_query)) {
        // Search by order number, email, or customer number
        $query .= " AND (o.order_number LIKE ? OR u.email LIKE ? OR CONCAT('Customer #', u.id) LIKE ?)";
    }
    
    $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($query);
    
    if ($status_filter !== 'all' && !empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt->bind_param("ssss", $status_filter, $search_param, $search_param, $search_param);
    } elseif ($status_filter !== 'all') {
        $stmt->bind_param("s", $status_filter);
    } elseif (!empty($search_query)) {
        $search_param = "%$search_query%";
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
    
    $stmt->execute();
    $orders_result = $stmt->get_result();
    $orders = [];
    while ($row = $orders_result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
}

// Function to update product sold_count
function updateProductSoldCount($order_id, $conn) {
    // Get all products in this order
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.quantity 
        FROM order_items oi 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Update sold_count for each product
    while ($row = $result->fetch_assoc()) {
        $update_stmt = $conn->prepare("
            UPDATE products 
            SET sold_count = sold_count + ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $row['quantity'], $row['product_id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();
}

// Function to return stock to inventory when order is cancelled
function returnStockToInventory($order_id, $conn) {
    // Get all products in this order
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.quantity 
        FROM order_items oi 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Return stock for each product
    while ($row = $result->fetch_assoc()) {
        $update_stmt = $conn->prepare("
            UPDATE products 
            SET stock = stock + ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $row['quantity'], $row['product_id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();
}

// Handle direct status update (for form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_direct'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    // Check if order is cancelled or delivered - prevent updating
    $check_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $order_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if (strtolower($order_data['status']) === 'cancelled' || strtolower($order_data['status']) === 'delivered') {
        $_SESSION['message'] = "Cannot update status of " . $order_data['status'] . " orders!";
        $_SESSION['message_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        if ($new_status === 'delivered') {
            updateProductSoldCount($order_id, $conn);
        }
        // If status changed to 'cancelled', return stock to inventory
        if (strtolower($new_status) === 'cancelled') {
            returnStockToInventory($order_id, $conn);
        }
        $_SESSION['message'] = "Order status updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Failed to update order status!";
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch initial orders from database - FIXED: Using CONCAT for customer name
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

$query = "
    SELECT 
        o.id,
        o.order_number,
        o.total_amount,
        o.status,
        o.created_at,
        o.payment_method,
        u.email,
        u.id as user_id,
        CONCAT('Customer #', u.id) as customer_name,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE 1=1
";

if ($status_filter !== 'all') {
    $query .= " AND o.status = ?";
}

if (!empty($search_query)) {
    // Search by order number, email, or customer number
    $query .= " AND (o.order_number LIKE ? OR u.email LIKE ? OR CONCAT('Customer #', u.id) LIKE ?)";
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);

if ($status_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->bind_param("ssss", $status_filter, $search_param, $search_param, $search_param);
} elseif ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$orders_result = $stmt->get_result();
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['Order ID', 'Order Number', 'Customer', 'Email', 'Total Amount', 'Status', 'Payment Method', 'Date', 'Items Count']);
    
    // Add data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['order_number'],
            $order['customer_name'],  // Now using "Customer #id"
            $order['email'],
            '$' . number_format($order['total_amount'], 2),
            ucfirst($order['status']),
            ucfirst($order['payment_method']),
            date('M d, Y', strtotime($order['created_at'])),
            $order['item_count']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Orders - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-uniform.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background-color: #8B4513;
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .close-modal:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
        }

        /* Search Box Styles */
        .search-container {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
        }

        .search-box {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .loading-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8B4513;
            font-size: 14px;
            display: none;
        }

        /* Table Styles */
        .table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table thead {
            background-color: #8B4513;
        }

        table th {
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }

        table th:last-child {
            border-right: none;
        }

        table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        table tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }

        table tbody tr:hover:nth-child(even) {
            background-color: #f0f0f0;
        }

        table td {
            padding: 15px;
            color: #333;
            vertical-align: middle;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #FFF3CD;
            color: #856404;
        }

        .status-active {
            background-color: #D1ECF1;
            color: #0C5460;
        }

        .status-completed {
            background-color: #D4EDDA;
            color: #155724;
        }

        .status-cancelled {
            background-color: #F8D7DA;
            color: #721C24;
        }

        .status-delivered {
            background-color: #D4EDDA;
            color: #155724;
        }

        /* Action Buttons */
        .action-btn {
            padding: 8px 16px;
            background-color: #8B4513;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 500;
            margin-right: 5px;
        }

        .action-btn:hover {
            background-color: #6B3410;
            transform: translateY(-2px);
        }

        .action-btn i {
            margin-right: 5px;
        }

        .action-btn.secondary {
            background-color: #666;
        }

        .action-btn.secondary:hover {
            background-color: #555;
        }

        /* Disabled Action Button */
        .action-btn.disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .action-btn.disabled:hover {
            background-color: #cccccc;
            transform: none;
        }

        /* Quick Action Button */
        .quick-action-btn {
            background-color: #8B4513;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .quick-action-btn:hover {
            background-color: #6B3410;
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            margin-right: 8px;
        }

        /* Message alerts */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Order Details Styles */
        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            padding: 10px 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
            line-height: 1.5;
        }

        /* Products Table in Modal */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        .products-table th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #8B4513;
            color: #8B4513;
            font-weight: 600;
        }

        .products-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .products-table tr:hover {
            background-color: #f9f9f9;
        }

        /* Discount Styles */
        .discount-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 12px;
            margin-right: 5px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ddd;
        }

        /* Form Styles for Update Modal */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #8B4513;
        }

        /* Disabled form elements */
        .form-control:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            color: #666;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #8B4513;
            color: white;
        }

        .btn-primary:hover {
            background-color: #6B3410;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-primary:disabled,
        .btn-secondary:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-primary:disabled:hover,
        .btn-secondary:disabled:hover {
            background-color: #cccccc;
            transform: none;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 120px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 3000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notification.success {
            background-color: #4CAF50;
        }

        .notification.error {
            background-color: #f44336;
        }

        .notification.warning {
            background-color: #ff9800;
        }

        /* Filter Styles */
        .filter-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        select {
            padding: 8px 15px;
            border: 2px solid #8B4513;
            border-radius: 6px;
            background: white;
            color: #333;
            font-weight: 500;
            min-width: 150px;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading i {
            font-size: 48px;
            color: #8B4513;
            margin-bottom: 20px;
        }

        /* Receipt Preview Styles */
        .receipt-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e0e0e0;
        }

        .receipt-preview {
            max-width: 100%;
            margin: 15px 0;
        }

        .receipt-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            display: block;
            margin: 0 auto;
        }

        .receipt-image:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .receipt-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .receipt-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .receipt-btn.view {
            background-color: #2196F3;
            color: white;
        }

        .receipt-btn.view:hover {
            background-color: #0b7dda;
        }

        .receipt-btn.download {
            background-color: #4CAF50;
            color: white;
        }

        .receipt-btn.download:hover {
            background-color: #45a049;
        }

        .receipt-btn.verify {
            background-color: #FF9800;
            color: white;
        }

        .receipt-btn.verify:hover {
            background-color: #e68900;
        }

        .no-receipt {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        .no-receipt i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
            display: block;
        }

        /* Fullscreen Receipt Modal */
        .fullscreen-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 4000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .fullscreen-content {
            position: relative;
            max-width: 95%;
            max-height: 95%;
        }

        .fullscreen-receipt {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            display: block;
            margin: 0 auto;
        }

        .close-fullscreen {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 36px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .close-fullscreen:hover {
            background-color: rgba(255,255,255,0.1);
        }

        /* Order Information Grid */
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e0e0e0;
        }

        .info-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #8B4513;
            font-size: 18px;
            border-bottom: 2px solid #8B4513;
            padding-bottom: 8px;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .info-row .label {
            flex: 0 0 120px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .info-row .value {
            flex: 1;
            color: #333;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Completed/Delivered order notice */
        .completed-notice {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cancelled-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .completed-notice i {
            color: #155724;
            font-size: 20px;
        }

        .cancelled-notice i {
            color: #856404;
            font-size: 20px;
        }

        .completed-notice p {
            margin: 0;
            color: #155724;
            font-weight: 500;
        }

        .cancelled-notice p {
            margin: 0;
            color: #856404;
            font-weight: 500;
        }

        /* Warning badge */
        .warning-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #fff3cd;
            color: #856404;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .completed-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #d4edda;
            color: #155724;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .table-wrapper {
                overflow-x: auto;
            }
            
            table {
                min-width: 1000px;
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
                max-height: 85vh;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .order-info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-card {
                padding: 15px;
            }
            
            .receipt-image {
                max-height: 300px;
            }
            
            .receipt-actions {
                flex-direction: column;
            }
            
            .receipt-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Badge for receipt status */
        .receipt-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .receipt-verified {
            background-color: #d4edda;
            color: #155724;
        }

        .receipt-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .receipt-missing {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Verification Notes */
        .verification-notes {
            background: #fffde7;
            border-left: 4px solid #ffd600;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
        }

        .verification-notes h4 {
            margin-top: 0;
            color: #ff6f00;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .verification-notes ul {
            margin: 0;
            padding-left: 20px;
        }

        .verification-notes li {
            margin-bottom: 5px;
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
                    <div class="admin-welcome">
                        <img src="../images/user_logo.png" alt="Admin" class="admin-icon-small">
                        <span class="admin-text">Welcome, Admin</span>
                    </div>
                    <div class="top-bar-actions">
                        <a href="logout.php" class="logout-link-no-icon">
                            Logout
                        </a>
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
                        <a href="Admin-Dashboard.php" class="logo-link">
                            <img src="../images/sprout logo bg-removed 3.png" alt="Sprout Logo" class="logo-img">
                            <span class="logo-text">SPROUT PRODUCTIONS</span>
                        </a>
                    </div>

                    <!-- Center Navigation Menu -->
                    <nav class="center-nav">
                        <ul class="nav-menu">
                            <li><a href="Admin-Dashboard.php">Dashboard</a></li>
                            <li><a href="../php/Admin-Products-Page.php">Products</a></li>
                            <li><a href="../php/Admin-Orders-Page.php" class="active">Orders</a></li>
                            <li><a href="../php/Admin-Customers-Page.php">Customers</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- Notification Area -->
    <div id="notification" class="notification"></div>

    <!-- Fullscreen Receipt Modal -->
    <div id="fullscreenReceiptModal" class="fullscreen-modal">
        <div class="fullscreen-content">
            <button class="close-fullscreen" onclick="closeFullscreenModal()">&times;</button>
            <img id="fullscreenReceiptImage" class="fullscreen-receipt" src="" alt="Payment Receipt">
            <div id="receiptError" style="color: white; text-align: center; display: none; padding: 20px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h3>Unable to load receipt</h3>
                <p>The receipt image could not be loaded. It may have been moved or deleted.</p>
                <p>File path: <span id="receiptPath" style="font-family: monospace; background: rgba(255,255,255,0.1); padding: 5px; border-radius: 4px;"></span></p>
            </div>
        </div>
    </div>

    <div class="container">
        <h1 class="dashboard-title">Orders</h1>

        <!-- Message Alert -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-section">
            <!-- Search and Filters -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:15px;">
                <div class="filter-container">
                    <div>
                        <label for="statusFilter" style="font-weight:600;margin-right:10px;color:#8B4513;">Filter by Status:</label>
                        <select id="statusFilter" onchange="filterOrders()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </div>
                    <div>
                        <button class="quick-action-btn" onclick="exportOrders()">
                            <i class="fas fa-file-export"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="searchBox" 
                       class="search-box" 
                       placeholder="Search orders by Order Number or Email..." 
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       onkeyup="debouncedSearch()">
                <span class="loading-indicator" id="loadingIndicator"></span>
            </div>

            <!-- Orders Table -->
            <div class="table-wrapper">
                <table id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:40px;">
                                    <i class="fas fa-box-open" style="font-size:48px;color:#ddd;margin-bottom:20px;"></i>
                                    <p style="color:#666;">No orders found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php 
                                    $status_class = '';
                                    switch($order['status']) {
                                        case 'pending': $status_class = 'status-pending'; break;
                                        case 'processing': $status_class = 'status-active'; break;
                                        case 'delivered': $status_class = 'status-delivered'; break;
                                        case 'cancelled': $status_class = 'status-cancelled'; break;
                                        default: $status_class = 'status-pending';
                                    }
                                    
                                    // Check if order is cancelled or delivered for button disabling
                                    $is_cancelled = strtolower($order['status']) === 'cancelled';
                                    $is_delivered = strtolower($order['status']) === 'delivered';
                                    $is_locked = $is_cancelled || $is_delivered;
                                    $update_btn_class = $is_locked ? 'action-btn disabled' : 'action-btn';
                                    $update_btn_title = $is_locked ? 'Cannot edit ' . $order['status'] . ' orders' : 'Update Status';
                                    $badge_type = $is_cancelled ? 'warning-badge' : ($is_delivered ? 'completed-badge' : '');
                                ?>
                                <tr data-status="<?php echo htmlspecialchars($order['status']); ?>" 
                                    data-search="<?php echo strtolower(htmlspecialchars($order['order_number'] . ' ' . $order['customer_name'] . ' ' . $order['email'])); ?>">
                                    <td><strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                                    <td><?php echo htmlspecialchars($order['item_count']); ?> item(s)</td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                            <?php if ($is_locked): ?>
                                                <span class="<?php echo $badge_type; ?>">LOCKED</span>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($order['payment_method']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <button class="action-btn" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i>View
                                        </button>
                                        <button class="<?php echo $update_btn_class; ?>" 
                                                onclick="<?php echo $is_locked ? 'showLockedWarning(\'' . $order['status'] . '\')' : "updateOrderStatus({$order['id']}, '{$order['status']}')"; ?>"
                                                title="<?php echo $update_btn_title; ?>"
                                                <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <i class="fas fa-edit"></i>Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <button class="close-modal" onclick="closeModal('viewOrderModal')">&times;</button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading order details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Order Status</h2>
                <button class="close-modal" onclick="closeModal('updateStatusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="statusForm" method="POST" action="">
                    <input type="hidden" name="update_status_direct" value="1">
                    <input type="hidden" name="order_id" id="updateOrderId">
                    <input type="hidden" name="current_status" id="currentStatusValue">
                    
                    <!-- Locked Order Warning -->
                    <div id="lockedWarning" style="display: none;">
                        <div class="cancelled-notice" id="lockedNotice">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p id="lockedMessage"></p>
                        </div>
                    </div>
                    
                    <div class="order-details-grid" id="updateFormContent">
                        <div class="info-group">
                            <span class="info-label">Order Number</span>
                            <div class="info-value" id="updateOrderNumber"></div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">Current Status</span>
                            <div class="info-value" id="currentStatus"></div>
                        </div>
                        
                        <div class="info-group">
                            <span class="info-label">New Status</span>
                            <div class="info-value">
                                <select name="status" id="newStatus" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="delivered">Delivered</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('updateStatusModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateStatusBtn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Debounce function for search
        let searchTimeout;
        function debouncedSearch() {
            clearTimeout(searchTimeout);
            document.getElementById('loadingIndicator').style.display = 'block';
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500); // 500ms delay
        }

        // Perform search via AJAX
        async function performSearch() {
            const searchInput = document.getElementById('searchBox').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'search_orders');
                formData.append('search_query', searchInput);
                formData.append('status_filter', statusFilter);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateOrdersTable(data.orders);
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error searching orders', 'error');
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // Update orders table with search results
        function updateOrdersTable(orders) {
            const tableBody = document.getElementById('ordersTableBody');
            
            if (orders.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;">
                            <i class="fas fa-box-open" style="font-size:48px;color:#ddd;margin-bottom:20px;"></i>
                            <p style="color:#666;">No orders found</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            let tableHTML = '';
            
            orders.forEach(order => {
                const statusClass = getStatusClass(order.status);
                const isCancelled = order.status.toLowerCase() === 'cancelled';
                const isDelivered = order.status.toLowerCase() === 'delivered';
                const isLocked = isCancelled || isDelivered;
                const updateBtnClass = isLocked ? 'action-btn disabled' : 'action-btn';
                const updateBtnTitle = isLocked ? 'Cannot edit ' + order.status + ' orders' : 'Update Status';
                const updateBtnClick = isLocked ? `showLockedWarning('${order.status}')` : `updateOrderStatus(${order.id}, '${order.status}')`;
                const badgeType = isCancelled ? 'warning-badge' : (isDelivered ? 'completed-badge' : '');
                
                tableHTML += `
                    <tr data-status="${order.status}" data-search="${order.order_number.toLowerCase()} ${order.customer_name.toLowerCase()} ${order.email.toLowerCase()}">
                        <td><strong>#${order.order_number}</strong></td>
                        <td>${order.customer_name}</td>
                        <td>${order.email}</td>
                        <td>${order.item_count} item(s)</td>
                        <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                ${isLocked ? `<span class="${badgeType}">LOCKED</span>` : ''}
                            </span>
                        </td>
                        <td>${order.payment_method ? order.payment_method.charAt(0).toUpperCase() + order.payment_method.slice(1) : 'N/A'}</td>
                        <td>${new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                        <td>
                            <button class="action-btn" onclick="viewOrder(${order.id})">
                                <i class="fas fa-eye"></i>View
                            </button>
                            <button class="${updateBtnClass}" 
                                    onclick="${updateBtnClick}"
                                    title="${updateBtnTitle}"
                                    ${isLocked ? 'disabled' : ''}>
                                <i class="fas fa-edit"></i>Update
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = tableHTML;
        }

        // Show warning for locked orders (cancelled or delivered)
        function showLockedWarning(status) {
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            showNotification(statusText + ' orders cannot be edited!', 'warning');
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // View order details
        async function viewOrder(orderId) {
            const modal = document.getElementById('viewOrderModal');
            const content = document.getElementById('orderDetailsContent');
            
            content.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading order details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_order_details');
                formData.append('order_id', orderId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const order = data.order;
                    const items = data.items;
                    const isCancelled = order.status.toLowerCase() === 'cancelled';
                    const isDelivered = order.status.toLowerCase() === 'delivered';
                    const isLocked = isCancelled || isDelivered;
                    
                    // Format order date
                    const orderDate = new Date(order.created_at);
                    const formattedDate = orderDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Calculate totals
                    let subtotal = 0;
                    let totalSavings = 0;
                    let itemsHtml = '';
                    
                    if (items && items.length > 0) {
                        items.forEach(item => {
                            const itemTotal = item.discounted_price * item.quantity;
                            subtotal += itemTotal;
                            
                            // Calculate savings if discounted
                            let savings = 0;
                            if (item.is_discounted === 'Yes') {
                                const originalTotal = item.original_price * item.quantity;
                                savings = originalTotal - itemTotal;
                                totalSavings += savings;
                            }
                            
                            // Price display
                            let priceDisplay = '';
                            if (item.is_discounted === 'Yes') {
                                priceDisplay = `
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="text-decoration: line-through; color: #999; font-size: 0.85em;">
                                            $${parseFloat(item.original_price).toFixed(2)}
                                        </span>
                                        <span style="color: #27ae60; font-weight: 600;">
                                            $${parseFloat(item.discounted_price).toFixed(2)}
                                        </span>
                                        <span class="discount-badge" style="font-size: 0.7em; padding: 1px 4px;">
                                            -${item.discount_percentage}%
                                        </span>
                                    </div>
                                `;
                            } else {
                                priceDisplay = `<span style="color: #27ae60; font-weight: 600;">$${parseFloat(item.discounted_price).toFixed(2)}</span>`;
                            }
                            
                            itemsHtml += `
                                <tr>
                                    <td>${item.product_name}</td>
                                    <td>${item.quantity}</td>
                                    <td>
                                        ${priceDisplay}
                                    </td>
                                    <td>$${itemTotal.toFixed(2)}</td>
                                </tr>
                            `;
                        });
                    }
                    
                    // Build receipt section
                    let receiptSection = '';
                    if (order.has_receipt && order.payment_receipt_path) {
                        const receiptUrl = getReceiptUrl(order.payment_receipt_path);
                        receiptSection = `
                            <div class="receipt-section">
                                <h3 style="margin-top: 0;">Payment Receipt Verification</h3>
                                <p style="color: #666; margin-bottom: 15px;">Check if the payment receipt is legitimate:</p>
                                <div class="receipt-preview">
                                    <img src="${receiptUrl}" 
                                        alt="Payment Receipt" 
                                        class="receipt-image"
                                        onclick="viewFullscreenReceipt('${receiptUrl.replace(/'/g, "\\'")}')"
                                        onerror="this.onerror=null; this.src='../images/default-receipt.jpg'; this.style.cursor='default'; this.onclick=null;">
                                </div>
                                <div class="receipt-actions">
                                    <button type="button" class="receipt-btn view" onclick="viewFullscreenReceipt('${receiptUrl.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-expand"></i> View Fullscreen
                                    </button>
                                    <button type="button" class="receipt-btn download" onclick="downloadReceipt('${receiptUrl.replace(/'/g, "\\'")}', 'receipt_${order.order_number}')">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </div>
                                <div class="verification-notes">
                                    <h4><i class="fas fa-check-circle"></i> Verification Checklist:</h4>
                                    <ul>
                                        <li>Check if the receipt shows the correct order amount ($${parseFloat(order.total_amount).toFixed(2)})</li>
                                        <li>Verify the payment date matches the order date</li>
                                        <li>Check for any signs of tampering or editing</li>
                                        <li>Confirm the payment reference number is visible</li>
                                    </ul>
                                </div>
                            </div>
                        `;
                    } else {
                        receiptSection = `
                            <div class="receipt-section">
                                <h3 style="margin-top: 0;">Payment Receipt</h3>
                                <div class="no-receipt">
                                    <i class="fas fa-receipt"></i>
                                    <p>No payment receipt uploaded for this order.</p>
                                    <p style="font-size: 0.9em; color: #999; margin-top: 10px;">
                                        If this is a Cash on Delivery (COD) order, no receipt is expected.
                                    </p>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Build HTML with appropriate locked notice
                    let lockedNotice = '';
                    if (isCancelled) {
                        lockedNotice = `
                            <div class="cancelled-notice">
                                <i class="fas fa-ban"></i>
                                <p><strong>This order is cancelled and cannot be edited.</strong> All product quantities have been returned to stock.</p>
                            </div>
                        `;
                    } else if (isDelivered) {
                        lockedNotice = `
                            <div class="completed-notice">
                                <i class="fas fa-check-circle"></i>
                                <p><strong>This order has been delivered and cannot be edited.</strong> The order is now complete and closed.</p>
                            </div>
                        `;
                    }
                    
                    let html = `
                        ${lockedNotice}
                        
                        <div class="order-info-grid">
                            <div class="info-card">
                                <h3>Order Information</h3>
                                <div class="info-row">
                                    <div class="label">Order Number:</div>
                                    <div class="value"><strong>#${order.order_number}</strong></div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Date:</div>
                                    <div class="value">${formattedDate}</div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Status:</div>
                                    <div class="value">
                                        <span class="status-badge ${getStatusClass(order.status)}">
                                            ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                            ${isLocked ? `<span class="${isCancelled ? 'warning-badge' : 'completed-badge'}">LOCKED</span>` : ''}
                                        </span>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Payment Method:</div>
                                    <div class="value">${order.payment_method ? order.payment_method.charAt(0).toUpperCase() + order.payment_method.slice(1) : 'N/A'}</div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Total Amount:</div>
                                    <div class="value"><strong>$${parseFloat(order.total_amount).toFixed(2)}</strong></div>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h3>Customer Information</h3>
                                <div class="info-row">
                                    <div class="label">Name:</div>
                                    <div class="value">${order.customer_name || 'N/A'}</div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Email:</div>
                                    <div class="value">${order.email}</div>
                                </div>
                                <div class="info-row">
                                    <div class="label">Phone:</div>
                                    <div class="value">${order.phone_number || 'N/A'}</div>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h3>Shipping Address</h3>
                                <div class="info-row">
                                    <div class="label">Address:</div>
                                    <div class="value">${order.formatted_shipping_address || 'No shipping address provided'}</div>
                                </div>
                            </div>
                        </div>
                        
                        ${receiptSection}
                        
                        <div class="info-card">
                            <h3>Order Items ${totalSavings > 0 ? `<span style="color: #4CAF50; font-size: 0.8em;">(Saved: $${totalSavings.toFixed(2)})</span>` : ''}</h3>
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f9f9f9;">
                                        <td colspan="3" style="text-align: right; font-weight: 600;">Subtotal:</td>
                                        <td style="font-weight: 600;">$${subtotal.toFixed(2)}</td>
                                    </tr>
                                    ${totalSavings > 0 ? `
                                    <tr style="background-color: #f9f9f9;">
                                        <td colspan="3" style="text-align: right; color: #4CAF50; font-weight: 600;">Discount Savings:</td>
                                        <td style="color: #4CAF50; font-weight: 600;">-$${totalSavings.toFixed(2)}</td>
                                    </tr>
                                    ` : ''}
                                    <tr style="background-color: #f9f9f9; border-top: 2px solid #ddd;">
                                        <td colspan="3" style="text-align: right; font-weight: 700; font-size: 1.1em;">Total:</td>
                                        <td style="font-weight: 700; font-size: 1.1em; color: #8B4513;">$${parseFloat(order.total_amount).toFixed(2)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    `;
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = `
                        <div style="text-align:center;padding:40px;color:#f44336;">
                            <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:20px;"></i>
                            <p>Error loading order details. Please try again.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                content.innerHTML = `
                    <div style="text-align:center;padding:40px;color:#f44336;">
                        <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:20px;"></i>
                        <p>Error loading order details. Please try again.</p>
                    </div>
                `;
            }
        }

        // Update order status modal
        function updateOrderStatus(orderId, currentStatus) {
            // Check if order is cancelled or delivered
            if (currentStatus.toLowerCase() === 'cancelled' || currentStatus.toLowerCase() === 'delivered') {
                showLockedWarning(currentStatus);
                return;
            }
            
            document.getElementById('updateOrderId').value = orderId;
            document.getElementById('currentStatusValue').value = currentStatus;
            document.getElementById('updateOrderNumber').textContent = `#${orderId}`;
            
            // Hide locked warning
            document.getElementById('lockedWarning').style.display = 'none';
            document.getElementById('updateFormContent').style.display = 'block';
            
            // Enable form elements
            document.getElementById('newStatus').disabled = false;
            document.getElementById('updateStatusBtn').disabled = false;
            document.getElementById('updateStatusBtn').innerHTML = 'Update Status';
            
            // Set current status display
            document.getElementById('currentStatus').innerHTML = `
                <span class="status-badge ${getStatusClass(currentStatus)}">
                    ${currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)}
                </span>
            `;
            
            // Set new status value
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusForm').action = window.location.href;
            
            // Show modal
            openModal('updateStatusModal');
        }

        function getStatusClass(status) {
            switch(status.toLowerCase()) {
                case 'pending': return 'status-pending';
                case 'processing': return 'status-active';
                case 'delivered': return 'status-delivered';
                case 'cancelled': return 'status-cancelled';
                default: return 'status-pending';
            }
        }

        // Filter orders by status
        function filterOrders() {
            performSearch(); // Use the same search function
        }

        // Export CSV
        function exportOrders() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchBox').value;
            let url = window.location.pathname + '?export=csv';
            
            if (status !== 'all') url += `&status=${status}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            
            window.location.href = url;
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
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

        // Download receipt
        function downloadReceipt(imageUrl, filename) {
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = filename || 'receipt';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Helper function to get basename
        function basename(path) {
            return path.split('/').pop().split('\\').pop();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Close fullscreen modal
            if (event.target.classList.contains('fullscreen-modal')) {
                closeFullscreenModal();
            }
        };

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('viewOrderModal');
                closeModal('updateStatusModal');
                closeFullscreenModal();
            }
        });

        // AJAX form submission for status update
        document.getElementById('statusForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const orderId = document.getElementById('updateOrderId').value;
            const newStatus = document.getElementById('newStatus').value;
            const currentStatus = document.getElementById('currentStatusValue').value;
            
            // Check if order is cancelled or delivered
            if (currentStatus.toLowerCase() === 'cancelled' || currentStatus.toLowerCase() === 'delivered') {
                showNotification('Cannot update status of ' + currentStatus + ' orders', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('order_id', orderId);
                formData.append('status', newStatus);
                formData.append('current_status', currentStatus);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('updateStatusModal');
                    // Refresh the table
                    performSearch();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to update order status', 'error');
            }
        });
    </script>
</body>
</html>