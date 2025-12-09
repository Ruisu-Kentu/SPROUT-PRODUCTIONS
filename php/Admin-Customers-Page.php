<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Customers - Sprout Productions</title>
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
                <li><a href="../php/Admin-Orders-Page.php" onclick="navigate('orders')">Orders</a></li>
                <li><a href="#" class="active" onclick="navigate('customers')">Customers</a></li>
                <li><a href="../php/Admin-Analytics-Page.php" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="../php/Admin-Settings-Page.php" onclick="navigate('settings')">Settings</a></li>
            </ul>
            <div class="nav-search">
                <input type="text" class="search-box" placeholder="Search customers..." onkeyup="handleSearch(event)">
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="dashboard-title">Customers</h1>

        <div class="content-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                    <label for="statusFilter">Account:</label>
                    <select id="statusFilter" onchange="filterCustomers()">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
                <div>
                    <button class="quick-action-btn" onclick="exportCustomers()">Export CSV</button>
                    <button class="quick-action-btn" onclick="addCustomer()">+ Add Customer</button>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTable">
                        <tr data-status="active">
                            <td>#C1001</td>
                            <td>Maria Santos</td>
                            <td>maria@example.com</td>
                            <td>3</td>
                            <td>$149.97</td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <button class="action-btn" onclick="viewCustomer('C1001')">View</button>
                                <button class="action-btn" onclick="editCustomer('C1001')">Edit</button>
                            </td>
                        </tr>
                        <tr data-status="inactive">
                            <td>#C1000</td>
                            <td>James Lee</td>
                            <td>james@example.com</td>
                            <td>0</td>
                            <td>$0.00</td>
                            <td><span class="status-badge status-pending">Inactive</span></td>
                            <td>
                                <button class="action-btn" onclick="viewCustomer('C1000')">View</button>
                                <button class="action-btn" onclick="editCustomer('C1000')">Edit</button>
                            </td>
                        </tr>
                        <tr data-status="banned">
                            <td>#C0999</td>
                            <td>Olivia Brown</td>
                            <td>olivia@example.com</td>
                            <td>1</td>
                            <td>$24.99</td>
                            <td><span class="status-badge status-pending">Banned</span></td>
                            <td>
                                <button class="action-btn" onclick="viewCustomer('C0999')">View</button>
                                <button class="action-btn" onclick="banCustomer('C0999')">Unban</button>
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
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                const term = event.target.value.toLowerCase();
                const rows = document.querySelectorAll('#customersTable tr');
                rows.forEach(r => {
                    const text = r.innerText.toLowerCase();
                    r.style.display = text.includes(term) ? '' : 'none';
                });
            }
        }

        function filterCustomers() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#customersTable tr');
            rows.forEach(r => {
                const status = r.getAttribute('data-status');
                r.style.display = (filter === 'all' || filter === status) ? '' : 'none';
            });
        }

        function viewCustomer(id) { alert('Viewing customer ' + id); }
        function editCustomer(id) { alert('Editing customer ' + id); }
        function banCustomer(id) { alert('Toggling ban for ' + id); }
        function addCustomer(){ alert('Add customer form (not implemented)'); }
        function exportCustomers(){ alert('Exporting customers (not implemented)'); }
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
