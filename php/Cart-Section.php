<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin (prevent admin from accessing cart)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: Admin-Dashboard.php");
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

// Function to get cart count
function getCartCount($conn, $userId) {
    $countQuery = "SELECT SUM(quantity) as total_items FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['total_items'] ?? 0;
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_cart':
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
                exit();
            }
            
            $cartQuery = "SELECT 
                uc.*, 
                p.name, 
                p.price, 
                p.image_path, 
                p.stock,
                (uc.quantity * p.price) as item_total
            FROM user_cart uc
            JOIN products p ON uc.product_id = p.id
            WHERE uc.user_id = ?";
            
            $stmt = $conn->prepare($cartQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $cartItems = [];
            $subtotal = 0;
            
            while($item = $result->fetch_assoc()) {
                $cartItems[] = $item;
                $subtotal += $item['item_total'];
            }
            
            $discount = $subtotal * 0.20; // 20% discount
            $shipping = 15; // Fixed shipping
            $total = $subtotal - $discount + $shipping;
            
            echo json_encode([
                'success' => true,
                'cart' => $cartItems,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => $shipping,
                'total' => $total,
                'item_count' => count($cartItems)
            ]);
            exit();
            
        case 'update_quantity':
            $productId = $_POST['product_id'];
            $quantityChange = (int)$_POST['quantity_change'];
            
            // Get current quantity
            $checkQuery = "SELECT quantity FROM user_cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ii", $userId, $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $current = $result->fetch_assoc();
                $newQuantity = $current['quantity'] + $quantityChange;
                
                if ($newQuantity <= 0) {
                    // Remove item
                    $deleteQuery = "DELETE FROM user_cart WHERE user_id = ? AND product_id = ?";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("ii", $userId, $productId);
                } else {
                    // Check product stock
                    $stockQuery = "SELECT stock FROM products WHERE id = ?";
                    $stmt = $conn->prepare($stockQuery);
                    $stmt->bind_param("i", $productId);
                    $stmt->execute();
                    $stockResult = $stmt->get_result();
                    $stockData = $stockResult->fetch_assoc();
                    
                    if ($newQuantity > $stockData['stock']) {
                        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                        exit();
                    }
                    
                    // Update quantity
                    $updateQuery = "UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("iii", $newQuantity, $userId, $productId);
                }
                
                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'cart_count' => getCartCount($conn, $userId)
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
                }
            }
            exit();
            
        case 'remove_item':
            $productId = $_POST['product_id'];
            
            $deleteQuery = "DELETE FROM user_cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("ii", $userId, $productId);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'cart_count' => getCartCount($conn, $userId)
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
            }
            exit();
            
        case 'clear_cart':
            $deleteQuery = "DELETE FROM user_cart WHERE user_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
            }
            exit();
    }
}

// Get cart count for header display
$cartCount = $userId ? getCartCount($conn, $userId) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Sprout Productions</title>
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Import landing page header styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        
        /* Fixed Header from Landing Page */
        .sticky-header {
            position: sticky;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .top-bar {
            background: #000;
            color: #fff;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .top-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info-with-icon {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-icon-small {
            width: 16px;
            height: 16px;
            filter: invert(1);
            opacity: 0.8;
        }
        
        .welcome-text {
            opacity: 0.8;
        }
        
        .user-email {
            font-weight: 500;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-link-no-icon {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            padding: 6px 15px;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-size: 13px;
            font-weight: 500;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.3);
        }
        
        .logout-link-no-icon:hover {
            background: linear-gradient(135deg, #ff6b5c 0%, #e74c3c 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        
        .close-icon {
            width: 16px;
            height: 16px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .close-icon:hover {
            opacity: 1;
        }
        
        .main-navigation {
            background: #fff;
            padding: 15px 0;
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #000;
            gap: 10px;
        }
        
        .logo-img {
            height: 40px;
            width: auto;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .center-nav {
            flex: 1;
            display: flex;
            justify-content: center;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 40px;
        }
        
        .nav-menu li {
            position: relative;
        }
        
        .nav-menu a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 16px;
            padding: 8px 0;
            transition: color 0.3s;
        }
        
        .nav-menu a:hover {
            color: #27ae60;
        }
        
        .right-nav {
            display: flex;
            align-items: center;
        }
        
        .action-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .icon-link {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            transition: transform 0.3s;
        }
        
        .icon-link:hover .nav-icon {
            transform: scale(1.1);
        }
        
        .icon-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            font-size: 11px;
            font-weight: bold;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        
        /* Cart Page Styles */
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .cart-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #000;
        }
        
        .cart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 900px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cart Items Section */
        .cart-items-section {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            gap: 20px;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            flex: 0 0 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .cart-item-name {
            font-size: 18px;
            font-weight: 600;
            color: #000;
            margin-bottom: 5px;
        }
        
        .delete-btn {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .delete-btn:hover {
            color: #c0392b;
        }
        
        .cart-item-price {
            font-size: 16px;
            color: #27ae60;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .quantity-btn:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: #27ae60;
            color: #27ae60;
        }
        
        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-value {
            font-size: 16px;
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }
        
        .cart-item-total {
            font-size: 18px;
            font-weight: 700;
            color: #000;
            margin-left: auto;
            min-width: 100px;
            text-align: right;
        }
        
        .out-of-stock-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Empty Cart State */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-cart p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .continue-shopping-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .continue-shopping-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        /* Order Summary */
        .order-summary {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 120px;
        }
        
        .order-summary h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #000;
        }
        
        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-line .label {
            color: #666;
        }
        
        .summary-line .value {
            font-weight: 600;
        }
        
        .summary-line .discount {
            color: #27ae60;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #000;
            font-size: 18px;
        }
        
        .summary-total .label {
            font-weight: 700;
        }
        
        .summary-total .value {
            font-weight: 700;
            color: #27ae60;
            font-size: 24px;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 16px;
            background: #000;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .checkout-btn:hover:not(:disabled) {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .clear-cart-btn {
            width: 100%;
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
        }
        
        .clear-cart-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        
        /* Footer */
        .footer {
            background: #000;
            color: #fff;
            padding: 50px 0 20px;
            margin-top: 60px;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }
        
        @media (max-width: 900px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
        
        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #fff;
        }
        
        .footer-description {
            color: #aaa;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #aaa;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #fff;
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
        }
        
        .social-icon-fb,
        .social-icon-insta,
        .social-icon-github,
        .social-icon-twitter {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #333;
            transition: background 0.3s;
        }
        
        .social-icon-fb:hover { background: #3b5998; }
        .social-icon-insta:hover { background: #e4405f; }
        .social-icon-github:hover { background: #333; }
        .social-icon-twitter:hover { background: #1da1f2; }
        
        .footer-bottom {
            max-width: 1200px;
            margin: 40px auto 0;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #333;
            color: #aaa;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Fixed Header (Same as Landing Page) -->
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
                            <li><a href="Limited-Time-Offers.php">Limited-Time Offers</a></li>
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

    <!-- Cart Content -->
    <div class="cart-container">
        <h1 class="cart-title">Your Shopping Cart</h1>
        
        <div class="cart-grid">
            <!-- Cart Items Section -->
            <div class="cart-items-section" id="cart-items-container">
                <div class="loading">Loading cart items...</div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary" id="order-summary" style="display: none;">
                <h2>Order Summary</h2>

                <div class="summary-lines">
                    <div class="summary-line">
                        <span class="label">Subtotal</span>
                        <span class="value" id="summary-subtotal">₱0</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">Discount</span>
                        <span class="value discount" id="summary-discount">-₱0</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">Delivery Fee</span>
                        <span class="value" id="summary-shipping">₱15</span>
                    </div>
                </div>

                <div class="summary-total">
                    <span class="label">Total</span>
                    <span class="value" id="summary-total">₱0</span>
                </div>

                <button class="checkout-btn" id="checkout-btn" disabled>
                    Proceed to Checkout
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
                
                <button class="clear-cart-btn" id="clear-cart-btn">
                    Clear Entire Cart
                </button>
            </div>
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
        const cartItemsContainer = document.getElementById('cart-items-container');
        const orderSummary = document.getElementById('order-summary');
        const cartBadge = document.getElementById('cart-badge');
        const checkoutBtn = document.getElementById('checkout-btn');
        const clearCartBtn = document.getElementById('clear-cart-btn');
        
        // Currency formatter
        const formatCurrency = (amount) => {
            return '₱' + parseFloat(amount).toFixed(2);
        };
        
        // Load cart from server
        function loadCart() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_cart'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartDisplay(data);
                } else {
                    showEmptyCart();
                }
            })
            .catch(error => {
                console.error('Error loading cart:', error);
                showEmptyCart();
            });
        }
        
        // Show empty cart state
        function showEmptyCart() {
            cartItemsContainer.innerHTML = `
                <div class="empty-cart">
                    <div class="empty-cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="Landing-Page-Section.php" class="continue-shopping-btn">
                        Continue Shopping
                    </a>
                </div>
            `;
            orderSummary.style.display = 'none';
        }
        
        // Update cart display
        function updateCartDisplay(data) {
            const cart = data.cart || [];
            
            // Update cart badge
            cartBadge.textContent = data.item_count || 0;
            
            // Update cart items display
            if (cart.length === 0) {
                showEmptyCart();
                return;
            }
            
            let cartHTML = '';
            let hasOutOfStockItems = false;
            
            cart.forEach(item => {
                const isOutOfStock = item.stock < item.quantity;
                if (isOutOfStock) hasOutOfStockItems = true;
                
                cartHTML += `
                    <div class="cart-item" data-product-id="${item.product_id}">
                        <div class="cart-item-image">
                            <img src="${item.image_path || '../images/default-product.jpg'}" alt="${item.name}">
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-header">
                                <h3 class="cart-item-name">${item.name}</h3>
                                <button class="delete-btn" onclick="removeFromCart(${item.product_id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <p class="cart-item-price">${formatCurrency(item.price)}</p>
                            ${isOutOfStock ? 
                                `<p class="out-of-stock-message">
                                    <i class="fas fa-exclamation-circle"></i> 
                                    Only ${item.stock} in stock (${item.quantity - item.stock} more than available)
                                </p>` : ''
                            }
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-value">${item.quantity}</span>
                                <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, 1)" ${item.quantity >= item.stock ? 'disabled' : ''}>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="cart-item-total">
                            ${formatCurrency(item.item_total)}
                        </div>
                    </div>
                `;
            });
            
            cartItemsContainer.innerHTML = cartHTML;
            
            // Update order summary
            updateOrderSummary(data);
            
            // Show order summary
            orderSummary.style.display = 'block';
            
            // Enable/disable checkout button
            checkoutBtn.disabled = hasOutOfStockItems || cart.length === 0;
        }
        
        // Update order summary
        function updateOrderSummary(data) {
            document.getElementById('summary-subtotal').textContent = formatCurrency(data.subtotal);
            document.getElementById('summary-discount').textContent = formatCurrency(-data.discount);
            document.getElementById('summary-shipping').textContent = formatCurrency(data.shipping);
            document.getElementById('summary-total').textContent = formatCurrency(data.total);
        }
        
        // Update quantity function
        window.updateQuantity = function(productId, quantityChange) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity_change=${quantityChange}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCart();
                } else {
                    alert(data.message || 'Error updating quantity');
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                alert('Error updating quantity. Please try again.');
            });
        };
        
        // Remove from cart function
        window.removeFromCart = function(productId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_item&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCart();
                } else {
                    alert('Error removing item');
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                alert('Error removing item. Please try again.');
            });
        };
        
        // Clear cart function
        clearCartBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_cart'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCart();
                    alert('Cart cleared successfully');
                } else {
                    alert('Error clearing cart');
                }
            })
            .catch(error => {
                console.error('Error clearing cart:', error);
                alert('Error clearing cart. Please try again.');
            });
        });
        
        // Checkout button handler
        checkoutBtn.addEventListener('click', function() {
            if (!this.disabled) {
                // Check if any items are out of stock
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_cart'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const hasOutOfStockItems = data.cart.some(item => item.stock < item.quantity);
                        
                        if (hasOutOfStockItems) {
                            alert('Some items in your cart are out of stock. Please update quantities before checkout.');
                            return;
                        }
                        
                        // Proceed to checkout
                        alert('Proceeding to checkout...');
                        // window.location.href = 'checkout.php'; // Uncomment when you have checkout page
                    }
                });
            }
        });
        
        // Initialize cart on page load
        loadCart();
    });
    </script>
</body>
</html>