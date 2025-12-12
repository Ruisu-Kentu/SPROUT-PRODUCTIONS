<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin
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

$_SESSION['login_time'] = time();

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
    }
    
    if ($action === 'get_cart_count') {
        header('Content-Type: application/json');
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
            z-index: 1;
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
            transition: background 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: #333;
        }
        
        .add-to-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Product image container */
        .product-image {
            position: relative;
            height: 300px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
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
        
        /* Price styling */
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
            margin-left: 8px;
        }
        
        .discount-badge {
            background-color: #ffe0e0;
            color: #ff4444;
            padding: 2px 8px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
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
        
        /* Product price container */
        .product-price {
            display: flex;
            align-items: center;
            margin: 8px 0;
        }
        
        .current-price {
            font-size: 18px;
            font-weight: bold;
            color: #000;
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
                <?php while ($product = $newArrivalsResult->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <img src="../images/no-image.jpg" alt="No Image Available">
                            <?php endif; ?>
                            
                            <!-- Stock Badge -->
                            <div class="stock-badge <?php echo $product['stock'] <= 0 ? 'out-of-stock' : ''; ?>">
                                <?php echo $product['stock'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            
                            <!-- Product Rating (Static for now - you can add ratings to your database later) -->
                            <div class="product-rating">
                                <div class="stars">
                                    <?php
                                    // Static rating - you can modify this when you add ratings to your database
                                    $rating = 4.0;
                                    $fullStars = floor($rating);
                                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= $fullStars):
                                    ?>
                                        <div class="star-filled"></div>
                                    <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                        <div class="star-filled" style="background: linear-gradient(90deg, #ffd700 50%, #e0e0e0 50%);"></div>
                                    <?php else: ?>
                                        <div class="star-empty"></div>
                                    <?php endif; endfor; ?>
                                </div>
                                <span class="rating-count"><?php echo number_format($rating, 1); ?>/5</span>
                            </div>
                            
                            <!-- Product Price -->
                            <div class="product-price">
                                <?php if ($product['is_discounted'] && $product['discount_percent'] > 0): ?>
                                    <?php
                                    $discountedPrice = $product['price'] - ($product['price'] * $product['discount_percent'] / 100);
                                    ?>
                                    <span class="current-price">₱<?php echo number_format($discountedPrice, 2); ?></span>
                                    <span class="original-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="discount-badge">-<?php echo $product['discount_percent']; ?>%</span>
                                <?php else: ?>
                                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Add to Cart Button -->
                            <button class="add-to-cart-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <?php echo $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                            </button>
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
            button.addEventListener('click', function() {
                if (this.disabled) return;
                
                const productId = this.dataset.productId;
                
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
                        
                        const originalText = this.textContent;
                        this.textContent = '✓ Added!';
                        this.style.background = '#27ae60';
                        
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.background = '';
                        }, 2000);
                    } else {
                        alert(data.message || 'Error adding to cart');
                    }
                })
                .catch(error => {
                    console.error('Error adding to cart:', error);
                    alert('Error adding to cart. Please try again.');
                });
            });
        });
        
        updateCartBadge();
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>