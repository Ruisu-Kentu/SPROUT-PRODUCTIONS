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

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$dbname = "sprout_productions";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch customers with their order stats
$query = "
    SELECT 
        u.id,
        u.email,
        u.created_at,
        COALESCE(ua.full_name, 'No Name Provided') as full_name,
        COUNT(o.id) as order_count,
        COALESCE(SUM(
            CASE 
                WHEN o.status != 'cancelled' 
                AND (
                    (LOWER(o.payment_method) IN ('gcash', 'bank transfer', 'bank_transfer', 'bank') 
                     AND LOWER(o.status) IN ('pending', 'processing', 'preparing', 'shipped', 'delivered', 'completed'))
                    OR 
                    (LOWER(o.payment_method) IN ('cod', 'cash on delivery') 
                     AND LOWER(o.status) IN ('delivered', 'completed'))
                )
                THEN o.total_amount 
                ELSE 0 
            END
        ), 0) as total_spent,
        MAX(CASE WHEN ua.is_default = 1 THEN CONCAT(ua.street_address, ', ', ua.barangay, ', ', ua.city, ', ', ua.province, ' ', ua.postal_code) ELSE NULL END) as address,
        MAX(CASE WHEN ua.is_default = 1 THEN ua.phone_number ELSE NULL END) as phone_number
    FROM users u
    LEFT JOIN user_addresses ua ON u.id = ua.user_id
    LEFT JOIN orders o ON u.id = o.user_id
    GROUP BY u.id, u.email, u.created_at, ua.full_name
    ORDER BY u.created_at DESC
";

$result = $conn->query($query);

// Prepare data for CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer ID', 'Name', 'Email', 'Phone', 'Address', 'Orders', 'Total Spent', 'Status', 'Join Date']);
    
    if ($result) {
        $result->data_seek(0);
        while ($customer = $result->fetch_assoc()) {
            $status = $customer['total_spent'] > 0 ? 'Active' : 'Inactive';
            fputcsv($output, [
                '#' . $customer['id'],
                $customer['full_name'],
                $customer['email'],
                $customer['phone_number'] ?: 'N/A',
                $customer['address'] ?: 'No address',
                $customer['order_count'],
                '$' . number_format($customer['total_spent'], 2),
                $status,
                date('M d, Y', strtotime($customer['created_at']))
            ]);
        }
    }
    fclose($output);
    exit();
}

// Handle customer update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_customer') {
        $customer_id = intval($_POST['customer_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $address = $conn->real_escape_string($_POST['address']);
        
        // Update user email
        $update_user = $conn->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
        $update_user->bind_param("si", $email, $customer_id);
        $update_user->execute();
        
        // Try to update default address or insert new one
        $check_address = $conn->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND is_default = 1");
        $check_address->bind_param("i", $customer_id);
        $check_address->execute();
        $address_result = $check_address->get_result();
        
        if ($address_result->num_rows > 0) {
            $update_address = $conn->prepare("UPDATE user_addresses SET full_name = ?, phone_number = ?, street_address = ?, updated_at = NOW() WHERE user_id = ? AND is_default = 1");
            $update_address->bind_param("sssi", $full_name, $phone, $address, $customer_id);
            $update_address->execute();
        } else {
            $insert_address = $conn->prepare("INSERT INTO user_addresses (user_id, full_name, phone_number, street_address, is_default, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
            $insert_address->bind_param("isss", $customer_id, $full_name, $phone, $address);
            $insert_address->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        exit();
    }
}

// Fetch single customer for modal
if (isset($_GET['get_customer']) && is_numeric($_GET['get_customer'])) {
    $customer_id = intval($_GET['get_customer']);
    
    $customer_query = "
    SELECT 
        u.id,
        u.email,
        u.created_at,
        COALESCE(ua.full_name, 'No Name Provided') as full_name,
        COUNT(o.id) as order_count,
        COALESCE(SUM(
            CASE 
                WHEN o.status != 'cancelled' 
                AND (
                    (LOWER(o.payment_method) IN ('gcash', 'bank transfer', 'bank_transfer', 'bank') 
                     AND LOWER(o.status) IN ('pending', 'processing', 'preparing', 'shipped', 'delivered', 'completed'))
                    OR 
                    (LOWER(o.payment_method) IN ('cod', 'cash on delivery') 
                     AND LOWER(o.status) IN ('delivered', 'completed'))
                )
                THEN o.total_amount 
                ELSE 0 
            END
        ), 0) as total_spent,
        COALESCE(ua.phone_number, 'N/A') as phone_number,
        COALESCE(CONCAT(ua.street_address, ', ', ua.barangay, ', ', ua.city, ', ', ua.province, ' ', ua.postal_code), 'No address provided') as address,
        ua.region,
        ua.province,
        ua.city,
        ua.barangay,
        ua.postal_code,
        ua.street_address
    FROM users u
    LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.id = ?
    GROUP BY u.id, u.email, u.created_at, ua.full_name, ua.phone_number, ua.region, ua.province, ua.city, ua.barangay, ua.postal_code, ua.street_address
";
    
    $stmt = $conn->prepare($customer_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer_details = $stmt->get_result()->fetch_assoc();
    
    // Fetch customer orders
    $orders_query = "
        SELECT 
            o.id,
            o.order_number,
            o.total_amount,
            o.status,
            o.payment_method,
            o.created_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ";
    
    $stmt2 = $conn->prepare($orders_query);
    $stmt2->bind_param("i", $customer_id);
    $stmt2->execute();
    $orders_result = $stmt2->get_result();
    $orders = [];
    while ($order = $orders_result->fetch_assoc()) {
        $orders[] = $order;
    }
    
    echo json_encode([
        'customer' => $customer_details,
        'orders' => $orders
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Customers - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-uniform.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background-color: #8B4513;
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .close-modal:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .customer-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            padding: 8px 12px;
            background: #f9f9f9;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .orders-section {
            margin-top: 30px;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .orders-table th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #8B4513;
            color: #8B4513;
        }

        .orders-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .orders-table tr:hover {
            background-color: #f9f9f9;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #8B4513;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #8B4513;
            color: white;
        }

        .btn-primary:hover {
            background-color: #6B3410;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background-color: #E8F5E9;
            color: #4CAF50;
        }

        .status-inactive {
            background-color: #FFF3E0;
            color: #FF9800;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading i {
            font-size: 48px;
            color: #8B4513;
            margin-bottom: 20px;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 120px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 3000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notification.success {
            background-color: #4CAF50;
        }

        .notification.error {
            background-color: #f44336;
        }

        /* Admin Welcome */
        .admin-welcome {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-icon-small {
            width: 20px;
            height: 20px;
        }

        .admin-text {
            color: white;
            font-weight: 500;
        }

        /* Quick Action Button */
        .quick-action-btn {
            background-color: #8B4513;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }

        .quick-action-btn:hover {
            background-color: #6B3410;
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            margin-right: 8px;
        }

        /* Search Box */
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .customer-info-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-body {
                padding: 20px;
            }
        }

        /* Simple table styling */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
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
                            <li><a href="Admin-Dashboard.php">Dashboard</a></li>
                            <li><a href="../php/Admin-Products-Page.php">Products</a></li>
                            <li><a href="../php/Admin-Orders-Page.php">Orders</a></li>
                            <li><a href="../php/Admin-Customers-Page.php" class="active">Customers</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- Notification Area -->
    <div id="notification" class="notification"></div>

    <div class="container">
        <h1 class="dashboard-title">Customers</h1>

        <div class="content-section">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <div>
                    <label for="statusFilter" style="font-weight:600;margin-right:10px;color:#8B4513;">Filter by:</label>
                    <select id="statusFilter" onchange="filterCustomers()" style="padding:8px 15px;border:2px solid #8B4513;border-radius:6px;background:white;color:#333;font-weight:500;">
                        <option value="all">All Customers</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
                <div>
                    <button class="quick-action-btn" onclick="exportCSV()">
                        <i class="fas fa-file-export"></i>Export CSV
                    </button>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchBox" class="search-box" placeholder="Search customers by name, email, or ID..." onkeyup="searchCustomers()">
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Customer ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTable">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($customer = $result->fetch_assoc()): ?>
                                <?php 
                                $status = $customer['total_spent'] > 0 ? 'Active' : 'Inactive';
                                $status_text = $customer['total_spent'] > 0 ? 'Active' : 'Inactive';
                                ?>
                                <tr data-status="<?php echo $status; ?>" data-search="<?php echo strtolower(htmlspecialchars($customer['full_name'] . ' ' . $customer['email'] . ' ' . $customer['id'])); ?>">
                                    <td>#<?php echo $customer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo $customer['phone_number'] ? htmlspecialchars($customer['phone_number']) : 'N/A'; ?></td>
                                    <td><?php echo $customer['order_count']; ?></td>
                                    <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="action-btn" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="action-btn" onclick="editCustomer(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;">
                                    <i class="fas fa-users" style="font-size:48px;color:#ddd;margin-bottom:20px;"></i>
                                    <p style="color:#666;">No customers found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Customer Modal -->
    <div id="viewCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Customer Details</h2>
                <button class="close-modal" onclick="closeModal('viewCustomerModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewCustomerContent">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading customer details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Customer</h2>
                <button class="close-modal" onclick="closeModal('editCustomerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editCustomerForm">
                    <input type="hidden" id="edit_customer_id" name="customer_id">
                    <input type="hidden" name="action" value="update_customer">
                    
                    <div class="customer-info-grid">
                        <div class="form-group">
                            <label for="edit_full_name">Full Name</label>
                            <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email">Email Address</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_phone">Phone Number</label>
                            <input type="tel" id="edit_phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="edit_address">Address</label>
                            <textarea id="edit_address" name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editCustomerModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Close modal function
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // View customer details
        function viewCustomer(customerId) {
            const modal = document.getElementById('viewCustomerModal');
            const content = document.getElementById('viewCustomerContent');
            
            content.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading customer details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            fetch(`?get_customer=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    const customer = data.customer;
                    const orders = data.orders;
                    
                    content.innerHTML = `
                        <div class="customer-info-grid">
                            <div class="info-group">
                                <span class="info-label">Customer ID</span>
                                <div class="info-value">#${customer.id}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Full Name</span>
                                <div class="info-value">${customer.full_name}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Email Address</span>
                                <div class="info-value">${customer.email}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Phone Number</span>
                                <div class="info-value">${customer.phone_number}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Total Orders</span>
                                <div class="info-value">${customer.order_count}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Total Spent</span>
                                <div class="info-value">$${parseFloat(customer.total_spent).toFixed(2)}</div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Account Status</span>
                                <div class="info-value">
                                    <span class="status-badge ${customer.total_spent > 0 ? 'status-active' : 'status-inactive'}">
                                        ${customer.total_spent > 0 ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Member Since</span>
                                <div class="info-value">${new Date(customer.created_at).toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'long', 
                                    day: 'numeric' 
                                })}</div>
                            </div>
                            <div class="info-group" style="grid-column: span 2;">
                                <span class="info-label">Address</span>
                                <div class="info-value">${customer.address}</div>
                            </div>
                        </div>
                        
                        ${orders.length > 0 ? `
                            <div class="orders-section">
                                <h3 style="margin-bottom:15px;color:#8B4513;">Recent Orders</h3>
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${orders.map(order => `
                                            <tr>
                                                <td>${order.order_number}</td>
                                                <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                                <td>${order.item_count} items</td>
                                                <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
                                                <td>
                                                    <span class="status-badge ${order.status === 'completed' ? 'status-active' : 'status-inactive'}">
                                                        ${order.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<p style="text-align:center;color:#666;padding:20px;">No orders found for this customer.</p>'}
                    `;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div style="text-align:center;padding:40px;color:#f44336;">
                            <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:20px;"></i>
                            <p>Error loading customer details. Please try again.</p>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        }

        // Edit customer
        function editCustomer(customerId) {
            const modal = document.getElementById('editCustomerModal');
            const form = document.getElementById('editCustomerForm');
            
            // Fetch customer data
            fetch(`?get_customer=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    const customer = data.customer;
                    
                    document.getElementById('edit_customer_id').value = customer.id;
                    document.getElementById('edit_full_name').value = customer.full_name;
                    document.getElementById('edit_email').value = customer.email;
                    document.getElementById('edit_phone').value = customer.phone_number;
                    document.getElementById('edit_address').value = customer.address || '';
                    
                    modal.style.display = 'block';
                })
                .catch(error => {
                    showNotification('Error loading customer data', 'error');
                });
        }

        // Handle edit form submission
        document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editCustomerModal');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message || 'Error updating customer', 'error');
                }
            })
            .catch(error => {
                showNotification('Error updating customer', 'error');
            });
        });

        // Filter customers by status
        function filterCustomers() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#customersTable tr[data-status]');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.style.display !== 'none') { // Only consider rows not hidden by search
                    if (filter === 'all' || row.getAttribute('data-status') === filter) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
            
            if (visibleCount === 0) {
                showNotification('No customers found with selected filter', 'error');
            }
        }

        // Search customers
        function searchCustomers() {
            const searchTerm = document.getElementById('searchBox').value.toLowerCase();
            const rows = document.querySelectorAll('#customersTable tr[data-status]');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (searchTerm && visibleCount === 0) {
                showNotification('No customers found matching your search', 'error');
            }
        }

        // Export CSV
        function exportCSV() {
            window.location.href = '?export=csv';
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>