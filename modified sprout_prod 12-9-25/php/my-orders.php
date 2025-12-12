<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin (prevent admin from accessing user orders page)
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

// Update session time
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

// Get user ID
$userId = $_SESSION['user_id'] ?? null;

// Static orders data for demonstration
$orders = [
    [
        'id' => 'ORD-001',
        'date' => '2024-01-15',
        'items' => [
            ['name' => 'Classic T-Shirt', 'quantity' => 2, 'price' => 25.99],
            ['name' => 'Denim Jacket', 'quantity' => 1, 'price' => 89.99]
        ],
        'status' => 'Delivered',
        'total' => 141.97,
        'tracking' => 'TRK-789456123'
    ],
    [
        'id' => 'ORD-002',
        'date' => '2024-01-20',
        'items' => [
            ['name' => 'Summer Dress', 'quantity' => 1, 'price' => 65.50],
            ['name' => 'Straw Hat', 'quantity' => 1, 'price' => 35.00],
            ['name' => 'Sunglasses', 'quantity' => 1, 'price' => 45.00]
        ],
        'status' => 'Processing',
        'total' => 145.50,
        'tracking' => 'TRK-321654987'
    ],
    [
        'id' => 'ORD-003',
        'date' => '2024-01-25',
        'items' => [
            ['name' => 'Leather Shoes', 'quantity' => 1, 'price' => 120.00]
        ],
        'status' => 'Shipped',
        'total' => 120.00,
        'tracking' => 'TRK-987654321'
    ],
    [
        'id' => 'ORD-004',
        'date' => '2024-02-01',
        'items' => [
            ['name' => 'Winter Coat', 'quantity' => 1, 'price' => 199.99],
            ['name' => 'Wool Scarf', 'quantity' => 2, 'price' => 29.99]
        ],
        'status' => 'Pending',
        'total' => 259.97,
        'tracking' => ''
    ],
    [
        'id' => 'ORD-005',
        'date' => '2024-02-05',
        'items' => [
            ['name' => 'Casual Shirt', 'quantity' => 3, 'price' => 45.00],
            ['name' => 'Chino Pants', 'quantity' => 2, 'price' => 65.00]
        ],
        'status' => 'Cancelled',
        'total' => 265.00,
        'tracking' => ''
    ]
];

// Get cart count for header
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
    <title>My Orders - Sprout Productions</title>
    <link rel="stylesheet" href="../css/my-orders.css">
    <link rel="stylesheet" href="../css/land-pag-sec.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
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
                            <li><a href="my-orders.php" class="active">My Orders</a></li>
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

    <main class="orders-container">
        <div class="orders-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-bag"></i> My Orders
            </h1>
            <div class="orders-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($orders); ?></span>
                    <span class="stat-label">Total Orders</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">$<?php echo number_format(array_sum(array_column($orders, 'total')), 2); ?></span>
                    <span class="stat-label">Total Spent</span>
                </div>
            </div>
        </div>

        <div class="orders-table-container">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Tracking</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="order-id"><?php echo $order['id']; ?></td>
                            <td class="order-date"><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                            <td class="order-items">
                                <div class="items-list">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="item-row">
                                            <span class="item-name"><?php echo $item['name']; ?></span>
                                            <span class="item-quantity">x<?php echo $item['quantity']; ?></span>
                                            <span class="item-price">$<?php echo number_format($item['price'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="order-total">$<?php echo number_format($order['total'], 2); ?></td>
                            <td class="order-status">
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </td>
                            <td class="order-tracking">
                                <?php if (!empty($order['tracking'])): ?>
                                    <a href="#" class="tracking-link"><?php echo $order['tracking']; ?></a>
                                <?php else: ?>
                                    <span class="no-tracking">Not Available</span>
                                <?php endif; ?>
                            </td>
                            <td class="order-actions">
                                <button class="action-btn view-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($order['status'] === 'Pending' || $order['status'] === 'Processing'): ?>
                                    <button class="action-btn cancel-btn" title="Cancel Order">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'Delivered'): ?>
                                    <button class="action-btn review-btn" title="Write Review">
                                        <i class="fas fa-star"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders yet.</p>
                <a href="Landing-Page-Section.php" class="shop-now-btn">
                    <i class="fas fa-shopping-cart"></i> Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </main>

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
        // Update cart badge
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
                    const cartBadge = document.getElementById('cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = data.item_count;
                    }
                }
            });
        }
        
        // Initialize cart badge
        updateCartBadge();
        
        // Add click handlers for action buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.closest('tr').querySelector('.order-id').textContent;
                alert('Viewing details for order: ' + orderId);
            });
        });
        
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.closest('tr').querySelector('.order-id').textContent;
                if (confirm('Are you sure you want to cancel order ' + orderId + '?')) {
                    alert('Order ' + orderId + ' has been cancelled.');
                }
            });
        });
        
        document.querySelectorAll('.review-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.closest('tr').querySelector('.order-id').textContent;
                alert('Writing review for order: ' + orderId);
            });
        });
        
        // Add tracking link functionality
        document.querySelectorAll('.tracking-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const trackingNum = this.textContent;
                alert('Tracking number: ' + trackingNum + '\nStatus: In transit');
            });
        });
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>