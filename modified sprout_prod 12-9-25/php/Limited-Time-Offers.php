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
    <title>Sprout Productions - Limited Time Offers</title>
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        /* Additional styles for Limited Time Offers page */
        .page-header {
            text-align: center;
            padding: 40px 20px 20px;
              background-color: #f5f5f5;
            color: black;
        }
        
        .page-header h1 {
            font-family: 'Georgia', serif;
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 2px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .page-header .offer-badge {
            display: inline-block;
            background: white;
            color: #ff6b6b;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .breadcrumb {
            font-size: 14px;
          color: black; 
            margin-top: 10px;
        }
        
        .breadcrumb a {
            color: black;;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: white;
        }
        
        /* Product grid for Limited Time Offers - 3 columns */
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
        
        /* Sale tag on product cards */
        .product-card {
            position: relative;
        }
        
        .sale-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ff4444;
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            z-index: 1;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
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
            background-color: #ff4444;
            color: #fff;
            border-color: #ff4444;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .disabled:hover {
            background-color: #fff;
            border-color: #ddd;
        }
        
        /* Add to cart button */
        .add-to-cart-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: black;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: #e63939;
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
        
        /* Price styling - enhanced for sales */
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
            margin-left: 8px;
        }
        
        .discount-badge {
            background-color: #ff4444;
            color: white;
            padding: 3px 10px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .current-price {
            color: #ff4444;
            font-weight: bold;
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
                            <li><a href="Limited-Time-Offers.php" class="active">Special Offers</a></li>
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
        <h1>ON SALE COLLECTIONS</h1>
        <div class="offer-badge">🔥 Price Drops!</div>
        <div class="breadcrumb">
            <a href="Landing-Page-Section.php">Home</a> / <span>Special Offers</span>
        </div>
    </div>

    <!-- Products Section -->
    <section class="products-section">
        <div class="products-grid">
            <!-- Product 1 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/Gradient.png" alt="Gradient Graphic T-shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Gradient Graphic T-shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">3.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱145</span>
                        <span class="original-price">₱242</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/Polo with Tipping Details.png" alt="Polo with Tipping Details">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Polo with Tipping Details</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱180</span>
                        <span class="original-price">₱242</span>
                        <span class="discount-badge">-30%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/Black Striped T-shirt.png" alt="Black Striped T-shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Black Striped T-shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">5.0/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱120</span>
                        <span class="original-price">₱150</span>
                        <span class="discount-badge">-30%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/SkinnyFitJeans.png" alt="Skinny Fit Jeans">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Skinny Fit Jeans</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">3.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱240</span>
                        <span class="original-price">₱260</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 5 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/CHECKERED SHIRT.png" alt="Checkered Shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Checkered Shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱180</span>
                        <span class="original-price">₱242</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 6 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/SLEEVE STRIPED T-SHIRT.png" alt="Sleeve Striped T-shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Sleeve Striped T-shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱130</span>
                        <span class="original-price">₱160</span>
                        <span class="discount-badge">-30%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 7 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/VERTICAL STRIPPED.png" alt="Vertical Striped Shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Vertical Striped Shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">5.0/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱212</span>
                        <span class="original-price">₱232</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 8 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/COURAGE GRAPHIC T-SHIRT.png" alt="Courage Graphic T-shirt">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Courage Graphic T-shirt</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">4.0/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱145</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>

            <!-- Product 9 -->
            <div class="product-card">
                <span class="sale-tag">SALE</span>
                <div class="product-image">
                    <img src="../images/new-arrival-section/LOOSE FIT BERMUDA.png" alt="Loose Fit Bermuda Shorts">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Loose Fit Bermuda Shorts</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">3.0/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">₱80</span>
                    </div>
                    <button class="add-to-cart-btn">Add to Cart</button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="disabled">←</button>
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <span>...</span>
            <a href="#">8</a>
            <a href="#">9</a>
            <a href="#">10</a>
            <button>→</button>
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
</body>
</html>
<?php $conn->close(); ?>