<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to login if not logged in
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin (prevent admin from accessing user landing page)
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

// Fetch new arrivals (latest 4 products)
$newArrivalsQuery = "SELECT * FROM products ORDER BY created_at DESC LIMIT 4";
$newArrivalsResult = $conn->query($newArrivalsQuery);

// Fetch best sellers (you might want to create a sales_count column or use order_items table)
// For now, we'll fetch 4 random products as best sellers
$bestSellersQuery = "SELECT * FROM products ORDER BY RAND() LIMIT 4";
$bestSellersResult = $conn->query($bestSellersQuery);

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_to_cart':
            header('Content-Type: application/json');
            
            if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
                echo json_encode(['success' => false, 'message' => 'Please login first']);
                exit();
            }
            
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
                // Get updated cart count
                $countQuery = "SELECT SUM(quantity) as total_items FROM user_cart WHERE user_id = ?";
                $stmt = $conn->prepare($countQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $countResult = $stmt->get_result();
                $countData = $countResult->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added to cart',
                    'item_count' => $countData['total_items'] ?? 0
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
            }
            exit();
            
        case 'get_cart_count':
            header('Content-Type: application/json');
            
            if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
                echo json_encode(['success' => false, 'item_count' => 0]);
                exit();
            }
            
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if ($userId) {
                $countQuery = "SELECT SUM(quantity) as total_items FROM user_cart WHERE user_id = ?";
                $stmt = $conn->prepare($countQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $countResult = $stmt->get_result();
                $countData = $countResult->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'item_count' => $countData['total_items'] ?? 0
                ]);
            } else {
                echo json_encode(['success' => false, 'item_count' => 0]);
            }
            exit();
    }
}

// Get cart count for display
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
    $stmt->close();
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
            font-weight: bold;
            transition: background 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: #333;
        }
        
        .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
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
        
        /* View More Button */
        .view-more-btn {
            margin-top: 8px;
            padding: 8px 12px;
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-more-btn:hover {
            background: #e9e9e9;
            border-color: #999;
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
        
        .product-name-link {
            color: #333;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .product-name-link:hover {
            color: #000;
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
                            <li><a href="my-orders.php">My Orders</a></li>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <!-- Updated: Cart icon links to cart-section.php -->
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
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" title="View Product Details">
                                    <img src="<?php echo $displayPath; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'default-product-image\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                                </a>
                            <?php } else { ?>
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" title="View Product Details">
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
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" class="product-name-link">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="price-container">
                                <?php if ($isDiscounted && $discountPercent > 0): ?>
                                    <span class="original-price">$<?php echo number_format($originalPrice, 2); ?></span>
                                    <span class="current-price">$<?php echo number_format($discountPrice, 2); ?></span>
                                <?php else: ?>
                                    <span class="current-price">$<?php echo number_format($originalPrice, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                                    <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                            <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                <button class="view-more-btn">
                                    <i class="fas fa-eye"></i>
                                    <span>View Details</span>
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
            <a href="New-Arrival-Section.php">View All</a>
        </div>
    </section>

    <!-- Best Sellers Section -->
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
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" title="View Product Details">
                                    <img src="<?php echo $displayPath; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'default-product-image\'><i class=\'fas fa-image\'></i> Image not available</div>';">
                                </a>
                            <?php } else { ?>
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" title="View Product Details">
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
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" class="product-name-link">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="price-container">
                                <?php if ($isDiscounted && $discountPercent > 0): ?>
                                    <span class="original-price">$<?php echo number_format($originalPrice, 2); ?></span>
                                    <span class="current-price">$<?php echo number_format($discountPrice, 2); ?></span>
                                <?php else: ?>
                                    <span class="current-price">$<?php echo number_format($originalPrice, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                                    <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                            <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                <button class="view-more-btn">
                                    <i class="fas fa-eye"></i>
                                    <span>View Details</span>
                                </button>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No best sellers available at the moment.</p>
                    <p>Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="view-all">
            <a href="Best-Sellers-Section.php">View All</a>
        </div>
    </section>

    <!-- Customer Reviews Section -->
    <section class="customer-reviews">
        <div class="reviews-header">
            <h2 class="section-title" style="margin-bottom: 0;">OUR HAPPY CUSTOMERS</h2>
            <div class="reviews-navigation">
                <div class="nav-arrow">←</div>
                <div class="nav-arrow">→</div>
            </div>
        </div>

        <div class="reviews-grid">
            <!-- Review 1 -->
            <div class="review-card">
                <div class="review-stars">
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                </div>
                <div class="reviewer-name">
                    Sarah M.
                    <div class="verified-badge"></div>
                </div>
                <p class="review-text">"I'm blown away by the quality and style of the clothes I received from Sprout Productions. From casual wear to elegant dresses, every piece I've bought has exceeded my expectations."</p>
            </div>

            <!-- Review 2 -->
            <div class="review-card">
                <div class="review-stars">
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                </div>
                <div class="reviewer-name">
                    Alex K.
                    <div class="verified-badge"></div>
                </div>
                <p class="review-text">"Finding clothes that align with my personal style used to be a challenge until I discovered Sprout Productions. The range of options they offer is truly remarkable, catering to a variety of tastes and occasions."</p>
            </div>

            <!-- Review 3 -->
            <div class="review-card">
                <div class="review-stars">
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                    <div class="star-filled"></div>
                </div>
                <div class="reviewer-name">
                    James L.
                    <div class="verified-badge"></div>
                </div>
                <p class="review-text">"As someone who's always on the lookout for unique fashion pieces, I'm thrilled to have stumbled upon Sprout Productions. The selection of clothes is not only diverse but also on-point with the latest trends."</p>
            </div>
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

    <!-- JavaScript for Add to Cart functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
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
        
        // Initialize cart badge on page load
        updateCartBadge();
    });
    </script>

</body>
</html>
<?php $conn->close(); ?>