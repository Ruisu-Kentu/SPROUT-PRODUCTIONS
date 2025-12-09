<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-dash.css">
    
</head>
<body>
    <div class="header">
        <div class="logo">SPROUT PRODUCTIONS</div>
        <img src="../images/sprout logo bg-removed 3.png" alt="">
        <div class="user-info">
            <span>Welcome, Admin</span>
            <div class="user-avatar">A</div>
            <button class="logout-btn" onclick="logout()">Logout</button>
        </div>
    </div>

    <nav class="navbar">
        <div class="navbar-container">
            <button class="mobile-menu-btn" onclick="toggleMenu()">☰</button>
            <ul class="nav-links" id="navLinks">
                <li><a href="#" class="active" onclick="navigate('dashboard')">Dashboard</a></li>
                <li><a href="../php/Admin-Products-Page.php" onclick="navigate('products')">Products</a></li>
                <li><a href="../php/Admin-Orders-Page.php" onclick="navigate('orders')">Orders</a></li>
                <li><a href="../php/Admin-Customers-Page.php" onclick="navigate('customers')">Customers</a></li>
                <li><a href="../php/Admin-Analytics-Page.php" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="../php/Admin-Settings-Page.php" onclick="navigate('settings')">Settings</a></li>
            </ul>
            <div class="nav-search">
                <input type="text" class="search-box" placeholder="Search..." onkeyup="handleSearch(event)">
            </div>
        </div>
    </nav>

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

        <div class="quick-actions">
            <button class="quick-action-btn" onclick="addProduct()">+ Add New Product</button>
            <button class="quick-action-btn" onclick="viewOrders()">View All Orders</button>
            <button class="quick-action-btn" onclick="manageCustomers()">Manage Customers</button>
            <button class="quick-action-btn" onclick="generateReport()">Generate Report</button>
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

    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        function navigate(section) {
            // Remove active class from all links
            const links = document.querySelectorAll('.nav-links a');
            links.forEach(link => link.classList.remove('active'));
            
            // Add active class to clicked link
            event.target.classList.add('active');
            
            // alert('Navigating to ' + section + ' section');
            // Add your navigation logic here
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                const searchTerm = event.target.value;
                alert('Searching for: ' + searchTerm);
                // Add your search logic here
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                alert('Logging out...');
                // Add your logout logic here
            }
        }

        function addProduct() {
            
            window.location.href = '../php/Admin-Products-Page.php';
        }

        function viewOrders() {
            alert('View All Orders page would open here');
        }

        function manageCustomers() {
            alert('Manage Customers page would open here');
        }

        function generateReport() {
            alert('Generate Report functionality would open here');
        }

        function viewOrder(orderId) {
            alert('Viewing order #' + orderId);
        }

        function editOrder(orderId) {
            alert('Editing order #' + orderId);
        }

        function viewProduct(productId) {
            alert('Viewing product #' + productId);
        }

        function editProduct(productId) {
            alert('Editing product #' + productId);
        }
    </script>
</body>
</html>