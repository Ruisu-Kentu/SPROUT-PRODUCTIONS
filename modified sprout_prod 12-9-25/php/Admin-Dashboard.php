<?php
// Start session and authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login-Form.php");
    exit();
}

// Check if user is admin
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: Landing-Page-Section.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <style>
        /* Additional styles for updated layout */
        .admin-welcome {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .admin-icon-small {
            width: 20px;
            height: 20px;
            filter: invert(1);
            opacity: 0.9;
        }
        
        .admin-text {
            color: #fff;
            font-weight: 600;
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
                    <div class="admin-welcome">
                        <img src="../images/user_logo.png" alt="Admin" class="admin-icon-small">
                        <span class="admin-text">Welcome, Admin</span>
                    </div>
                    <div class="top-bar-actions">
                        <a href="logout.php" class="logout-link-no-icon">
                            Logout
                        </a>
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
                        <a href="Admin-Dashboard.php" class="logo-link">
                            <img src="../images/sprout logo bg-removed 3.png" alt="Sprout Logo" class="logo-img">
                            <span class="logo-text">SPROUT PRODUCTIONS</span>
                        </a>
                    </div>

                    <!-- Center Navigation Menu -->
                    <nav class="center-nav">
                        <ul class="nav-menu">
                            <li><a href="Admin-Dashboard.php" class="active">Dashboard</a></li>
                            <li><a href="../php/Admin-Products-Page.php">Products</a></li>
                            <li><a href="../php/Admin-Orders-Page.php">Orders</a></li>
                            <li><a href="../php/Admin-Customers-Page.php">Customers</a></li>
                            <li><a href="../php/Admin-Analytics-Page.php">Analytics</a></li>
                            <li><a href="../php/Admin-Settings-Page.php">Settings</a></li>
                        </ul>
                    </nav>

                    <!-- Right Side Icons -->
                    <div class="right-nav">
                        <div class="action-icons">
                            <div class="user-avatar">A</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <h1 class="dashboard-title">Dashboard Overview</h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value">1,247</div>
                    <div class="stat-change">↑ 12% from last month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Revenue</div>
                    <div class="stat-value">$18,450</div>
                    <div class="stat-change">↑ 8% from last month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">New Customers</div>
                    <div class="stat-value">342</div>
                    <div class="stat-change">↑ 15% from last month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Products</div>
                    <div class="stat-value">89</div>
                    <div class="stat-change">↑ 3% from last month</div>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Recent Orders</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <tr>
                                <td>#10247</td>
                                <td>Sarah Johnson</td>
                                <td>Organic Seed Kit</td>
                                <td>$49.99</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>Nov 28, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10247')">View</button>
                                    <button class="action-btn" onclick="editOrder('10247')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10246</td>
                                <td>Michael Chen</td>
                                <td>Garden Tool Set</td>
                                <td>$89.99</td>
                                <td><span class="status-badge status-active">Processing</span></td>
                                <td>Nov 28, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10246')">View</button>
                                    <button class="action-btn" onclick="editOrder('10246')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10245</td>
                                <td>Emily Rodriguez</td>
                                <td>Plant Food Bundle</td>
                                <td>$34.99</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>Nov 27, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10245')">View</button>
                                    <button class="action-btn" onclick="editOrder('10245')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10244</td>
                                <td>David Kim</td>
                                <td>Composting Kit</td>
                                <td>$64.99</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>Nov 27, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10244')">View</button>
                                    <button class="action-btn" onclick="editOrder('10244')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10243</td>
                                <td>Lisa Anderson</td>
                                <td>Indoor Herb Garden</td>
                                <td>$79.99</td>
                                <td><span class="status-badge status-active">Processing</span></td>
                                <td>Nov 26, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10243')">View</button>
                                    <button class="action-btn" onclick="editOrder('10243')">Edit</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Top Selling Products</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Organic Seed Collection</td>
                                <td>Seeds</td>
                                <td>342</td>
                                <td>$8,550</td>
                                <td>156</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('1')">View</button>
                                    <button class="action-btn" onclick="editProduct('1')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Premium Garden Tools</td>
                                <td>Tools</td>
                                <td>287</td>
                                <td>$14,350</td>
                                <td>89</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('2')">View</button>
                                    <button class="action-btn" onclick="editProduct('2')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Composting Starter Kit</td>
                                <td>Composting</td>
                                <td>198</td>
                                <td>$9,900</td>
                                <td>45</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('3')">View</button>
                                    <button class="action-btn" onclick="editProduct('3')">Edit</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Dashboard functions
        function addProduct() {
            window.location.href = '../php/Admin-Products-Page.php';
        }

        function viewOrders() {
            window.location.href = '../php/Admin-Orders-Page.php';
        }

        function manageCustomers() {
            window.location.href = '../php/Admin-Customers-Page.php';
        }

        function generateReport() {
            alert('Generate Report functionality would open here');
        }

        function viewOrder(orderId) {
            window.location.href = `../php/Admin-Orders-Page.php?id=${orderId}`;
        }

        function editOrder(orderId) {
            window.location.href = `../php/Admin-Orders-Page.php?edit=${orderId}`;
        }

        function viewProduct(productId) {
            window.location.href = `../php/Admin-Products-Page.php?id=${productId}`;
        }

        function editProduct(productId) {
            window.location.href = `../php/Admin-Products-Page.php?edit=${productId}`;
        }
    </script>
</body>
</html>