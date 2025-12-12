<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin (prevent admin from accessing user pages)
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

// Update session time on activity
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

// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    header("Location: Landing-Page-Section.php");
    exit();
}

// Fetch product details
$productQuery = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($productQuery);
$stmt->bind_param("i", $productId);
$stmt->execute();
$productResult = $stmt->get_result();

if ($productResult->num_rows === 0) {
    header("Location: Landing-Page-Section.php");
    exit();
}

$product = $productResult->fetch_assoc();

// Get cart count
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$cartCount = 0;

if ($userId) {
    $countQuery = "SELECT SUM(quantity) as total_items FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $countData = $countResult->fetch_assoc();
    $cartCount = $countData['total_items'] ?? 0;
}

// Fetch related products (same category, excluding current product)
$category = $product['category'];
$relatedQuery = "SELECT * FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 4";
$stmt = $conn->prepare($relatedQuery);
$stmt->bind_param("si", $category, $productId);
$stmt->execute();
$relatedResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Sprout Productions</title>
    <link rel="stylesheet" href="../css/prod-det-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        /* Custom styles to match your design */
        .header-top {
            background: #000;
            color: #fff;
            padding: 10px 0;
            text-align: center;
            font-size: 14px;
        }
        
        .header-top a {
            color: #fff;
            text-decoration: underline;
            margin-left: 5px;
        }
        
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
        }
        
        .logo a {
            color: #000;
            text-decoration: none;
        }
        
        .logo img {
            width: 40px;
            height: 40px;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 30px;
            margin: 0;
            padding: 0;
        }
        
        .nav-menu a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-menu a:hover {
            color: #000;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            background: #f5f5f5;
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .search-bar img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        
        .search-bar input {
            border: none;
            background: none;
            outline: none;
            width: 200px;
        }
        
        .header-icons {
            display: flex;
            gap: 15px;
        }
        
        .icon-placeholder {
            cursor: pointer;
        }
        
        .icon-placeholder img {
            width: 24px;
            height: 24px;
        }
        
        .breadcrumb {
            padding: 20px;
            background: #f9f9f9;
            font-size: 14px;
            color: #666;
        }
        
        /* Product Container */
        .product-container {
            display: grid;
            grid-template-columns: 80px 1fr 400px;
            gap: 40px;
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .thumbnails {
                order: 2;
                display: flex;
                flex-direction: row !important;
                justify-content: center;
                gap: 15px;
            }
            
            .thumbnails .thumbnail {
                width: 80px;
                height: 80px;
            }
            
            .main-image {
                order: 1;
            }
            
            .product-info {
                order: 3;
            }
        }
        
        .thumbnails {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            overflow: hidden;
            transition: border-color 0.3s;
        }
        
        .thumbnail.active {
            border-color: #000;
        }
        
        .thumbnail .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .main-image {
            text-align: center;
        }
        
        .image-placeholder-large {
            max-width: 600px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .image-placeholder-large img {
            width: 100%;
            height: auto;
            max-height: 600px;
            object-fit: contain;
        }
        
        .product-info h1 {
            font-size: 32px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stars {
            color: #ffd700;
            font-size: 20px;
        }
        
        .star {
            display: inline-block;
        }
        
        .star.filled {
            color: #ffd700;
        }
        
        .star.half {
            position: relative;
            color: #ddd;
        }
        
        .star.half:before {
            content: '★';
            position: absolute;
            left: 0;
            width: 50%;
            overflow: hidden;
            color: #ffd700;
        }
        
        .rating-number {
            color: #666;
            font-size: 14px;
        }
        
        .price-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .price-current {
            font-size: 32px;
            font-weight: bold;
            color: #000;
        }
        
        .price-original {
            font-size: 20px;
            color: #999;
            text-decoration: line-through;
        }
        
        .discount-badge {
            background: #c62828;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .size-section {
            margin-bottom: 30px;
        }
        
        .size-section label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #333;
        }
        
        .size-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .size-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 60px;
            text-align: center;
        }
        
        .size-btn:hover {
            border-color: #999;
        }
        
        .size-btn.active {
            border-color: #000;
            background: #000;
            color: white;
        }
        
        .cart-section {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 30px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .qty-btn {
            padding: 10px 15px;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s;
        }
        
        .qty-btn:hover {
            background: #e9e9e9;
        }
        
        .qty-number {
            padding: 0 20px;
            font-size: 16px;
            font-weight: 500;
        }
        
        .add-to-cart-btn {
            flex: 1;
            padding: 15px;
            background: #000;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .add-to-cart-btn:hover {
            background: #333;
        }
        
        .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Tabs */
        .tabs-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
        }
        
        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            position: relative;
        }
        
        .tab.active {
            color: #000;
        }
        
        .tab.active:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #000;
        }
        
        /* Details Container */
        .details-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        @media (max-width: 768px) {
            .details-container {
                grid-template-columns: 1fr;
            }
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .info-section p, .info-section li {
            color: #666;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        .info-section ul {
            padding-left: 20px;
        }
        
        .info-section li {
            margin-bottom: 5px;
        }
        
        /* Recommendations */
        .recommendations {
            max-width: 1400px;
            margin: 60px auto;
            padding: 0 40px;
        }
        
        .recommendations h2 {
            font-size: 24px;
            margin-bottom: 30px;
            color: #333;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 640px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image-placeholder {
            width: 100%;
            height: 200px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .product-card h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
            height: 40px;
            overflow: hidden;
        }
        
        .card-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .rating-text {
            font-size: 12px;
            color: #666;
        }
        
        .card-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .card-price .current {
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }
        
        .card-price .original {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        
        .card-price .discount {
            background: #c62828;
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .view-more-btn {
            width: 100%;
            padding: 10px;
            background: #000;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .view-more-btn:hover {
            background: #333;
        }
        
        .btn-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: url('../images/eye-icon.png') no-repeat center;
            background-size: contain;
        }
        
        /* Footer */
        .footer {
            background: #000;
            color: white;
            padding: 60px 40px 30px;
            margin-top: 60px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
        
        .footer-column h3 {
            font-size: 16px;
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-description {
            color: #ccc;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 14px;
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
            background: #333;
            border-radius: 50%;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-bottom {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #333;
            color: #ccc;
            font-size: 14px;
        }
        
        /* Notification */
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
        
        /* Back to products link */
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: #000;
        }
        
        /* Stock status */
        .stock-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .stock-in {
            background: #27ae60;
            color: white;
        }
        
        .stock-low {
            background: #f39c12;
            color: white;
        }
        
        .stock-out {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header Top -->
    <div class="header-top">
        Sign up and get 20% off your first order. <a href="../php/Register-Form.php">Sign Up Now</a>
        <img src="../images/close_logo.png" alt="">
    </div>

    <!-- Main Header -->
    <header class="header-main">
        <div class="logo">
            <a href="../php/Landing-Page-Section.php">SPROUT PRODUCTIONS</a>
            <img src="../images/sprout logo bg-removed 3.png" alt="">
        </div>

        <nav>
            <ul class="nav-menu">
                <li><a href="New-Arrival-Section.php">New Arrivals</a></li>
                <li><a href="Best-Sellers-Section.php">Best Sellers</a></li>
                <li><a href="Limited-Time-Offers.php">Limited-Time Offers</a></li>
                <li><a href="my-orders.php">My Orders</a></li>
            </ul>
        </nav>

        <div class="header-right">
            <div class="search-bar">
                <img src="../images/Search_logo.png" alt="">
                <input type="text" placeholder="Search for products...">
            </div>
            <div class="header-icons">
                <a href="cart-section.php" class="icon-placeholder">
                    <img src="../images/cart_logo.png" alt="">
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: #c62828; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 10px;"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                <div class="icon-placeholder">
                    <img src="../images/user_logo.png" alt="">
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="Landing-Page-Section.php" class="back-link">← Back to Products</a>
        <span>Product Details</span>
    </div>

    <!-- Main Product Section -->
    <div class="product-container">
        <!-- Left: Thumbnails -->
        <div class="thumbnails">
            <div class="thumbnail active">
                <div class="image-placeholder">
                    <?php if (!empty($product['image_path'])): 
                        $imagePath = $product['image_path'];
                        if (strpos($imagePath, 'uploads/') === 0) {
                            $displayPath = '../' . $imagePath;
                        } else {
                            $displayPath = '../' . $imagePath;
                        }
                    ?>
                        <img src="<?php echo $displayPath; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #666;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Add more thumbnails as needed -->
        </div>

        <!-- Center: Main Product Image -->
        <div class="main-image">
            <div class="image-placeholder-large">
                <?php if (!empty($product['image_path'])): 
                    $imagePath = $product['image_path'];
                    if (strpos($imagePath, 'uploads/') === 0) {
                        $displayPath = '../' . $imagePath;
                    } else {
                        $displayPath = '../' . $imagePath;
                    }
                ?>
                    <img src="<?php echo $displayPath; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainProductImage">
                <?php else: ?>
                    <div style="width: 100%; height: 400px; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #666; font-size: 16px;">
                        <i class="fas fa-image fa-3x"></i>
                        <div style="margin-left: 10px;">No image available</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Product Info -->
        <div class="product-info">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <?php
            $isDiscounted = $product['is_discounted'] == 1;
            $discountPercent = $product['discount_percent'];
            $originalPrice = $product['price'];
            $discountPrice = $originalPrice * (1 - $discountPercent / 100);
            $stock = $product['stock'];
            ?>
            
            <!-- Price -->
            <div class="price-section">
                <?php if ($isDiscounted && $discountPercent > 0): ?>
                    <span class="price-current">$<?php echo number_format($discountPrice, 2); ?></span>
                    <span class="price-original">$<?php echo number_format($originalPrice, 2); ?></span>
                    <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                <?php else: ?>
                    <span class="price-current">$<?php echo number_format($originalPrice, 2); ?></span>
                <?php endif; ?>
                <?php if ($stock > 0): ?>
                    <span class="stock-status <?php echo $stock > 10 ? 'stock-in' : 'stock-low'; ?>">
                        <?php echo $stock > 10 ? 'In Stock' : 'Low Stock (' . $stock . ' left)'; ?>
                    </span>
                <?php else: ?>
                    <span class="stock-status stock-out">Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <p class="description">
                <?php echo !empty($product['description']) ? htmlspecialchars($product['description']) : 'No description available for this product.'; ?>
            </p>

            <!-- Size Selector -->
            <div class="size-section">
                <label>Choose Size</label>
                <div class="size-buttons">
                    <button class="size-btn">Small</button>
                    <button class="size-btn">Medium</button>
                    <button class="size-btn active">Large</button>
                    <button class="size-btn">X-Large</button>
                </div>
            </div>

            <!-- Quantity & Add to Cart -->
            <div class="cart-section">
                <div class="quantity-selector">
                    <button class="qty-btn" id="decreaseQty">-</button>
                    <span class="qty-number" id="quantity">1</span>
                    <button class="qty-btn" id="increaseQty">+</button>
                </div>
                <button class="add-to-cart-btn" 
                        id="addToCartBtn"
                        data-product-id="<?php echo $product['id']; ?>"
                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                        data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                        <?php echo ($stock <= 0) ? 'disabled' : ''; ?>>
                    <?php echo ($stock > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab active">Product Details</button>
        </div>
    </div>

    <!-- Product Details Content -->
    <div class="details-container">
        <!-- Left Column -->
        <div class="details-left">
            <!-- Product Information -->
            <section class="info-section">
                <h3>Product Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($product['name']); ?></p>
                <p><strong>Category:</strong> <?php echo ucfirst(htmlspecialchars($product['category'])); ?></p>
                <p><strong>Product ID:</strong> PROD-<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Added:</strong> <?php echo date('F j, Y', strtotime($product['created_at'])); ?></p>
            </section>

            <!-- Features -->
            <section class="info-section">
                <h3>Features</h3>
                <ul>
                    <li>High-quality materials</li>
                    <li>Premium construction</li>
                    <li>Comfortable fit</li>
                    <li>Durable design</li>
                    <li>Easy to care for</li>
                </ul>
            </section>

            <!-- Size & Fit -->
            <section class="info-section">
                <h3>Size & Fit</h3>
                <p>True-to-size fit</p>
                <p>Designed for everyday casual wear</p>
                <p>Unisex sizing suitable for both men & women</p>
            </section>
        </div>

        <!-- Right Column -->
        <div class="details-right">
            <!-- Care Instructions -->
            <section class="info-section">
                <h3>Care Instructions</h3>
                <p>Machine wash cold</p>
                <p>Do not bleach</p>
                <p>Tumble dry low</p>
                <p>Iron on low heat if needed</p>
            </section>

            <!-- Shipping -->
            <section class="info-section">
                <h3>Shipping</h3>
                <p>Ships in 1-3 business days</p>
                <p>Free shipping for orders over $50</p>
                <p>Standard shipping: 3-7 business days</p>
            </section>

            <!-- Returns / Exchange -->
            <section class="info-section">
                <h3>Returns / Exchange</h3>
                <p>30-day return policy</p>
                <p>Free size exchanges</p>
                <p>Items must be unused and in original condition</p>
            </section>
        </div>
    </div>

    <!-- You Might Also Like -->
    <?php if ($relatedResult && $relatedResult->num_rows > 0): ?>
    <section class="recommendations">
        <h2>You might also like</h2>
        <div class="product-grid">
            <?php while($relatedProduct = $relatedResult->fetch_assoc()): ?>
                <?php
                $relatedIsDiscounted = $relatedProduct['is_discounted'] == 1;
                $relatedDiscountPercent = $relatedProduct['discount_percent'];
                $relatedOriginalPrice = $relatedProduct['price'];
                $relatedDiscountPrice = $relatedOriginalPrice * (1 - $relatedDiscountPercent / 100);
                ?>
                <div class="product-card">
                    <div class="product-image-placeholder">
                        <?php if (!empty($relatedProduct['image_path'])): 
                            $relatedImagePath = $relatedProduct['image_path'];
                            if (strpos($relatedImagePath, 'uploads/') === 0) {
                                $relatedDisplayPath = '../' . $relatedImagePath;
                            } else {
                                $relatedDisplayPath = '../' . $relatedImagePath;
                            }
                        ?>
                            <img src="<?php echo $relatedDisplayPath; ?>" alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #666;">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h4><?php echo htmlspecialchars($relatedProduct['name']); ?></h4>
                    <div class="card-price">
                        <?php if ($relatedIsDiscounted && $relatedDiscountPercent > 0): ?>
                            <span class="current">$<?php echo number_format($relatedDiscountPrice, 2); ?></span>
                            <span class="original">$<?php echo number_format($relatedOriginalPrice, 2); ?></span>
                            <span class="discount">-<?php echo $relatedDiscountPercent; ?>%</span>
                        <?php else: ?>
                            <span class="current">$<?php echo number_format($relatedOriginalPrice, 2); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="product-details.php?id=<?php echo $relatedProduct['id']; ?>">
                        <button class="view-more-btn">
                            <span class="btn-icon"></span>
                            <span>View More</span>
                        </button>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

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

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quantity selector
        const quantityElement = document.getElementById('quantity');
        const decreaseBtn = document.getElementById('decreaseQty');
        const increaseBtn = document.getElementById('increaseQty');
        const addToCartBtn = document.getElementById('addToCartBtn');
        
        let quantity = 1;
        
        decreaseBtn.addEventListener('click', function() {
            if (quantity > 1) {
                quantity--;
                quantityElement.textContent = quantity;
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            quantity++;
            quantityElement.textContent = quantity;
        });
        
        // Add to cart functionality
        addToCartBtn.addEventListener('click', function() {
            if (this.disabled) return;
            
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;
            
            addToCart(productId, productName, productPrice, quantity, this);
        });
        
        function addToCart(productId, productName, productPrice, quantity, button) {
            fetch('landing-page-section.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_to_cart&product_id=' + productId + '&quantity=' + quantity
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
                    button.style.background = '#27ae60';
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.style.background = '';
                    }, 2000);
                    
                    // Show success notification
                    showNotification('Product added to cart successfully!', 'success');
                    
                    // Update cart count in header
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showNotification('Error adding to cart. Please try again.', 'error');
            });
        }
        
        function updateCartCount() {
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
                    // You can update a cart count display if you have one
                    console.log('Cart count updated:', data.item_count);
                }
            });
        }
        
        // Size buttons
        const sizeButtons = document.querySelectorAll('.size-btn');
        sizeButtons.forEach(button => {
            button.addEventListener('click', function() {
                sizeButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
            });
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
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>