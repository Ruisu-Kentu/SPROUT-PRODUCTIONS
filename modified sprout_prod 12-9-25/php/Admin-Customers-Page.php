<?php
// Start session and authentication check
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<script>
        alert("⚠️ ADMIN ACCESS REQUIRED\\n\\nPlease log in as an administrator first!");
        window.location.href = "Login-Form.php";
    </script>';
    exit();
}

// Check if user is admin
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    echo '<script>
        alert("⛔ ACCESS DENIED\\n\\nYou don\'t have administrator privileges!");
        window.location.href = "Landing-Page-Section.php";
    </script>';
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
    <title>Admin Customers - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-uniform.css">
  
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
                            <li><a href="Admin-Dashboard.php" >Dashboard</a></li>
                            <li><a href="../php/Admin-Products-Page.php">Products</a></li>
                            <li><a href="../php/Admin-Orders-Page.php">Orders</a></li>
                            <li><a href="../php/Admin-Customers-Page.php" class="active">Customers</a></li>
                        </ul>
                    </nav>

                  
                </div>
            </div>
        </div>
    </header>

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
