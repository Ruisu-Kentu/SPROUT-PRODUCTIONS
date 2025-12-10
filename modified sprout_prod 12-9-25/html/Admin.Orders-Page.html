<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Orders - Sprout Productions</title>
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
                <li><a href="../php/Admin-Dashboard.php" onclick="navigate('dashboard')">Dashboard</a></li>
                <li><a href="../php/Admin-Products-Page.php" onclick="navigate('products')">Products</a></li>
                <li><a href="../php/Admin-Orders-Page.php" class="active" onclick="navigate('orders')">Orders</a></li>
                <li><a href="../php/Admin-Customers-Page.php" onclick="navigate('customers')">Customers</a></li>
                <li><a href="../php/Admin-Analytics-Page.php" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="../php/Admin-Settings-Page.php" onclick="navigate('settings')">Settings</a></li>
            </ul>
            <div class="nav-search">
                <input type="text" class="search-box" placeholder="Search orders..." onkeyup="handleSearch(event)">
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="dashboard-title">Orders</h1>

        <div class="content-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" onchange="filterOrders()">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <button class="quick-action-btn" onclick="exportOrders()">Export CSV</button>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <tr data-status="pending">
                            <td>#20001</td>
                            <td>Anna Lopez</td>
                            <td>Organic Seed Kit (1)</td>
                            <td>$49.99</td>
                            <td><span class="status-badge status-pending">Pending</span></td>
                            <td>Dec 08, 2025</td>
                            <td>
                                <button class="action-btn" onclick="viewOrder('20001')">View</button>
                                <button class="action-btn" onclick="updateStatus('20001')">Update</button>
                            </td>
                        </tr>
                        <tr data-status="processing">
                            <td>#20000</td>
                            <td>Brian Evans</td>
                            <td>Garden Tool Set (1)</td>
                            <td>$89.99</td>
                            <td><span class="status-badge status-active">Processing</span></td>
                            <td>Dec 07, 2025</td>
                            <td>
                                <button class="action-btn" onclick="viewOrder('20000')">View</button>
                                <button class="action-btn" onclick="updateStatus('20000')">Update</button>
                            </td>
                        </tr>
                        <tr data-status="completed">
                            <td>#19999</td>
                            <td>Catherine Park</td>
                            <td>Composting Kit (1)</td>
                            <td>$64.99</td>
                            <td><span class="status-badge status-completed">Completed</span></td>
                            <td>Dec 06, 2025</td>
                            <td>
                                <button class="action-btn" onclick="viewOrder('19999')">View</button>
                                <button class="action-btn" onclick="updateStatus('19999')">Update</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px;display:flex;justify-content:center;">
                <button class="quick-action-btn" onclick="prevPage()">Previous</button>
                <div style="width:12px"></div>
                <button class="quick-action-btn" onclick="nextPage()">Next</button>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        function navigate(section) {
            const links = document.querySelectorAll('.nav-links a');
            links.forEach(link => link.classList.remove('active'));
            if (event && event.target) event.target.classList.add('active');
            // basic navigation stub
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                const term = event.target.value.toLowerCase();
                const rows = document.querySelectorAll('#ordersTable tr');
                rows.forEach(r => {
                    const text = r.innerText.toLowerCase();
                    r.style.display = text.includes(term) ? '' : 'none';
                });
            }
        }

        function filterOrders() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#ordersTable tr');
            rows.forEach(r => {
                const status = r.getAttribute('data-status');
                r.style.display = (filter === 'all' || filter === status) ? '' : 'none';
            });
        }

        function viewOrder(id) {
            alert('Viewing order #' + id);
        }

        function updateStatus(id) {
            alert('Update status for order #' + id);
        }

        function exportOrders() {
            alert('Exporting orders to CSV (not implemented)');
        }

        function prevPage(){alert('Prev page');}
        function nextPage(){alert('Next page');}

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                alert('Logging out...');
            }
        }
    </script>
</body>
</html>
