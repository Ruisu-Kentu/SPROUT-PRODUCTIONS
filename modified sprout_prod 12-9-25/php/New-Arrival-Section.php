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

// Fetch all new arrivals with pagination - SORTED BY NEWEST FIRST
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

$countQuery = "SELECT COUNT(*) as total FROM products";
$countResult = $conn->query($countQuery);
$totalProducts = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalProducts / $perPage);

// Updated query to sort by newest first (created_at DESC)
$newArrivalsQuery = "SELECT * FROM products ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$newArrivalsResult = $conn->query($newArrivalsQuery);

// Handle AJAX cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_to_cart') {
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
        
        if ($product['stock'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Product out of stock']);
            exit();
        }
        
        $checkCartQuery = "SELECT * FROM user_cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($checkCartQuery);
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
        $cartResult = $stmt->get_result();
        
        if ($cartResult->num_rows > 0) {
            $updateQuery = "UPDATE user_cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ii", $userId, $productId);
        } else {
            $insertQuery = "INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ii", $userId, $productId);
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
                'item_count' => $countData['product_count'] ?? 0  // Changed to product_count
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
                'item_count' => $countData['product_count'] ?? 0  // Changed to product_count
            ]);
        } else {
            echo json_encode(['success' => false, 'item_count' => 0]);
        }
        exit();
    }
}

// Get cart count for display - COUNT DISTINCT PRODUCTS (not sum of quantities)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprout Productions - New Arrivals</title>
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        /* Additional styles for New Arrivals page */
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
        
        /* Product grid for New Arrivals - 3 columns */
        .products-section {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 40px 0 60px;
        }
        
        .pagination button,
        .pagination a {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
            background-color: #fff;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .pagination button:hover,
        .pagination a:hover {
            background-color: #f5f5f5;
            border-color: #000;
        }
        
        .pagination .active {
            background-color: #000;
            color: #fff;
            border-color: #000;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .disabled:hover {
            background-color: #fff;
            border-color: #ddd;
        }
        
        /* Stock badge */
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
        
        /* Add to cart button */
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
            transition: all 0.3s ease;
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
        
        /* Product image container - UPDATED */
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
        
        /* Default image styling */
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
        
        /* Star ratings */
        .star-filled {
            width: 16px;
            height: 16px;
            background-color: #ffd700;
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
        }
        
        .star-empty {
            width: 16px;
            height: 16px;
            background-color: #e0e0e0;
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
        }
        
        /* Price styling - UPDATED */
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
        
        /* Discount badge */
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
        
        /* View Details button - UPDATED to match Add to Cart */
        .view-more-btn {
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .view-more-btn:hover {
            background: #333;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .view-more-btn i {
            font-size: 14px;
        }
        
        /* Product rating container */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
        }
        
        .stars {
            display: flex;
            gap: 2px;
        }
        
        .rating-count {
            font-size: 14px;
            color: #666;
        }
        
        /* No products message */
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 18px;
        }
        
        /* Product name styling */
        .product-name {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
            min-height: 48px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        /* Product name link styling */
        .product-name-link {
            color: #333;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .product-name-link:hover {
            color: #000;
        }
        
        /* Responsive design */
        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 36px;
            }
        }
        
        /* Notification styles (same as landing page) */
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
                            <li><a href="New-Arrival-Section.php" class="active">New Arrivals</a></li>
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
        <h1>LATEST COLLECTIONS</h1>
        <div class="breadcrumb">
            <a href="Landing-Page-Section.php">Home</a> / <span>New Arrivals</span>
        </div>
    </div>

    <!-- Products Section -->
    <section class="products-section">
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
                            // Check if image path exists and is valid (SAME AS LANDING PAGE)
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
                        
                            
                            <!-- Product Price -->
                            <div class="price-container">
                                <?php if ($isDiscounted && $discountPercent > 0): ?>
                                    <span class="original-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                    <span class="current-price">₱<?php echo number_format($discountPrice, 2); ?></span>
                                <?php else: ?>
                                    <span class="current-price">₱<?php echo number_format($originalPrice, 2); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Add to Cart Button -->
                            <button class="add-to-cart-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-product-price="<?php echo $isDiscounted ? $discountPrice : $originalPrice; ?>"
                                    <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                            
                            <!-- View Details Button -->
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
                <div class="no-products">
                    <p>No new arrivals found.</p>
                    <p>Check back soon for new products!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">←</a>
            <?php else: ?>
                <button class="disabled">←</button>
            <?php endif; ?>
            
            <?php
            // Show page numbers
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<a href="?page=1">1</a>';
                if ($startPage > 2) echo '<span>...</span>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span>...</span>';
                echo '<a href="?page=' . $totalPages . '">' . $totalPages . '</a>';
            }
            ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>">→</a>
            <?php else: ?>
                <button class="disabled">→</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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

    <!-- JavaScript for Add to Cart -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        const cartBadge = document.getElementById('cart-badge');
        
        // Function to update cart badge
        function updateCartBadge() {
            fetch('New-Arrival-Section.php', {
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
                e.stopPropagation(); // Prevent triggering other clicks
                if (this.disabled) return;
                
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const productPrice = this.dataset.productPrice;
                
                addToCart(productId, productName, productPrice, this);
            });
        });
        
        function addToCart(productId, productName, productPrice, button = null) {
            fetch('New-Arrival-Section.php', {
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
        
        // Notification function (same as landing page)
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