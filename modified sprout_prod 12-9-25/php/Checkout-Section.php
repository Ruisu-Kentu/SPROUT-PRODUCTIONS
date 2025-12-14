<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<script>
        alert("⚠️\\n\\nPlease log in first!");
        window.location.href = "Login-Form.php";
    </script>';
    exit();
}

// Check if user is admin - deny access if they are
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<script>
        alert("⛔ ACCESS DENIED\\n\\nThis page is only for regular users!");
        window.location.href = "admin-dashboard.php";
    </script>';
    exit();
}

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

// Get user ID from session
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Function to get cart count for header - MODIFIED TO COUNT DISTINCT PRODUCTS
function getCartCount($conn, $userId) {
    $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['product_count'] ?? 0;
}

// Get cart count for header display
$cartCount = $userId ? getCartCount($conn, $userId) : 0;

// Fetch user's default delivery address
$userAddress = null;
$hasAddress = false;
$shippingAddressId = null;

// Calculate cart totals - SIMPLIFIED
$cartItems = [];
$cartTotal = 0; // Simple total (already includes discounts)
$hasOutOfStockItems = false;
$itemCount = 0; // This is for quantity total (not product count)
$productCount = 0; // This is for distinct product count

if ($userId) {
    // Get user's default address or most recent address
    $addressQuery = "SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1";
    $stmt = $conn->prepare($addressQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $addressResult = $stmt->get_result();
    
    if ($addressResult->num_rows > 0) {
        $userAddress = $addressResult->fetch_assoc();
        $hasAddress = true;
        $shippingAddressId = $userAddress['id'];
    } else {
        // If no default address, get any address for this user
        $addressQuery2 = "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt2 = $conn->prepare($addressQuery2);
        $stmt2->bind_param("i", $userId);
        $stmt2->execute();
        $addressResult2 = $stmt2->get_result();
        
        if ($addressResult2->num_rows > 0) {
            $userAddress = $addressResult2->fetch_assoc();
            $hasAddress = true;
            $shippingAddressId = $userAddress['id'];
        }
    }
    if (isset($stmt)) $stmt->close();
    
    // Fetch cart items from database with proper discount calculations
    $cartQuery = "SELECT 
        uc.*, 
        p.name, 
        p.price, 
        p.image_path, 
        p.stock,
        p.is_discounted,
        p.discount_percent,
        CASE 
            WHEN p.is_discounted = 1 THEN p.price * (1 - p.discount_percent / 100)
            ELSE p.price
        END as discounted_price,
        CASE 
            WHEN p.is_discounted = 1 THEN uc.quantity * (p.price * (1 - p.discount_percent / 100))
            ELSE uc.quantity * p.price
        END as discounted_item_total
    FROM user_cart uc
    JOIN products p ON uc.product_id = p.id
    WHERE uc.user_id = ?";
    
    $stmt = $conn->prepare($cartQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($item = $result->fetch_assoc()) {
        $cartItems[] = $item;
        $cartTotal += $item['discounted_item_total']; // Just use the discounted total
        $itemCount += $item['quantity'];
        
        // Check if item is out of stock
        if ($item['stock'] < $item['quantity']) {
            $hasOutOfStockItems = true;
        }
    }
    
    $stmt->close();
    
    // Get distinct product count for header badge - NEW
    $productCount = count($cartItems); // This is already distinct products from the query
}

// Check if cart is empty - redirect back to cart if empty
if (empty($cartItems)) {
    echo '<script>
        alert("⚠️\\n\\nYour cart is empty! Please add items to proceed to checkout.");
        window.location.href = "cart-section.php";
    </script>';
    exit();
}

// Simple order total
$shipping = 15; // Fixed shipping
$total = $cartTotal + $shipping; // Just add shipping to cart total

// Generate unique order number
$orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());

// Format currency function
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Handle AJAX request for order processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['ajax_action'] === 'create_order') {
        // Get order data
        $orderNumber = $_POST['order_number'];
        $paymentMethod = $_POST['payment_method'];
        $totalAmount = $_POST['total_amount'];
        $shippingAddressId = isset($_POST['shipping_address_id']) ? $_POST['shipping_address_id'] : null;
        $receiptFilename = null;
        
        // Handle file upload if present
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/receipts/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $fileExtension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $fileName = 'receipt_' . $orderNumber . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filePath)) {
                $receiptFilename = $fileName;
            }
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // 1. Insert order into orders table - SIMPLIFIED (no subtotal columns)
            if ($receiptFilename) {
                $orderQuery = "INSERT INTO orders (order_number, user_id, total_amount, payment_method, shipping_address_id, 
                                      payment_receipt, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($orderQuery);
                $stmt->bind_param("sidsis", $orderNumber, $userId, $totalAmount, $paymentMethod, $shippingAddressId,
                     $receiptFilename);
            } else {
                $orderQuery = "INSERT INTO orders (order_number, user_id, total_amount, payment_method, shipping_address_id, status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($orderQuery);
                $stmt->bind_param("sidsi", $orderNumber, $userId, $totalAmount, $paymentMethod, $shippingAddressId);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }
            
            $orderId = $stmt->insert_id;
            $stmt->close();
            
            // 2. Get cart items and insert into order_items table
            $cartQuery = "SELECT uc.product_id, uc.quantity, p.price, p.name, p.discount_percent, p.is_discounted
                          FROM user_cart uc 
                          JOIN products p ON uc.product_id = p.id 
                          WHERE uc.user_id = ?";
            $stmt = $conn->prepare($cartQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $cartItems = $stmt->get_result();
            $stmt->close();
            
            // Check if order_items table exists, create if not
            $checkTable = "SHOW TABLES LIKE 'order_items'";
            $result = $conn->query($checkTable);
            
            if ($result->num_rows == 0) {
                // Create order_items table
                $createTable = "CREATE TABLE order_items (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    order_id INT NOT NULL,
                    product_id INT NOT NULL,
                    quantity INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    discount_percent DECIMAL(5,2) DEFAULT 0,
                    is_discounted TINYINT(1) DEFAULT 0,
                    FOREIGN KEY (order_id) REFERENCES orders(id),
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )";
                $conn->query($createTable);
            }
            
            // Insert order items
            while($item = $cartItems->fetch_assoc()) {
                $orderItemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price, discount_percent, is_discounted) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($orderItemQuery);
                $stmt->bind_param("iiiddi", $orderId, $item['product_id'], $item['quantity'], 
                                 $item['price'], $item['discount_percent'], $item['is_discounted']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add order item: " . $stmt->error);
                }
                $stmt->close();
                
                // 3. Update product stock
                $updateStockQuery = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stmt = $conn->prepare($updateStockQuery);
                $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update stock: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // 4. Clear user's cart
            $clearCartQuery = "DELETE FROM user_cart WHERE user_id = ?";
            $stmt = $conn->prepare($clearCartQuery);
            $stmt->bind_param("i", $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to clear cart: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = 'Order created successfully';
            $response['order_number'] = $orderNumber;
            $response['order_id'] = $orderId;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $response['message'] = $e->getMessage();
        }
    }
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sprout Productions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <style>
        .changeBtn{
            color: orange;
            text-decoration: none;
            margin-left: 500px;
            font-size: 16px;
            font-weight: bold;  
        }
        .changeBtn:hover{
            color: orangered;
        }

        /* ===== CHECKOUT CONTENT ===== */
        .checkout-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.8rem;
            font-family: 'Georgia', serif;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-family: 'Georgia', serif;
        }

        h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        /* Delivery Address */
        .delivery-address {
            margin-bottom: 3rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
        }

        .delivery-address h1 {
            margin-bottom: 1rem;
        }

        .address-info {
            font-size: 0.875rem;
        }

        .address-line {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .address-line strong {
            min-width: 150px;
            color: #333;
        }

        .address-line span {
            color: #666666;
        }

        .no-address {
            color: #e74c3c;
            font-style: italic;
            margin-bottom: 1rem;
        }

        .change-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .change-btn:hover {
            color: #dc2626;
        }

        /* Layout */
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cart Items */
        .cart-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background-color: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .cart-item-image-container {
            width: 96px;
            height: 96px;
            border-radius: 0.75rem;
            overflow: hidden;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .cart-item-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .no-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 14px;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .cart-item-price {
            font-size: 1.125rem;
            font-weight: 600;
            color: #27ae60;
        }

        .cart-item-quantity {
            font-size: 0.875rem;
            color: #666666;
            margin-top: 0.5rem;
        }

        .out-of-stock-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Payment & Summary */
        .payment-summary {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .payment-method-btn {
            width: 100%;
            padding: 0.875rem 1rem;
            text-align: center;
            border-radius: 0.5rem;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .payment-method-btn:hover {
            background-color: #f3f4f6;
        }

        .payment-method-btn.active {
            background-color: #000;
            color: #fff;
            border: 2px solid #000;
        }

        /* Order Summary */
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .summary-label {
            color: #666666;
        }

        .summary-value {
            font-weight: 500;
        }

        .summary-value.discount {
            color: #27ae60;
        }

        .summary-divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 1rem 0;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.125rem;
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
        }

        .proceed-btn {
            width: 100%;
            padding: 1rem;
            background-color: #000000;
            color: #ffffff;
            border: none;
            border-radius: 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .proceed-btn:hover {
            background-color: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .proceed-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .proceed-btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
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

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Payment Modal Specific Styles */
        .payment-info {
            margin-bottom: 20px;
        }

        .payment-info p {
            margin: 8px 0;
            color: #666;
        }

        .qr-code-container {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .bank-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .bank-detail-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .bank-detail-label {
            width: 150px;
            font-weight: 600;
            color: #333;
        }

        .bank-detail-value {
            flex: 1;
            color: #666;
        }

        .upload-section {
            margin: 20px 0;
            text-align: center;
        }

        .upload-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #000;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .upload-btn:hover {
            background: #333;
        }

        .receipt-preview {
            margin-top: 15px;
        }

        .receipt-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .modal-btn {
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn-primary {
            background: #000;
            color: white;
            border: none;
        }

        .modal-btn-primary:hover {
            background: #333;
        }

        .modal-btn-secondary {
            background: white;
            color: #000;
            border: 2px solid #000;
        }

        .modal-btn-secondary:hover {
            background: #f5f5f5;
        }

        /* Order Confirmation */
        .order-confirmation {
            text-align: center;
            padding: 20px 0;
        }

        .confirmation-icon {
            font-size: 48px;
            color: #27ae60;
            margin-bottom: 20px;
        }

        .order-number {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 10px 0;
        }

        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            text-align: center;
            color: white;
        }

        .loading-spinner i {
            font-size: 48px;
            margin-bottom: 20px;
        }

        /* Icons */
        .icon {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* ===== FOOTER - MATCHING LANDING PAGE ===== */
        .footer {
            background-color: #000;
            color: #fff;
            padding: 40px 20px 20px;
            margin-top: 60px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 30px;
            border-bottom: 1px solid #333;
        }

        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 15px;
            font-family: 'Georgia', serif;
        }

        .footer-description {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .social-icons {
            display: flex;
            gap: 10px;
        }

        .social-icon-fb,
        .social-icon-insta,
        .social-icon-github,
        .social-icon-twitter {
            width: 30px;
            height: 30px;
            background-color: #333;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .social-icon-fb:hover,
        .social-icon-insta:hover,
        .social-icon-github:hover,
        .social-icon-twitter:hover {
            transform: translateY(-3px);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 8px;
        }

        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.2s;
            font-size: 14px;
        }

        .footer-links a:hover {
            color: #fff;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            color: #999;
            font-size: 14px;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding-top: 140px;
            }

            .nav-menu {
                gap: 20px;
            }

            .nav-menu li a {
                font-size: 12px;
            }

            .logo-text {
                font-size: 18px;
            }

            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item-image-container {
                width: 100%;
                height: 200px;
            }

            .changeBtn {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                text-align: center;
                display: block;
            }
            
            .address-line strong {
                min-width: 120px;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .bank-detail-row {
                flex-direction: column;
            }

            .bank-detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Header - Matching Landing Page -->
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
                        <a href="logout.php" class="logout-link-no-icon">Logout</a>
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
                            <li><a href="my-orders.php">My Orders</a></li>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <a href="cart-section.php" class="icon-link">
                                <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                                <!-- CHANGED: Using $cartCount (distinct product count) instead of $itemCount (quantity total) -->
                                <span id="cart-badge" class="icon-badge"><?php echo $cartCount; ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Checkout Content -->
    <div class="checkout-container">
        <!-- Delivery Address -->
        <div class="delivery-address">
            <h1>Delivery Address</h1>
            <div class="address-info">
                <?php if ($hasAddress && $userAddress): ?>
                    <div class="address-line">
                        <strong>Full Name:</strong>
                        <span><?php echo htmlspecialchars($userAddress['full_name']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Contact Number:</strong>
                        <span><?php echo htmlspecialchars($userAddress['phone_number']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Street Address:</strong>
                        <span><?php echo htmlspecialchars($userAddress['street_address']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Barangay:</strong>
                        <span><?php echo htmlspecialchars($userAddress['barangay']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>City/Municipality:</strong>
                        <span><?php echo htmlspecialchars($userAddress['city']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Province:</strong>
                        <span><?php echo htmlspecialchars($userAddress['province']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Region:</strong>
                        <span><?php echo htmlspecialchars($userAddress['region']); ?></span>
                    </div>
                    <div class="address-line">
                        <strong>Postal Code:</strong>
                        <span><?php echo htmlspecialchars($userAddress['postal_code']); ?></span>
                    </div>
                    <?php if (!empty($userAddress['landmark'])): ?>
                    <div class="address-line">
                        <strong>Landmark:</strong>
                        <span><?php echo htmlspecialchars($userAddress['landmark']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-address">No delivery address saved. Please add an address to continue.</p>
                <?php endif; ?>
                
                <a href="../php/Checkout-NewAddress.php" class="changeBtn">
                    <?php echo $hasAddress ? 'Change Address' : 'Add Address'; ?>
                </a>
            </div>
        </div>

        <!-- Main Checkout Grid -->
        <div class="checkout-grid">
            <!-- Left Column - Cart -->
            <div class="cart-section">
                <!-- CHANGED: Showing product count instead of item count -->
                <h2>Your Cart (<?php echo count($cartItems); ?> products)</h2>
                
                <?php foreach ($cartItems as $item): ?>
                    <?php
                    // Determine image path for display
                    $displayImagePath = '';
                    if (!empty($item['image_path'])) {
                        if (strpos($item['image_path'], 'uploads/') === 0) {
                            // It's already a relative path from root
                            $displayImagePath = '../' . $item['image_path'];
                        } else if (strpos($item['image_path'], '../uploads/') === 0) {
                            // It starts with ../uploads/
                            $displayImagePath = $item['image_path'];
                        } else if (strpos($item['image_path'], 'http') === 0) {
                            // It's an absolute URL
                            $displayImagePath = $item['image_path'];
                        } else {
                            // It's a relative path, prepend ../
                            $displayImagePath = '../' . $item['image_path'];
                        }
                    }
                    ?>
                    
                    <div class="cart-item">
                        <div class="cart-item-image-container">
                            <?php if (!empty($displayImagePath)): ?>
                                <img src="<?php echo htmlspecialchars($displayImagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="cart-item-image"
                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image-placeholder\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                            <?php else: ?>
                                <div class="no-image-placeholder">
                                    <i class="fas fa-image"></i> No image
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-details">
                            <h3 class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <?php if ($item['is_discounted'] && $item['discount_percent'] > 0): ?>
                                <p class="cart-item-price">
                                    <span style="font-size: 14px; color: #999; text-decoration: line-through;">
                                        <?php echo formatCurrency($item['price']); ?>
                                    </span>
                                    <span style="margin-left: 8px;">
                                        <?php echo formatCurrency($item['discounted_price']); ?>
                                    </span>
                                    <span style="font-size: 12px; background: #c62828; color: white; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">
                                        -<?php echo $item['discount_percent']; ?>%
                                    </span>
                                </p>
                            <?php else: ?>
                                <p class="cart-item-price"><?php echo formatCurrency($item['price']); ?></p>
                            <?php endif; ?>
                            <p class="cart-item-quantity">Quantity: <?php echo $item['quantity']; ?></p>
                            <p class="cart-item-price">Item Total: <?php echo formatCurrency($item['discounted_item_total']); ?></p>
                            <?php if ($item['stock'] < $item['quantity']): ?>
                                <p class="out-of-stock-message">
                                    <i class="fas fa-exclamation-circle"></i> 
                                    Only <?php echo $item['stock']; ?> in stock (<?php echo $item['quantity'] - $item['stock']; ?> more than available)
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Right Column - Payment & Summary -->
            <div class="payment-summary">
                <!-- Payment Method -->
                <div class="card">
                    <h2>Payment Method</h2>
                    <div class="payment-methods">
                        <button class="payment-method-btn active" data-method="cash" onclick="selectPayment(this)">Cash on Delivery</button>
                        <button class="payment-method-btn" data-method="gcash" onclick="selectPayment(this)">Gcash</button>
                        <button class="payment-method-btn" data-method="bank" onclick="selectPayment(this)">Bank Account</button>
                    </div>
                </div>

                <!-- Order Summary - SIMPLIFIED -->
                <div class="card">
                    <h2>Order Summary</h2>
                    <div class="summary-row">
                        <span class="summary-label">Cart Total</span>
                        <span class="summary-value" id="cart-total"><?php echo formatCurrency($cartTotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Delivery Fee</span>
                        <span class="summary-value">+<?php echo formatCurrency($shipping); ?></span>
                    </div>
                    
                    <hr class="summary-divider">
                    <div class="summary-total">
                        <span>Total Amount</span>
                        <span id="total"><?php echo formatCurrency($total); ?></span>
                    </div>
                    <button class="proceed-btn" id="proceed-btn" <?php echo ($hasOutOfStockItems || !$hasAddress) ? 'disabled' : ''; ?> onclick="openPaymentModal()">
                        PROCEED TO ORDER
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                    <?php if ($hasOutOfStockItems): ?>
                        <p style="color: #e74c3c; font-size: 14px; text-align: center; margin-top: 10px;">
                            <i class="fas fa-exclamation-circle"></i> Some items are out of stock. Please update your cart.
                        </p>
                    <?php endif; ?>
                    <?php if (!$hasAddress): ?>
                        <p style="color: #e74c3c; font-size: 14px; text-align: center; margin-top: 10px;">
                            <i class="fas fa-exclamation-circle"></i> Please add a delivery address to proceed with your order.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modals -->
    
    <!-- Cash on Delivery Modal -->
    <div class="modal-overlay" id="cashModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-money-bill-wave" style="margin-right: 10px;"></i> Cash on Delivery</h2>
                <button class="close-modal" onclick="closeModal('cashModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="payment-info">
                    <p><strong>Order Total:</strong> <?php echo formatCurrency($total); ?></p>
                    <p><strong>Order Number:</strong> <span id="cashOrderNumber"><?php echo $orderNumber; ?></span></p>
                    <p><strong>Payment Method:</strong> Cash on Delivery</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <i class="fas fa-box" style="font-size: 48px; color: #27ae60; margin-bottom: 15px;"></i>
                    <h3>Pay Upon Delivery</h3>
                    <p>You will pay the delivery person when your order arrives.</p>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <p><strong>Note:</strong> Please have exact change ready. Our delivery personnel carry limited change.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('cashModal')">Cancel</button>
                <button class="modal-btn modal-btn-primary" onclick="confirmCashPayment()">Confirm Order</button>
            </div>
        </div>
    </div>

    <!-- GCash Modal -->
    <div class="modal-overlay" id="gcashModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-mobile-alt" style="margin-right: 10px;"></i> GCash Payment</h2>
                <button class="close-modal" onclick="closeModal('gcashModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="payment-info">
                    <p><strong>Order Total:</strong> <?php echo formatCurrency($total); ?></p>
                    <p><strong>Order Number:</strong> <span id="gcashOrderNumber"><?php echo $orderNumber; ?></span></p>
                    <p><strong>Payment Method:</strong> GCash</p>
                </div>
                
                <div class="qr-code-container">
                    <h3>Scan to Pay</h3>
                    <img src="../images/gcash-qr-code.jpg" alt="GCash QR Code" style="width: 200px; height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                    <p>Scan this QR code using your GCash app</p>
                </div>
                
                <div class="bank-details">
                    <h4>Or Send Payment to:</h4>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">GCash Number:</div>
                        <div class="bank-detail-value">0916 415 0439</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Account Name:</div>
                        <div class="bank-detail-value">Benedicto Cuizon</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Amount:</div>
                        <div class="bank-detail-value"><?php echo formatCurrency($total); ?></div>
                    </div>
                </div>
                
                <div class="upload-section">
                    <h4>Upload Payment Receipt</h4>
                    <p>Please upload a screenshot of your GCash payment confirmation</p>
                    <label for="gcashReceipt" class="upload-btn">
                        <i class="fas fa-upload"></i> Upload Receipt
                    </label>
                    <input type="file" id="gcashReceipt" accept="image/*" style="display: none;" onchange="previewReceipt(this, 'gcashPreview')">
                    <div id="gcashPreview" class="receipt-preview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('gcashModal')">Cancel</button>
                <button class="modal-btn modal-btn-primary" onclick="confirmGcashPayment()" id="gcashConfirmBtn" disabled>Confirm Payment</button>
            </div>
        </div>
    </div>

    <!-- Bank Account Modal -->
    <div class="modal-overlay" id="bankModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-university" style="margin-right: 10px;"></i> Bank Transfer</h2>
                <button class="close-modal" onclick="closeModal('bankModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="payment-info">
                    <p><strong>Order Total:</strong> <?php echo formatCurrency($total); ?></p>
                    <p><strong>Order Number:</strong> <span id="bankOrderNumber"><?php echo $orderNumber; ?></span></p>
                    <p><strong>Payment Method:</strong> Bank Transfer</p>
                </div>
                
                <div class="bank-details">
                    <h3>Bank Account Details</h3>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Bank Name:</div>
                        <div class="bank-detail-value">BDO (Banco de Oro)</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Account Name:</div>
                        <div class="bank-detail-value">Sprout Productions Inc.</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Account Number:</div>
                        <div class="bank-detail-value">0012 3456 7890</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Account Type:</div>
                        <div class="bank-detail-value">Current/Savings Account</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Amount:</div>
                        <div class="bank-detail-value"><?php echo formatCurrency($total); ?></div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Branch:</div>
                        <div class="bank-detail-value">Cebu Business Park</div>
                    </div>
                    <div class="bank-detail-row">
                        <div class="bank-detail-label">Swift Code:</div>
                        <div class="bank-detail-value">BNORPHMM</div>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h4>Important Notes:</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Include your Order Number in the transaction reference</li>
                        <li>Bank transfers may take 1-2 business days to process</li>
                        <li>Keep your deposit slip/transaction receipt</li>
                        <li>Orders will be processed after payment confirmation</li>
                    </ul>
                </div>
                
                <div class="upload-section">
                    <h4>Upload Payment Proof</h4>
                    <p>Please upload a photo of your deposit slip or online transfer confirmation</p>
                    <label for="bankReceipt" class="upload-btn">
                        <i class="fas fa-upload"></i> Upload Proof
                    </label>
                    <input type="file" id="bankReceipt" accept="image/*" style="display: none;" onchange="previewReceipt(this, 'bankPreview')">
                    <div id="bankPreview" class="receipt-preview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('bankModal')">Cancel</button>
                <button class="modal-btn modal-btn-primary" onclick="confirmBankPayment()" id="bankConfirmBtn" disabled>Confirm Payment</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-body">
                <div class="order-confirmation">
                    <div class="confirmation-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Order Confirmed!</h2>
                    <p>Your order has been successfully placed.</p>
                    <div class="order-number">
                        Order Number: <strong id="confirmedOrderNumber"><?php echo $orderNumber; ?></strong>
                    </div>
                    <p style="margin: 20px 0;">We've sent a confirmation email to your registered email address.</p>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p><strong>Next Steps:</strong></p>
                        <ul style="text-align: left; margin: 10px 0;">
                            <li>Track your order in "My Orders" section</li>
                            <li>Delivery usually takes 3-5 business days</li>
                            <li>Contact support if you have any questions</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-primary" onclick="goToOrders()">View My Orders</button>
                <button class="modal-btn modal-btn-secondary" onclick="goToHome()">Continue Shopping</button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Processing your order...</p>
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
        // Payment method selection
        function selectPayment(button) {
            // Remove active class from all payment buttons
            document.querySelectorAll('.payment-method-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Add active class to clicked button
            button.classList.add('active');
        }

        // Open payment modal based on selected method
        function openPaymentModal() {
            const selectedPayment = document.querySelector('.payment-method-btn.active').dataset.method;
            
            if (selectedPayment === 'cash') {
                document.getElementById('cashModal').style.display = 'flex';
            } else if (selectedPayment === 'gcash') {
                document.getElementById('gcashModal').style.display = 'flex';
                // Reset file input
                document.getElementById('gcashReceipt').value = '';
                document.getElementById('gcashPreview').innerHTML = '';
                document.getElementById('gcashConfirmBtn').disabled = true;
            } else if (selectedPayment === 'bank') {
                document.getElementById('bankModal').style.display = 'flex';
                // Reset file input
                document.getElementById('bankReceipt').value = '';
                document.getElementById('bankPreview').innerHTML = '';
                document.getElementById('bankConfirmBtn').disabled = true;
            }
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Preview receipt/image
        function previewReceipt(input, previewId) {
            const preview = document.getElementById(previewId);
            const confirmBtn = input.id === 'gcashReceipt' ? 
                document.getElementById('gcashConfirmBtn') : 
                document.getElementById('bankConfirmBtn');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Receipt Preview">`;
                    confirmBtn.disabled = false;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Confirm Cash Payment
        function confirmCashPayment() {
            const orderData = {
                ajax_action: 'create_order',
                order_number: document.getElementById('cashOrderNumber').textContent,
                payment_method: 'Cash on Delivery',
                total_amount: <?php echo $total; ?>,
                shipping_address_id: <?php echo $shippingAddressId ?: 'null'; ?>,
                user_id: <?php echo $userId; ?>
            };
            
            processPayment(orderData);
        }

        // Confirm GCash Payment
        function confirmGcashPayment() {
            const receiptInput = document.getElementById('gcashReceipt');
            if (!receiptInput.files[0]) {
                alert('Please upload your payment receipt first.');
                return;
            }
            
            const orderData = {
                ajax_action: 'create_order',
                order_number: document.getElementById('gcashOrderNumber').textContent,
                payment_method: 'GCash',
                total_amount: <?php echo $total; ?>,
                shipping_address_id: <?php echo $shippingAddressId ?: 'null'; ?>,
                user_id: <?php echo $userId; ?>
            };
            
            processPayment(orderData, receiptInput.files[0]);
        }

        // Confirm Bank Payment
        function confirmBankPayment() {
            const receiptInput = document.getElementById('bankReceipt');
            if (!receiptInput.files[0]) {
                alert('Please upload your payment proof first.');
                return;
            }
            
            const orderData = {
                ajax_action: 'create_order',
                order_number: document.getElementById('bankOrderNumber').textContent,
                payment_method: 'Bank Account',
                total_amount: <?php echo $total; ?>,
                shipping_address_id: <?php echo $shippingAddressId ?: 'null'; ?>,
                user_id: <?php echo $userId; ?>
            };
            
            processPayment(orderData, receiptInput.files[0]);
        }

        // Process payment and save to database
        function processPayment(orderData, receiptFile = null) {
            // Close current modal
            const currentModal = document.querySelector('.modal-overlay[style*="flex"]');
            if (currentModal) {
                currentModal.style.display = 'none';
            }
            
            // Show loading
            showLoading();
            
            // Create form data for file upload
            const formData = new FormData();
            formData.append('ajax_action', 'create_order');
            formData.append('order_number', orderData.order_number);
            formData.append('payment_method', orderData.payment_method);
            formData.append('total_amount', orderData.total_amount);
            formData.append('shipping_address_id', orderData.shipping_address_id);
            
            if (receiptFile) {
                formData.append('receipt', receiptFile);
            }
            
            // Send AJAX request to same page
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    // Show success modal
                    document.getElementById('confirmedOrderNumber').textContent = orderData.order_number;
                    document.getElementById('successModal').style.display = 'flex';
                    
                    // Update cart badge to 0 (cart is cleared)
                    document.getElementById('cart-badge').textContent = '0';
                    
                    // Disable proceed button
                    document.getElementById('proceed-btn').disabled = true;
                } else {
                    alert('Error: ' + data.message);
                    // Re-open the modal if there was an error
                    openPaymentModal();
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                // Re-open the modal if there was an error
                openPaymentModal();
            });
        }

        // Navigation functions
        function goToOrders() {
            window.location.href = 'my-orders.php';
        }

        function goToHome() {
            window.location.href = 'Landing-Page-Section.php';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }

        // Escape key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal-overlay');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Close icon functionality
        document.querySelector('.close-icon').addEventListener('click', function() {
            window.location.href = 'Landing-Page-Section.php';
        });

        // Initialize confirm buttons as disabled
        document.getElementById('gcashConfirmBtn').disabled = true;
        document.getElementById('bankConfirmBtn').disabled = true;
    </script>
</body>
</html>