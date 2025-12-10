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
                            <li><a href="Limited-Time-Offers.php">Limited-Time Offers</a></li>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <a href="#" class="icon-link">
                                <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                                <span class="icon-badge">0</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Style That Defines You.</h1>
            <p>Discover premium fashion that elevates your confidence.</p>
            <a href="Limited-Time-Offers.php" class="hero-btn">Shop Now</a>
        </div>
    </section>

    <!-- Middle Banner -->
    <div class="middle-banner">
        SPROUT PRODUCTIONS
    </div>

    <!-- New Arrivals Section -->
    <section class="new-arrivals">
        <h2 class="section-title">NEW ARRIVALS</h2>
        
        <div class="products-grid">
            <!-- Product 1 -->
            <div class="product-card">
                <div class="product-image">
                    <img src="../images/new-arrival-section/Black Striped T-shirt.png" alt="T-shirt with Tape Details">
                </div>
                <div class="product-info">
                    <h3 class="product-name">T-shirt with Tape Details</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$120</span>
                    </div>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="product-card">
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
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">3.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$240</span>
                        <span class="original-price">$260</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="product-card">
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
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$180</span>
                    </div>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="product-card">
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
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$130</span>
                        <span class="original-price">$160</span>
                        <span class="discount-badge">-30%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="view-all">
            <a href="New-Arrival-Section.php">View All</a>
        </div>
    </section>

    <!-- Best Sellers Section -->
    <section class="best-sellers">
        <h2 class="section-title">BEST SELLERS</h2>
        
        <div class="products-grid">
            <!-- Product 1 -->
            <div class="product-card">
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
                        <span class="current-price">$212</span>
                        <span class="original-price">$232</span>
                        <span class="discount-badge">-20%</span>
                    </div>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="product-card">
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
                        <span class="current-price">$145</span>
                    </div>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="product-card">
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
                            <div class="star-filled"></div>
                            <div class="star-empty"></div>
                        </div>
                        <span class="rating-count">3.0/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$80</span>
                    </div>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="product-card">
                <div class="product-image">
                    <img src="../images/new-arrival-section/SkinnyFitJeans.png" alt="Faded Skinny Jeans">
                </div>
                <div class="product-info">
                    <h3 class="product-name">Faded Skinny Jeans</h3>
                    <div class="product-rating">
                        <div class="stars">
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                            <div class="star-filled"></div>
                        </div>
                        <span class="rating-count">4.5/5</span>
                    </div>
                    <div class="product-price">
                        <span class="current-price">$210</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="view-all">
            <a href="Limited-Time-Offers.php">View All</a>
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
                <p class="review-text">"I'm blown away by the quality and style of the clothes I received from Shop.co. From casual wear to elegant dresses, every piece I've bought has exceeded my expectations."</p>
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
                <p class="review-text">"Finding clothes that align with my personal style used to be a challenge until I discovered Shop.co. The range of options they offer is truly remarkable, catering to a variety of tastes and occasions."</p>
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
                <p class="review-text">"As someone who's always on the lookout for unique fashion pieces, I'm thrilled to have stumbled upon Shop.co. The selection of clothes is not only diverse but also on-point with the latest trends."</p>
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

</body>
</html>