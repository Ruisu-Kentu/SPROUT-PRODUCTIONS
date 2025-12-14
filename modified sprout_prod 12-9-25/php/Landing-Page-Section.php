<?php
session_start();

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

// Check if user is logged in
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isUser = isset($_SESSION['role']) && $_SESSION['role'] === 'user';

// If user is logged in, check if they're admin - deny access if they are
if ($isLoggedIn && $isAdmin) {
    echo '<script>
        alert("⛔ ACCESS DENIED\\n\\nThis page is only for regular users!");
        window.location.href = "admin-dashboard.php"; // Redirect to admin dashboard
    </script>';
    exit();
}

// Fetch new arrivals (latest 4 products)
$newArrivalsQuery = "SELECT * FROM products ORDER BY created_at DESC LIMIT 4";
$newArrivalsResult = $conn->query($newArrivalsQuery);

// Fetch best sellers - FIXED: Only show products with sold_count > 0
$bestSellersQuery = "SELECT * FROM products WHERE sold_count > 0 ORDER BY sold_count DESC LIMIT 4";
$bestSellersResult = $conn->query($bestSellersQuery);

// Fetch special offers - discounted products
$specialOffersQuery = "SELECT * FROM products WHERE is_discounted = 1 AND discount_percent > 0 ORDER BY discount_percent DESC LIMIT 4";
$specialOffersResult = $conn->query($specialOffersQuery);

// Fetch ALL reviews with user information (using LEFT JOIN to include all reviews even if product info is missing)
$reviewsQuery = "SELECT 
                    sr.*, 
                    u.email,
                    p.name as product_name,
                    p.image_path as product_image
                 FROM sproutReviews sr
                 LEFT JOIN users u ON sr.user_id = u.id
                 LEFT JOIN products p ON sr.product_id = p.id
                 ORDER BY sr.created_at DESC";
$reviewsResult = $conn->query($reviewsQuery);

// Store all reviews in an array
$reviews = [];
if ($reviewsResult && $reviewsResult->num_rows > 0) {
    while($review = $reviewsResult->fetch_assoc()) {
        $reviews[] = $review;
    }
}

// Handle AJAX requests for cart operations (only if logged in as user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Only allow cart operations if user is logged in
    if (!$isLoggedIn || !$isUser) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please login as a user first']);
        exit();
    }
    
    switch ($action) {
        case 'add_to_cart':
            header('Content-Type: application/json');
            
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }
            
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product']);
                exit();
            }
            
            // Check if product exists and has stock
            $checkProductQuery = "SELECT * FROM products WHERE id = ?";
            $stmt = $conn->prepare($checkProductQuery);
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $productResult = $stmt->get_result();
            
            if ($productResult->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            $product = $productResult->fetch_assoc();
            
            // Check stock
            if ($product['stock'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Product out of stock']);
                exit();
            }
            
            // Check if product already in cart
            $checkCartQuery = "SELECT * FROM user_cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($checkCartQuery);
            $stmt->bind_param("ii", $userId, $productId);
            $stmt->execute();
            $cartResult = $stmt->get_result();
            
            if ($cartResult->num_rows > 0) {
                // Update quantity
                $updateQuery = "UPDATE user_cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("ii", $userId, $productId);
            } else {
                // Insert new item
                $insertQuery = "INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ii", $userId, $productId);
            }
            
            if ($stmt->execute()) {
                // Get updated cart count - NUMBER OF DISTINCT PRODUCTS (not sum of quantities)
                $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
                $stmt = $conn->prepare($countQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $countResult = $stmt->get_result();
                $countData = $countResult->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added to cart',
                    'item_count' => $countData['product_count'] ?? 0 // Changed to product_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
            }
            exit();
            
        case 'get_cart_count':
            header('Content-Type: application/json');
            
            if (!$isLoggedIn || !$isUser) {
                echo json_encode(['success' => false, 'item_count' => 0]);
                exit();
            }
            
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if ($userId) {
                // Get cart count - NUMBER OF DISTINCT PRODUCTS (not sum of quantities)
                $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
                $stmt = $conn->prepare($countQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $countResult = $stmt->get_result();
                $countData = $countResult->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'item_count' => $countData['product_count'] ?? 0 // Changed to product_count
                ]);
            } else {
                echo json_encode(['success' => false, 'item_count' => 0]);
            }
            exit();
    }
}

// Get cart count for display - only if logged in as user
$cartCount = 0;
if ($isLoggedIn && $isUser) {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    if ($userId) {
        $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $countResult = $stmt->get_result();
        $countData = $countResult->fetch_assoc();
        $cartCount = $countData['product_count'] ?? 0;
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprout Productions - E-commerce</title>
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        /* User Info with User Icon */
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
        
        /* Login/Register buttons */
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        
        .login-link, .register-link {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            border: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .login-link {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .register-link {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        
        .login-link:hover {
            background: linear-gradient(135deg, #4aa3df 0%, #3498db 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .register-link:hover {
            background: linear-gradient(135deg, #48d68c 0%, #2ecc71 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
        }
        
        /* Logout button without icon */
        .logout-link-no-icon {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.3);
            position: relative;
            overflow: hidden;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .logout-link-no-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .logout-link-no-icon:hover::before {
            left: 100%;
        }
        
        .logout-link-no-icon:hover {
            background: linear-gradient(135deg, #ff6b5c 0%, #e74c3c 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        
        .logout-link-no-icon:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        /* Navigation menu styles */
        .nav-menu {
            list-style: none;
            display: flex;
            gap: 40px;
            margin: 0;
            padding: 0;
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
            position: relative;
            transition: color 0.3s ease;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .nav-menu a:hover {
            color: #000;
        }
        
        /* Underline effect on hover */
        .nav-menu a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: #000;
            transition: width 0.3s ease;
        }
        
        .nav-menu a:hover::after {
            width: 100%;
        }
        
        /* Active link styling */
        .nav-menu a.active {
            color: #000;
            font-weight: 600;
        }
        
        .nav-menu a.active::after {
            width: 100%;
        }
        
        /* Disabled menu items for non-logged in users */
        .nav-menu a.disabled-link {
            color: #999;
            cursor: not-allowed;
        }
        
        .nav-menu a.disabled-link:hover::after {
            width: 0;
        }
        
        /* Product grid responsiveness */
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Empty state message */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            grid-column: 1 / -1;
        }
        
        .empty-state p {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        /* Product card enhancements */
        .product-image {
            position: relative;
            overflow: hidden;
            height: 250px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .product-image a {
            display: block;
            width: 100%;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .product-image img {
            transition: transform 0.3s ease;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #27ae60;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 2;
        }
        
        .out-of-stock {
            background: #e74c3c;
        }
        
        .add-to-cart-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: #000;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .add-to-cart-btn:hover {
            background: #333;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .add-to-cart-btn.login-required {
            background: #666;
        }
        
        .add-to-cart-btn.login-required:hover {
            background: #777;
            cursor: pointer;
        }
        
        /* Cart badge animation */
        .icon-badge {
            transition: all 0.3s ease;
        }
        
        .icon-badge.updated {
            transform: scale(1.2);
            background-color: #27ae60;
        }
        
        /* Fix for broken image display */
        .default-product-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #666;
            font-size: 14px;
        }
        
        /* Discount badge for landing page */
        .discount-badge-landing {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #c62828 0%, #d32f2f 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            text-transform: uppercase;
        }
        
        /* Special offers badge */
        .special-offer-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #ff4444 0%, #ff6b6b 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            text-transform: uppercase;
        }
        
        /* Best seller badge */
        .best-seller-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            text-transform: uppercase;
        }
        
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
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }
        
        /* Special offers price styling */
        .special-offers .current-price {
            color: #ff4444;
        }
        
        .special-offers .add-to-cart-btn {
            background: #ff4444;
        }
        
        .special-offers .add-to-cart-btn:hover {
            background: #e63939;
        }
        
        /* View More Button */
        .view-more-btn {
            margin-top: 10px;
            padding: 10px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .view-more-btn:hover {
            background: #34495e;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(44, 62, 80, 0.2);
        }
        
        .view-more-btn.login-required {
            background: #666;
        }
        
        .view-more-btn.login-required:hover {
            background: #777;
            cursor: pointer;
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
        
        .notification-warning {
            background: #f39c12;
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
        
        .product-name-link {
            color: #333;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .product-name-link:hover {
            color: #000;
        }
        
        /* Sold count for best sellers */
        .sold-count {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-weight: 600;
        }
        
        /* Section headers */
        .section-title {
            font-family: 'Georgia', serif;
            font-size: 32px;
            color: #000;
            margin: 40px 0 30px;
            text-align: center;
        }
        
        /* Welcome message styles */
        .welcome-section {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: white;
        }
        
        .welcome-text {
            font-weight: 500;
        }
        
        .user-email {
            font-weight: 600;
            color: #f1c40f;
        }
        
        .guest-welcome {
            font-size: 14px;
            color: white;
            font-weight: 500;
        }
        
        /* Disabled cart icon */
        .icon-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .icon-link.disabled:hover {
            transform: none;
        }
        
        .guest-message {
            color: #f1c40f;
            font-weight: 600;
            font-size: 14px;
        }

        .view-all a.login-required-link {
            display: inline-block;
            background: #666;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }

        .view-all a.login-required-link:hover {
            background: #777;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Style for regular View All link */
        .view-all a:not(.login-required-link) {
            display: inline-block;
            background: #000;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }

        .view-all a:not(.login-required-link):hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Updated Review Styles */
        .customer-reviews {
            padding: 60px 20px;
            background: #f9f9f9;
            margin-top: 60px;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .reviews-navigation {
            display: flex;
            gap: 15px;
        }
        
        .nav-arrow {
            width: 40px;
            height: 40px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            color: #333;
            font-size: 18px;
        }
        
        .nav-arrow:hover {
            background: #000;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }
        
        .review-slider {
            display: flex;
            transition: transform 0.5s ease;
            width: 100%;
        }
        
        .review-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            min-width: 300px;
            flex-shrink: 0;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .review-stars {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .star-filled {
            color: #FFD700;
            font-size: 20px;
        }
        
        .star-filled::before {
            content: "★";
        }
        
        .reviewer-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .verified-badge {
            width: 16px;
            height: 16px;
            background: #27ae60;
            border-radius: 50%;
            position: relative;
        }
        
        .verified-badge::after {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
            font-weight: bold;
        }
        
        .review-text {
            color: #666;
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .review-date {
            font-size: 12px;
            color: #999;
            margin-top: 15px;
        }
        
        .review-product {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .product-thumbnail {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .product-name {
            font-size: 13px;
            color: #666;
            max-width: 200px;
        }
        
        .review-empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
        }
        
        .review-empty-state p {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .write-review-btn {
            display: inline-block;
            background: #27ae60;
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .write-review-btn:hover {
            background: #2ecc71;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.3);
        }
        
        .reviewer-email {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
            font-style: normal;
        }
        
        /* Review slider navigation */
        .nav-arrow.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .nav-arrow.disabled:hover {
            background: #fff;
            color: #333;
            transform: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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
                    <?php if ($isLoggedIn && $isUser): ?>
                        <div class="user-info-with-icon">
                            <img src="../images/user_logo.png" alt="User" class="user-icon-small">
                            <span class="welcome-text">Welcome,</span>
                            <span class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?> (User)</span>
                        </div>
                    <?php else: ?>
                        <div class="guest-welcome">
                            <i class="fas fa-user" style="margin-right: 8px;"></i>
                            <span class="guest-message">Welcome Guest! Please login to access all features.</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="top-bar-actions">
                        <?php if ($isLoggedIn && $isUser): ?>
                            <a href="logout.php" class="logout-link-no-icon">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        <?php else: ?>
                            <div class="auth-buttons">
                                <a href="Login-Form.php" class="login-link">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                                <a href="Register-Form.php" class="register-link">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                            </div>
                        <?php endif; ?>
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
                            <?php if ($isLoggedIn && $isUser): ?>
                                <li><a href="my-orders.php">My Orders</a></li>
                            <?php else: ?>
                                <li><a href="Login-Form.php" class="disabled-link">My Orders</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <?php if ($isLoggedIn && $isUser): ?>
                                <!-- Cart icon for logged in users -->
                                <a href="cart-section.php" class="icon-link">
                                    <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                                    <span id="cart-badge" class="icon-badge"><?php echo $cartCount; ?></span>
                                </a>
                            <?php else: ?>
                                <!-- Disabled cart icon for guests -->
                                <a href="Login-Form.php" class="icon-link disabled" title="Login to access cart">
                                    <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                                    <span id="cart-badge" class="icon-badge">0</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- New Arrivals Section -->
    <section class="new-arrivals">
        <h2 class="section-title">NEW ARRIVALS</h2>
        
        <div class="products-grid">
            <?php if ($newArrivalsResult && $newArrivalsResult->num_rows > 0): ?>
                <?php while($product = $newArrivalsResult->fetch_assoc()): ?>
                    <?php
                    $isDiscounted = $product['is_discounted'] == 1;
                    $discountPercent = $product['discount_percent'];
                    $originalPrice = $product['price'];
                    $discountPrice = $originalPrice * (1 - $discountPercent / 100);
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php 
                            // Check if image path exists and is valid
                            $imagePath = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : '';
                            
                            if (!empty($imagePath)) {
                                // Check if path starts with 'uploads/' or '../uploads/'
                                if (strpos($imagePath, 'uploads/') === 0) {
                                    // It's already a relative path from root
                                    $displayPath = '../' . $imagePath;
                                } else if (strpos($imagePath, '../uploads/') === 0) {
                                    // It starts with ../uploads/
                                    $displayPath = $imagePath;
                                } else if (strpos($imagePath, 'http') === 0) {
                                    // It's an absolute URL
                                    $displayPath = $imagePath;
                                } else {
                                    // It's a relative path, prepend ../
                                    $displayPath = '../' . $imagePath;
                                }
                                ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <img src="<?php echo $displayPath; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'default-product-image\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                                </a>
                            <?php } else { ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <div class="default-product-image">
                                        <i class="fas fa-image"></i> No image
                                    </div>
                                </a>
                            <?php } ?>
                            
                            <?php if ($isDiscounted && $discountPercent > 0): ?>
                                <div class="discount-badge-landing">-<?php echo $discountPercent; ?>% OFF</div>
                            <?php endif; ?>
                            
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-badge">In Stock (<?php echo $product['stock']; ?>)</span>
                            <?php else: ?>
                                <span class="stock-badge out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   class="product-name-link"
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="price-container">
                                <?php if ($isDiscounted && $discountPercent > 0): ?>
                                    <span class="original-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                    <span class="current-price">₱<?php echo number_format($discountPrice, 2); ?></span>
                                <?php else: ?>
                                    <span class="current-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isLoggedIn && $isUser): ?>
                                <button class="add-to-cart-btn" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                    <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                                </button>
                            <?php else: ?>
                                <button class="add-to-cart-btn login-required" 
                                        onclick="showLoginRequired()">
                                    <i class="fas fa-sign-in-alt"></i> Login to Add to Cart
                                </button>
                            <?php endif; ?>
                            
                            <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>">
                                <button class="view-more-btn <?php echo ($isLoggedIn && $isUser) ? '' : 'login-required'; ?>">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo ($isLoggedIn && $isUser) ? 'View Details' : 'Login to View Details'; ?></span>
                                </button>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No new arrivals available at the moment.</p>
                    <p>Check back soon for new products!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="view-all">
            <?php if ($isLoggedIn && $isUser): ?>
                <a href="New-Arrival-Section.php">View All</a>
            <?php else: ?>
                <a href="Login-Form.php" class="login-required-link">Login to View All</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Best Sellers Section - FIXED -->
    <section class="best-sellers">
        <h2 class="section-title">BEST SELLERS</h2>
        
        <div class="products-grid">
            <?php if ($bestSellersResult && $bestSellersResult->num_rows > 0): ?>
                <?php while($product = $bestSellersResult->fetch_assoc()): ?>
                    <?php
                    $isDiscounted = $product['is_discounted'] == 1;
                    $discountPercent = $product['discount_percent'];
                    $originalPrice = $product['price'];
                    $discountPrice = $originalPrice * (1 - $discountPercent / 100);
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php 
                            // Check if image path exists and is valid
                            $imagePath = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : '';
                            
                            if (!empty($imagePath)) {
                                // Check if path starts with 'uploads/' or '../uploads/'
                                if (strpos($imagePath, 'uploads/') === 0) {
                                    // It's already a relative path from root
                                    $displayPath = '../' . $imagePath;
                                } else if (strpos($imagePath, '../uploads/') === 0) {
                                    // It starts with ../uploads/
                                    $displayPath = $imagePath;
                                } else if (strpos($imagePath, 'http') === 0) {
                                    // It's an absolute URL
                                    $displayPath = $imagePath;
                                } else {
                                    // It's a relative path, prepend ../
                                    $displayPath = '../' . $imagePath;
                                }
                                ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <img src="<?php echo $displayPath; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'default-product-image\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                                </a>
                            <?php } else { ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <div class="default-product-image">
                                        <i class="fas fa-image"></i> No image
                                    </div>
                                </a>
                            <?php } ?>
                            
                            <!-- Best Seller Badge -->
                            <div class="best-seller-badge">BEST SELLER</div>
                            
                            <?php if ($isDiscounted && $discountPercent > 0): ?>
                                <div class="discount-badge-landing">-<?php echo $discountPercent; ?>% OFF</div>
                            <?php endif; ?>
                            
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-badge">In Stock (<?php echo $product['stock']; ?>)</span>
                            <?php else: ?>
                                <span class="stock-badge out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   class="product-name-link"
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="price-container">
                                <?php if ($isDiscounted && $discountPercent > 0): ?>
                                    <span class="original-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                    <span class="current-price">₱<?php echo number_format($discountPrice, 2); ?></span>
                                <?php else: ?>
                                    <span class="current-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($product['sold_count'] > 0): ?>
                                <div class="sold-count"><?php echo $product['sold_count']; ?> sold</div>
                            <?php endif; ?>
                            <?php if ($isLoggedIn && $isUser): ?>
                                <button class="add-to-cart-btn" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                    <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                                </button>
                            <?php else: ?>
                                <button class="add-to-cart-btn login-required" 
                                        onclick="showLoginRequired()">
                                    <i class="fas fa-sign-in-alt"></i> Login to Add to Cart
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>">
                                <button class="view-more-btn <?php echo ($isLoggedIn && $isUser) ? '' : 'login-required'; ?>">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo ($isLoggedIn && $isUser) ? 'View Details' : 'Login to View Details'; ?></span>
                                </button>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No best sellers available yet.</p>
                    <p>Be the first to buy and create our best sellers list!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="view-all">
            <?php if ($isLoggedIn && $isUser): ?>
                <a href="Best-Sellers-Section.php">View All</a>
            <?php else: ?>
                <a href="Login-Form.php" class="login-required-link">Login to View All</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Special Offers Section -->
    <section class="special-offers">
        <h2 class="section-title">SPECIAL OFFERS</h2>
        
        <div class="products-grid">
            <?php if ($specialOffersResult && $specialOffersResult->num_rows > 0): ?>
                <?php while($product = $specialOffersResult->fetch_assoc()): ?>
                    <?php
                    $isDiscounted = $product['is_discounted'] == 1;
                    $discountPercent = $product['discount_percent'];
                    $originalPrice = $product['price'];
                    $discountPrice = $originalPrice * (1 - $discountPercent / 100);
                    // Calculate savings
                    $savings = $originalPrice - $discountPrice;
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php 
                            // Check if image path exists and is valid
                            $imagePath = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : '';
                            
                            if (!empty($imagePath)) {
                                // Check if path starts with 'uploads/' or '../uploads/'
                                if (strpos($imagePath, 'uploads/') === 0) {
                                    // It's already a relative path from root
                                    $displayPath = '../' . $imagePath;
                                } else if (strpos($imagePath, '../uploads/') === 0) {
                                    // It starts with ../uploads/
                                    $displayPath = $imagePath;
                                } else if (strpos($imagePath, 'http') === 0) {
                                    // It's an absolute URL
                                    $displayPath = $imagePath;
                                } else {
                                    // It's a relative path, prepend ../
                                    $displayPath = '../' . $imagePath;
                                }
                                ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <img src="<?php echo $displayPath; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'default-product-image\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                                </a>
                            <?php } else { ?>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <div class="default-product-image">
                                        <i class="fas fa-image"></i> No image
                                    </div>
                                </a>
                            <?php } ?>
                            
                            <!-- Special Offer Badge -->
                            <div class="special-offer-badge">-<?php echo $discountPercent; ?>% OFF</div>
                            
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-badge">In Stock (<?php echo $product['stock']; ?>)</span>
                            <?php else: ?>
                                <span class="stock-badge out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3>
                                <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>" 
                                   class="product-name-link"
                                   title="<?php echo ($isLoggedIn && $isUser) ? 'View Product Details' : 'Login to view details'; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="price-container">
                                <span class="original-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                <span class="current-price">₱<?php echo number_format($discountPrice, 2); ?></span>
                                <small style="color: #27ae60; font-weight: bold;">
                                    Save ₱<?php echo number_format($savings, 2); ?>
                                </small>
                            </div>
                            <?php if ($isLoggedIn && $isUser): ?>
                                <button class="add-to-cart-btn" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-product-price="<?php echo $discountPrice; ?>"
                                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                    <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                                </button>
                            <?php else: ?>
                                <button class="add-to-cart-btn login-required" 
                                        onclick="showLoginRequired()">
                                    <i class="fas fa-sign-in-alt"></i> Login to Add to Cart
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo ($isLoggedIn && $isUser) ? 'product-details.php?id=' . $product['id'] : 'Login-Form.php'; ?>">
                                <button class="view-more-btn <?php echo ($isLoggedIn && $isUser) ? '' : 'login-required'; ?>">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo ($isLoggedIn && $isUser) ? 'View Details' : 'Login to View Details'; ?></span>
                                </button>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No special offers available at the moment.</p>
                    <p>Check back soon for amazing discounts!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="view-all">
            <?php if ($isLoggedIn && $isUser): ?>
                <a href="Limited-Time-Offers.php">View All</a>
            <?php else: ?>
                <a href="Login-Form.php" class="login-required-link">Login to View All</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Customer Reviews Section - DYNAMIC WITH SLIDER -->
    <section class="customer-reviews">
        <div class="reviews-header">
            <h2 class="section-title" style="margin-bottom: 0;">CUSTOMER REVIEWS</h2>
        </div>

        <div class="reviews-grid" id="reviews-container">
            <?php if (!empty($reviews)): ?>
                <?php foreach($reviews as $index => $review): ?>
                    <div class="review-card">
                        <div class="review-stars">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="star-filled" style="<?php echo $i > $review['rating'] ? 'color: #ddd;' : ''; ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="reviewer-name">
                            <?php 
                            // Extract username from email (part before @)
                            $email = !empty($review['email']) ? $review['email'] : 'Anonymous User';
                            $emailParts = explode('@', $email);
                            $username = $emailParts[0];
                            echo htmlspecialchars(ucfirst($username));
                            ?>
                            <div class="verified-badge"></div>
                        </div>
                        <?php if (!empty($review['email'])): ?>
                            <div class="reviewer-email">
                                <?php echo htmlspecialchars($review['email']); ?>
                            </div>
                        <?php endif; ?>
                        <p class="review-text">"<?php echo htmlspecialchars($review['review_text']); ?>"</p>
                        
                        <?php if (!empty($review['product_name'])): ?>
                        <div class="review-product">
                            <div class="product-thumbnail">
                                <?php 
                                $productImagePath = !empty($review['product_image']) ? htmlspecialchars($review['product_image']) : '';
                                if (!empty($productImagePath)) {
                                    $displayPath = strpos($productImagePath, 'uploads/') === 0 ? '../' . $productImagePath : $productImagePath;
                                    ?>
                                    <img src="<?php echo $displayPath; ?>" alt="<?php echo htmlspecialchars($review['product_name']); ?>">
                                <?php } else { ?>
                                    <i class="fas fa-image" style="color: #ccc;"></i>
                                <?php } ?>
                            </div>
                            <div class="product-name">
                                <?php echo htmlspecialchars($review['product_name']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="review-date">
                            <?php 
                            $reviewDate = new DateTime($review['created_at']);
                            echo $reviewDate->format('F j, Y');
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="review-empty-state">
                    <p>No reviews yet.</p>
                    <p>Be the first to share your experience!</p>
                    <?php if ($isLoggedIn && $isUser): ?>
                        <a href="#" class="write-review-btn">Write a Review</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

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

    <!-- JavaScript for Add to Cart functionality and Review Slider -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn:not(.login-required)');
        const cartBadge = document.getElementById('cart-badge');
        
        // Function to update cart badge
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
                    cartBadge.textContent = data.item_count;
                    cartBadge.classList.add('updated');
                    setTimeout(() => {
                        cartBadge.classList.remove('updated');
                    }, 300);
                }
            })
            .catch(error => {
                console.error('Error updating cart badge:', error);
            });
        }
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent triggering the image click
                if (this.disabled) return;
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const productPrice = this.dataset.productPrice;
                
                addToCart(productId, productName, productPrice, this);
            });
        });
        
        function addToCart(productId, productName, productPrice, button = null) {
            fetch('landing-page-section.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_to_cart&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cartBadge.textContent = data.item_count;
                    cartBadge.classList.add('updated');
                    setTimeout(() => {
                        cartBadge.classList.remove('updated');
                    }, 300);
                    
                    // Update button on card
                    if (button) {
                        const originalText = button.textContent;
                        const originalBackground = button.style.background;
                        button.textContent = '✓ Added!';
                        button.style.background = '#27ae60';
                        button.style.color = 'white';
                        
                        setTimeout(() => {
                            button.textContent = originalText;
                            button.style.background = originalBackground;
                            button.style.color = '';
                        }, 2000);
                    }
                    
                    // Show success notification
                    showNotification('Product added to cart successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showNotification('Error adding to cart. Please try again.', 'error');
            });
        }
        
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
        
        // Function to show login required message
        window.showLoginRequired = function() {
            showNotification('Please login first to access this feature!', 'warning');
        }
        
        // Initialize cart badge on page load (only if user is logged in)
        <?php if ($isLoggedIn && $isUser): ?>
            updateCartBadge();
        <?php endif; ?>
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>