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
        window.location.href = "admin-dashboard.php"; // Redirect to admin dashboard
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

// Function to get cart count (COUNT OF DISTINCT PRODUCTS)
function getCartCount($conn, $userId) {
    $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['product_count'] ?? 0;
}

// Function to check if cart has items
function hasCartItems($conn, $userId) {
    $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return ($data['product_count'] ?? 0) > 0;
}

// Get cart items count for the button
$cartHasItems = $userId ? hasCartItems($conn, $userId) : false;

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
                p.is_discounted,
                p.discount_percent,
                (uc.quantity * p.price) as original_item_total,
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
            
            $cartItems = [];
            $originalSubtotal = 0;
            $discountedSubtotal = 0;
            $totalItemCount = 0;
            
            while($item = $result->fetch_assoc()) {
                $cartItems[] = $item;
                $originalSubtotal += $item['original_item_total'];
                $discountedSubtotal += $item['discounted_item_total'];
                $totalItemCount += $item['quantity'];
            }
            
            // Calculate discounts - NO ADDITIONAL 20% DISCOUNT
            $productDiscounts = $originalSubtotal - $discountedSubtotal; // Discounts from individual product discounts only
            $totalDiscount = $productDiscounts; // No additional discount
            
            // Final calculations
            $shipping = 15; // Fixed shipping
            $total = $discountedSubtotal + $shipping; // No subtraction of additional discount
            
            echo json_encode([
                'success' => true,
                'cart' => $cartItems,
                'original_subtotal' => $originalSubtotal,
                'discounted_subtotal' => $discountedSubtotal,
                'product_discounts' => $productDiscounts,
                'total_discount' => $totalDiscount,
                'shipping' => $shipping,
                'total' => $total,
                'product_count' => count($cartItems), // Number of distinct products
                'total_items' => $totalItemCount, // Total quantity of all items
                'has_items' => !empty($cartItems)
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
                        'cart_count' => getCartCount($conn, $userId), // Returns count of distinct products
                        'has_items' => hasCartItems($conn, $userId)
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
                    'cart_count' => getCartCount($conn, $userId), // Returns count of distinct products
                    'has_items' => hasCartItems($conn, $userId)
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

// Get cart count for header display (COUNT OF DISTINCT PRODUCTS)
$cartCount = $userId ? getCartCount($conn, $userId) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Sprout Productions</title>
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Cart Page Specific Styles */
        .cart-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .cart-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #000;
            font-family: 'Georgia', serif;
            text-align: center;
            letter-spacing: 2px;
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
            margin-left: 130px;
            width: 900px;
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
        
        /* Cart item image styling */
        .cart-item-image {
            flex: 0 0 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cart-item-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }
        
        .cart-item:hover .cart-item-image img {
            transform: scale(1.05);
        }
        
        /* Default image styling */
        .default-cart-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #666;
            font-size: 12px;
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
        
        /* Price styling */
        .price-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0;
        }
        
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        
        .current-price {
            font-size: 16px;
            font-weight: 600;
            color: #27ae60;
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
        
        /* Discount badge for cart items */
        .cart-discount-badge {
            background: linear-gradient(135deg, #c62828 0%, #d32f2f 100%);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
            text-transform: uppercase;
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
            top: 140px;
        }
        
        .order-summary h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #000;
            font-family: 'Georgia', serif;
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
        
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .notification-success {
            background: #27ae60;
            color: white;
        }
        
        .notification-error {
            background: #e74c3c;
            color: white;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            margin-left: 15px;
            font-size: 16px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Cart badge animation */
        .icon-badge.updated {
            transform: scale(1.2);
            background-color: #27ae60;
        }
        
        /* New summary styles */
        .summary-details {
            margin-bottom: 10px;
        }
        
        .summary-breakdown {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 12px;
        }
        
        .breakdown-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        /* Cart info badge */
        .cart-info-badge {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Fixed Header (Same as New Arrivals) -->
    <header class="sticky-header">
        <!-- Top Bar -->
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
                    <div class="logo">
                        <a href="Landing-Page-Section.php" class="logo-link">
                            <img src="../images/sprout logo bg-removed 3.png" alt="Sprout Logo" class="logo-img">
                            <span class="logo-text">SPROUT PRODUCTIONS</span>
                        </a>
                    </div>

                    <nav class="center-nav">
                        <ul class="nav-menu">
                            <li><a href="New-Arrival-Section.php">New Arrivals</a></li>
                            <li><a href="Best-Sellers-Section.php">Best Sellers</a></li>
                            <li><a href="Limited-Time-Offers.php">Special Offers</a></li>
                            <li><a href="my-orders.php">My Orders</a></li>
                        </ul>
                    </nav>

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
        <h1 class="cart-title">YOUR SHOPPING CART</h1>
        
        <div class="cart-grid">
            <!-- Cart Items Section -->
            <div class="cart-items-section" id="cart-items-container">
                <div class="loading">Loading cart items...</div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary" id="order-summary" style="display: none;">
                <h2>Order Summary</h2>
                
                <!-- Cart info badge showing number of distinct products -->
                <div id="cart-info-badge" class="cart-info-badge" style="display: none;">
                    <i class="fas fa-shopping-cart"></i> <span id="product-count">0</span> distinct product(s) in cart
                </div>

                <div class="summary-lines">
                    <div class="summary-line">
                        <span class="label">Original Subtotal</span>
                        <span class="value" id="summary-original-subtotal">₱0</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">Product Discounts</span>
                        <span class="value discount" id="summary-product-discounts">-₱0</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">Discounted Subtotal</span>
                        <span class="value" id="summary-discounted-subtotal">₱0</span>
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
                
                <!-- Discount breakdown -->
                <div class="summary-breakdown" id="discount-breakdown" style="display: none;">
                    <div class="breakdown-line">
                        <span>Original Subtotal:</span>
                        <span id="breakdown-original">₱0</span>
                    </div>
                    <div class="breakdown-line">
                        <span>Product Discounts:</span>
                        <span style="color: #27ae60;" id="breakdown-product-discount">-₱0</span>
                    </div>
                    <div class="breakdown-line">
                        <span>Discounted Subtotal:</span>
                        <span id="breakdown-discounted">₱0</span>
                    </div>
                    <div class="breakdown-line">
                        <span>Additional 20% Off:</span>
                        <span style="color: #27ae60;" id="breakdown-additional">-₱0</span>
                    </div>
                    <div class="breakdown-line">
                        <span>Delivery Fee:</span>
                        <span>+₱15</span>
                    </div>
                    <div class="breakdown-line" style="font-weight: bold;">
                        <span>Final Total:</span>
                        <span id="breakdown-final">₱0</span>
                    </div>
                </div>

                <!-- UPDATED CHECKOUT BUTTON -->
                <button class="checkout-btn" id="checkout-btn" <?php echo !$cartHasItems ? 'disabled' : ''; ?>>
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
        const cartInfoBadge = document.getElementById('cart-info-badge');
        const productCountElement = document.getElementById('product-count');
        
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
            cartInfoBadge.style.display = 'none';
        }
        
        // Update cart display
        function updateCartDisplay(data) {
            const cart = data.cart || [];
            
            // Update cart badge with product count (distinct products)
            cartBadge.textContent = data.product_count || 0;
            
            // Update cart info badge
            if (data.product_count > 0) {
                productCountElement.textContent = data.product_count;
                cartInfoBadge.style.display = 'block';
            } else {
                cartInfoBadge.style.display = 'none';
            }
            
            // Update cart items display
            if (cart.length === 0) {
                showEmptyCart();
                // Disable checkout button when cart is empty
                checkoutBtn.disabled = true;
                return;
            }
            
            let cartHTML = '';
            let hasOutOfStockItems = false;
            
            cart.forEach(item => {
                const isOutOfStock = item.stock < item.quantity;
                if (isOutOfStock) hasOutOfStockItems = true;
                
                // Calculate prices with discounts
                const isDiscounted = item.is_discounted == 1;
                const discountPercent = item.discount_percent || 0;
                const originalPrice = parseFloat(item.price);
                const discountedPrice = isDiscounted ? originalPrice * (1 - discountPercent / 100) : originalPrice;
                const itemTotal = isDiscounted ? item.discounted_item_total : item.original_item_total;
                
                // Get image path
                const imagePath = item.image_path || '';
                let displayPath = '';
                
                if (imagePath) {
                    if (imagePath.startsWith('uploads/')) {
                        displayPath = '../' + imagePath;
                    } else if (imagePath.startsWith('../uploads/')) {
                        displayPath = imagePath;
                    } else if (imagePath.startsWith('http')) {
                        displayPath = imagePath;
                    } else {
                        displayPath = '../' + imagePath;
                    }
                }
                
                cartHTML += `
                    <div class="cart-item" data-product-id="${item.product_id}">
                        <div class="cart-item-image">
                            ${imagePath ? 
                                `<img src="${displayPath}" 
                                      alt="${item.name}"
                                      onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'default-cart-image\\'><i class=\\'fas fa-image\\'></i> No image</div>';">
                                ` : 
                                `<div class="default-cart-image">
                                    <i class="fas fa-image"></i> No image
                                </div>`
                            }
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-header">
                                <div>
                                    <h3 class="cart-item-name">${item.name}
                                        ${isDiscounted && discountPercent > 0 ? 
                                            `<span class="cart-discount-badge">-${discountPercent}% OFF</span>` : ''
                                        }
                                    </h3>
                                    <div class="price-container">
                                        ${isDiscounted && discountPercent > 0 ? 
                                            `<span class="original-price">${formatCurrency(originalPrice)}</span>
                                             <span class="current-price">${formatCurrency(discountedPrice)}</span>` :
                                            `<span class="current-price">${formatCurrency(originalPrice)}</span>`
                                        }
                                    </div>
                                </div>
                                <button class="delete-btn" onclick="removeFromCart(${item.product_id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
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
                            ${formatCurrency(itemTotal)}
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
            // Update main summary lines
            document.getElementById('summary-original-subtotal').textContent = formatCurrency(data.original_subtotal);
            document.getElementById('summary-product-discounts').textContent = formatCurrency(-data.product_discounts);
            document.getElementById('summary-discounted-subtotal').textContent = formatCurrency(data.discounted_subtotal);
            document.getElementById('summary-shipping').textContent = formatCurrency(data.shipping);
            document.getElementById('summary-total').textContent = formatCurrency(data.total);
            
            // Update breakdown
            document.getElementById('breakdown-original').textContent = formatCurrency(data.original_subtotal);
            document.getElementById('breakdown-product-discount').textContent = formatCurrency(-data.product_discounts);
            document.getElementById('breakdown-discounted').textContent = formatCurrency(data.discounted_subtotal);
            document.getElementById('breakdown-final').textContent = formatCurrency(data.total);
            
            // Show breakdown if there are discounts
            const discountBreakdown = document.getElementById('discount-breakdown');
            if (data.total_discount > 0) {
                discountBreakdown.style.display = 'block';
            } else {
                discountBreakdown.style.display = 'none';
            }
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
                    // Update cart badge
                    cartBadge.textContent = data.cart_count || 0;
                    // Show notification
                    showNotification('Cart updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error updating quantity', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                showNotification('Error updating quantity. Please try again.', 'error');
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
                    // Update cart badge
                    cartBadge.textContent = data.cart_count || 0;
                    showNotification('Item removed from cart', 'success');
                } else {
                    showNotification('Error removing item', 'error');
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                showNotification('Error removing item. Please try again.', 'error');
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
                    // Update cart badge
                    cartBadge.textContent = 0;
                    showNotification('Cart cleared successfully', 'success');
                } else {
                    showNotification('Error clearing cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing cart:', error);
                showNotification('Error clearing cart. Please try again.', 'error');
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
                            showNotification('Some items in your cart are out of stock. Please update quantities before checkout.', 'error');
                            return;
                        }
                        
                        // Proceed to checkout-section.php
                        window.location.href = 'checkout-section.php';
                    }
                });
            } else {
                // Show message when disabled button is clicked
                showNotification('Your cart is empty or contains out-of-stock items. Please add items to proceed to checkout.', 'error');
            }
        });
        
        // Notification function
        function showNotification(message, type = 'success') {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Initialize cart on page load
        loadCart();
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>