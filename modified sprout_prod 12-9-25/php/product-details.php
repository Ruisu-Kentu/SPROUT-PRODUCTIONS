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

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);  
}

// Get product ID from URL
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle AJAX cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_to_cart') {
        header('Content-Type: application/json');
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
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
        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
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
            $updateQuery = "UPDATE user_cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("iii", $quantity, $userId, $productId);
        } else {
            // Insert new item
            $insertQuery = "INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iii", $userId, $productId, $quantity);
        }
        
        if ($stmt->execute()) {
            // Get updated cart count - COUNT DISTINCT PRODUCTS (not sum of quantities)
            $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
            $stmt = $conn->prepare($countQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $countResult = $stmt->get_result();
            $countData = $countResult->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart',
                'item_count' => $countData['product_count'] ?? 0
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
        }
        exit();
    }
    
    if ($action === 'get_cart_count') {
        header('Content-Type: application/json');
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        if ($userId) {
            // Get cart count - COUNT DISTINCT PRODUCTS (not sum of quantities)
            $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
            $stmt = $conn->prepare($countQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $countResult = $stmt->get_result();
            $countData = $countResult->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'item_count' => $countData['product_count'] ?? 0
            ]);
        } else {
            echo json_encode(['success' => false, 'item_count' => 0]);
        }
        exit();
    }
}

// Fetch product details
$product = null;
if ($productId > 0) {
    $productQuery = "SELECT * FROM products WHERE id = ?";
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $productResult = $stmt->get_result();
    
    if ($productResult->num_rows > 0) {
        $product = $productResult->fetch_assoc();
    } else {
        // Product not found, redirect to shop
        header('Location: Best-Sellers-Section.php');
        exit();
    }
    $stmt->close();
} else {
    // No product ID provided, redirect to shop
    header('Location: Best-Sellers-Section.php');
    exit();
}

// Get cart count for display
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$cartCount = 0;

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

// Fetch recommended products (excluding current product)
$recommendedQuery = "SELECT * FROM products WHERE id != ? ORDER BY RAND() LIMIT 4";
$stmt = $conn->prepare($recommendedQuery);
$stmt->bind_param("i", $productId);
$stmt->execute();
$recommendedResult = $stmt->get_result();

// Calculate prices
$isDiscounted = isset($product['is_discounted']) ? $product['is_discounted'] == 1 : false;
$discountPercent = isset($product['discount_percent']) ? $product['discount_percent'] : 0;
$originalPrice = isset($product['price']) ? $product['price'] : 0;
$discountPrice = $isDiscounted ? $originalPrice * (1 - $discountPercent / 100) : $originalPrice;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Sprout Productions</title>
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            padding: 40px 20px 20px;
            background-color: #f5f5f5;
        }
        
        .page-header h1 {
            font-family: 'Georgia', serif;
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .breadcrumb a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: #000;
        }

        /* Product Container */
        .product-container {
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 80px 1fr 1fr;
            gap: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .thumbnails {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .thumbnail.active {
            border-color: #000;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .main-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            min-height: 500px;
        }

        .main-image img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-info h1 {
            font-family: 'Georgia', serif;
            font-size: 32px;
            font-weight: 700;
            color: #000;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stars {
            display: flex;
            gap: 4px;
        }

        .star-filled,
        .star-empty {
            width: 20px;
            height: 20px;
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
        }

        .star-filled {
            background-color: #ffd700;
        }

        .star-empty {
            background-color: #e0e0e0;
        }

        .rating-count {
            color: #666;
            font-size: 14px;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .current-price {
            font-size: 32px;
            font-weight: 700;
            color: #000;
        }

        .original-price {
            font-size: 24px;
            color: #999;
            text-decoration: line-through;
        }

        .discount-badge {
            background-color: #ffe0e0;
            color: #ff4444;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: bold;
        }

        .description {
            color: #666;
            line-height: 1.8;
            font-size: 15px;
        }

        .size-section {
            padding: 20px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .size-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 15px;
            color: #000;
        }

        .size-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .size-btn {
            padding: 10px 20px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .size-btn:hover {
            border-color: #000;
        }

        .size-btn.active {
            background: #000;
            color: #fff;
            border-color: #000;
        }

        .cart-section {
            display: flex;
            gap: 15px;
            padding-top: 20px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 2rem;
            padding: 12px 24px;
        }

        .qty-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .qty-btn:hover {
            color: #000;
        }

        .qty-number {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }

        .add-to-cart-btn {
            flex: 1;
            padding: 14px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 2rem;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-to-cart-btn:hover:not(:disabled) {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Stock badge */
        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Tabs */
        .tabs-container {
            max-width: 1200px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        .tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e5e7eb;
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
            transition: color 0.3s ease;
        }

        .tab:hover {
            color: #000;
        }

        .tab.active {
            color: #000;
            font-weight: 600;
        }

        .tab.active::after {
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
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .info-section {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .info-section h3 {
            font-family: 'Georgia', serif;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #000;
        }

        .info-section p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 8px;
        }

        .info-section ul {
            list-style: none;
            padding-left: 0;
        }

        .info-section ul li {
            color: #666;
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .info-section ul li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
        }

        /* Recommendations */
        .recommendations {
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 0 20px;
        }

        .recommendations h2 {
            font-family: 'Georgia', serif;
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 40px;
            color: #000;
            letter-spacing: 1px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }

        .product-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            height: 250px;
            background: #f9fafb;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-card h4 {
            padding: 15px 15px 10px;
            font-size: 16px;
            font-weight: 600;
            color: #000;
        }

        .card-rating {
            padding: 0 15px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }

        .card-rating .star-filled,
        .card-rating .star-empty {
            width: 16px;
            height: 16px;
        }

        .card-price {
            padding: 0 15px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-price .current {
            font-size: 20px;
            font-weight: 700;
            color: #000;
        }

        .card-price .original {
            font-size: 16px;
            color: #999;
            text-decoration: line-through;
        }

        .card-price .discount {
            background-color: #ffe0e0;
            color: #ff4444;
            padding: 2px 8px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: bold;
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

        /* Responsive */
        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
            }

            .thumbnails {
                flex-direction: row;
                justify-content: center;
            }

            .details-container {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 36px;
            }

            .product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Header -->
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

    <!-- Page Header -->
    <div class="page-header">
        <h1>PRODUCT DETAILS</h1>
        <div class="breadcrumb">
            <a href="Landing-Page-Section.php">Home</a> / <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>
    </div>

    <!-- Main Product Section -->
    <div class="product-container">
        <!-- Left: Thumbnails -->
        <div class="thumbnails">
            <div class="thumbnail active">
                <?php 
                $imagePath = !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : '';
                $displayPath = '';
                
                if (!empty($imagePath)) {
                    if (strpos($imagePath, 'uploads/') === 0) {
                        $displayPath = '../' . $imagePath;
                    } else if (strpos($imagePath, '../uploads/') === 0) {
                        $displayPath = $imagePath;
                    } else if (strpos($imagePath, 'http') === 0) {
                        $displayPath = $imagePath;
                    } else {
                        $displayPath = '../' . $imagePath;
                    }
                } else {
                    $displayPath = '../images/new-arrival-section/Gradient.png';
                }
                ?>
                <img src="<?php echo $displayPath; ?>" alt="Product Image" 
                     onerror="this.onerror=null; this.src='../images/new-arrival-section/Gradient.png';">
            </div>
        </div>

        <!-- Center: Main Product Image -->
        <div class="main-image">
            <img src="<?php echo $displayPath; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                 id="main-product-image"
                 onerror="this.onerror=null; this.src='../images/new-arrival-section/Gradient.png';">
        </div>

        <!-- Right: Product Info -->
        <div class="product-info">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <!-- Stock Status -->
            <?php if ($product['stock'] > 0): ?>
                <div class="stock-badge in-stock">In Stock (<?php echo $product['stock']; ?> available)</div>
            <?php else: ?>
                <div class="stock-badge out-of-stock">Out of Stock</div>
            <?php endif; ?>

            <!-- Price -->
            <div class="product-price">
                <span class="current-price">₱<?php echo number_format($discountPrice, 2); ?></span>
                <?php if ($isDiscounted && $discountPercent > 0): ?>
                    <span class="original-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                    <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <p class="description">
                <?php echo htmlspecialchars($product['description'] ?? 'This product offers superior comfort and style.'); ?>
            </p>

            <!-- Quantity & Add to Cart -->
            <div class="cart-section">
                <div class="quantity-selector">
                    <button class="qty-btn" id="decrease-qty">-</button>
                    <span class="qty-number" id="quantity">1</span>
                    <button class="qty-btn" id="increase-qty">+</button>
                </div>
                <button class="add-to-cart-btn" 
                        id="add-to-cart"
                        data-product-id="<?php echo $product['id']; ?>"
                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                    <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab active">Product Details</button>
            <button class="tab">Rating & Reviews</button>
            <button class="tab">FAQs</button>
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
                <p><strong>Material:</strong> 100% Premium Cotton</p>
                <p><strong>Fabric Weight:</strong> 220 GSM (mid-heavy)</p>
                <p><strong>Fit:</strong> Regular / Unisex</p>
                <p><strong>Print Type:</strong> High-quality screen print</p>
                <p><strong>Neckline:</strong> Crew neck</p>
                <p><strong>Sleeve Length:</strong> Short sleeves</p>
            </section>

            <!-- Features -->
            <section class="info-section">
                <h3>Features</h3>
                <ul>
                    <li>Durable ribbed neckline</li>
                    <li>Eco-friendly ink used</li>
                    <li>Pre-shrunk fabric to reduce washing shrinkage</li>
                    <li>Reinforced stitching for longer wear</li>
                    <li>Fade-resistant color</li>
                </ul>
            </section>

            <!-- Size & Fit -->
            <section class="info-section">
                <h3>Size & Fit</h3>
                <p>True-to-size fit</p>
                <p>Model is 5'9" and wearing Medium</p>
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
                <p>Iron inside out</p>
                <p>Hang or tumble dry low</p>
            </section>

            <!-- Shipping -->
            <section class="info-section">
                <h3>Shipping</h3>
                <p>Ships in 1-3 days</p>
                <p>Cash on delivery available</p>
                <p>Free shipping for ₱1500+ orders</p>
            </section>

            <!-- Returns / Exchange -->
            <section class="info-section">
                <h3>Returns / Exchange</h3>
                <p>7-day size exchange</p>
                <p>Must be unused & unwashed</p>
            </section>
        </div>
    </div>

    <!-- You Might Also Like -->
    <?php if ($recommendedResult->num_rows > 0): ?>
    <section class="recommendations">
        <h2>You might also like</h2>
        <div class="product-grid">
            <?php while($recommended = $recommendedResult->fetch_assoc()): ?>
                <?php
                $recIsDiscounted = $recommended['is_discounted'] == 1;
                $recDiscountPercent = $recommended['discount_percent'];
                $recOriginalPrice = $recommended['price'];
                $recDiscountPrice = $recIsDiscounted ? $recOriginalPrice * (1 - $recDiscountPercent / 100) : $recOriginalPrice;
                
                $recImagePath = !empty($recommended['image_path']) ? htmlspecialchars($recommended['image_path']) : '';
                $recDisplayPath = '';
                
                if (!empty($recImagePath)) {
                    if (strpos($recImagePath, 'uploads/') === 0) {
                        $recDisplayPath = '../' . $recImagePath;
                    } else if (strpos($recImagePath, '../uploads/') === 0) {
                        $recDisplayPath = $recImagePath;
                    } else if (strpos($recImagePath, 'http') === 0) {
                        $recDisplayPath = $recImagePath;
                    } else {
                        $recDisplayPath = '../' . $recImagePath;
                    }
                } else {
                    $recDisplayPath = '../images/new-arrival-section/Gradient.png';
                }
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <a href="product-details.php?id=<?php echo $recommended['id']; ?>">
                            <img src="<?php echo $recDisplayPath; ?>" 
                                 alt="<?php echo htmlspecialchars($recommended['name']); ?>"
                                 onerror="this.onerror=null; this.src='../images/new-arrival-section/Gradient.png';">
                        </a>
                    </div>
                    <h4><?php echo htmlspecialchars($recommended['name']); ?></h4>
                    <div class="card-price">
                        <span class="current">₱<?php echo number_format($recDiscountPrice, 2); ?></span>
                        <?php if ($recIsDiscounted && $recDiscountPercent > 0): ?>
                            <span class="original">₱<?php echo number_format($recOriginalPrice, 2); ?></span>
                            <span class="discount">-<?php echo $recDiscountPercent; ?>%</span>
                        <?php endif; ?>
                    </div>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quantity selector functionality
        const qtyNumber = document.getElementById('quantity');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const addToCartBtn = document.getElementById('add-to-cart');
        const cartBadge = document.getElementById('cart-badge');
        
        // Quantity buttons
        decreaseBtn.addEventListener('click', function() {
            let currentQty = parseInt(qtyNumber.textContent);
            if (currentQty > 1) {
                qtyNumber.textContent = currentQty - 1;
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            let currentQty = parseInt(qtyNumber.textContent);
            qtyNumber.textContent = currentQty + 1;
        });
        
        // Thumbnail selector functionality
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Add to cart functionality
        addToCartBtn.addEventListener('click', function() {
            if (this.disabled) return;
            
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const quantity = parseInt(qtyNumber.textContent);
            
            addToCart(productId, productName, quantity, this);
        });
        
        function addToCart(productId, productName, quantity, button = null) {
            fetch('product-details.php?id=<?php echo $productId; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_to_cart&product_id=' + productId + '&quantity=' + quantity
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
        
        // Function to update cart badge
        function updateCartBadge() {
            fetch('product-details.php?id=<?php echo $productId; ?>', {
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
        
        // Initialize cart badge on page load
        updateCartBadge();
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>