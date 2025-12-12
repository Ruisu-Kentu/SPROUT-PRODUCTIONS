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

// Database connection
$host = 'localhost';
$username = 'root'; // Change if different
$password = ''; // Change if you have a password
$database = 'sprout_productions';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Create products table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    description TEXT,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableQuery)) {
    die("Error creating table: " . $conn->error);
}

// Handle form submission for adding product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $productName = trim($_POST['productName']);
        $productCategory = $_POST['productCategory'];
        $productPrice = floatval($_POST['productPrice']);
        $productStock = intval($_POST['productStock']);
        $productDescription = trim($_POST['productDescription']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($productName)) {
            $errors[] = "Product name is required";
        }
        
        if ($productPrice <= 0) {
            $errors[] = "Price must be greater than 0";
        }
        
        if ($productStock < 0) {
            $errors[] = "Stock cannot be negative";
        }
        
        // Handle file upload
        $imagePath = '';
        if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = uniqid('product_', true) . '.' . $fileExtension;
                $targetFile = $uploadDir . $fileName;
                
                // Check file size (max 5MB)
                if ($_FILES['productImage']['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
                        $imagePath = 'uploads/products/' . $fileName;
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                } else {
                    $errors[] = "Image size too large. Maximum size is 5MB";
                }
            } else {
                $errors[] = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed";
            }
        } else {
            $errors[] = "Product image is required";
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO products (name, category, price, stock, description, image_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdiss", $productName, $productCategory, $productPrice, $productStock, $productDescription, $imagePath);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Product added successfully!";
                $stmt->close();
                header("Location: Admin-Products-Page.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error adding product: " . $conn->error;
                if (!empty($imagePath) && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
            $_SESSION['form_data'] = $_POST;
        }
        
        header("Location: Admin-Products-Page.php");
        exit();
    }
    
    // Handle form submission for editing product
    if (isset($_POST['edit_product'])) {
        $productId = intval($_POST['productId']);
        $productName = trim($_POST['productName']);
        $productCategory = $_POST['productCategory'];
        $productPrice = floatval($_POST['productPrice']);
        $productStock = intval($_POST['productStock']);
        $productDescription = trim($_POST['productDescription']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($productName)) {
            $errors[] = "Product name is required";
        }
        
        if ($productPrice <= 0) {
            $errors[] = "Price must be greater than 0";
        }
        
        if ($productStock < 0) {
            $errors[] = "Stock cannot be negative";
        }
        
        // Handle file upload if new image is provided
        $imagePath = '';
        if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['productImage']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = uniqid('product_', true) . '.' . $fileExtension;
                $targetFile = $uploadDir . $fileName;
                
                if ($_FILES['productImage']['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
                        $imagePath = 'uploads/products/' . $fileName;
                        
                        // Delete old image if exists
                        $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
                        $stmt->bind_param("i", $productId);
                        $stmt->execute();
                        $stmt->bind_result($oldImagePath);
                        $stmt->fetch();
                        $stmt->close();
                        
                        if ($oldImagePath && file_exists('../' . $oldImagePath) && !empty($oldImagePath)) {
                            unlink('../' . $oldImagePath);
                        }
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                } else {
                    $errors[] = "Image size too large. Maximum size is 5MB";
                }
            } else {
                $errors[] = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed";
            }
        }
        
        // If no errors, update database
        if (empty($errors)) {
            if (!empty($imagePath)) {
                // Update with new image
                $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock = ?, description = ?, image_path = ? WHERE id = ?");
                $stmt->bind_param("ssdissi", $productName, $productCategory, $productPrice, $productStock, $productDescription, $imagePath, $productId);
            } else {
                // Update without changing image
                $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, stock = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssdisi", $productName, $productCategory, $productPrice, $productStock, $productDescription, $productId);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Product updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating product: " . $conn->error;
                // Delete new image if database update failed
                if (!empty($imagePath) && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        header("Location: Admin-Products-Page.php");
        exit();
    }
}

// Check for messages from session
$success_message = '';
$error_message = '';
$form_data = [];

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}

// Fetch products from database
$products = [];
$query = "SELECT * FROM products ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Handle product deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($imagePath);
    $stmt->fetch();
    $stmt->close();
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if ($imagePath && file_exists('../' . $imagePath) && !empty($imagePath)) {
            unlink('../' . $imagePath);
        }
        $_SESSION['success_message'] = "Product deleted successfully!";
        $stmt->close();
        header("Location: Admin-Products-Page.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error deleting product: " . $conn->error;
        header("Location: Admin-Products-Page.php");
        exit();
    }
}

// Check for GET success messages
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Sprout Productions Admin</title>
    <link rel="stylesheet" href="../css/admin-products-page.css">
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
        
        .alert {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .no-products {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
        }
        
        .no-products p {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .no-products i {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-image:hover img {
            transform: scale(1.05);
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .view-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .view-btn:hover {
            background-color: #1976D2;
        }
        
        .edit-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .edit-btn:hover {
            background-color: #45a049;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        .form-group small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stock-status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
        }
        
        .stock-in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        
        .stock-low {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .stock-out {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #eaeaea;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        
        .modal-close:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-button:hover {
            background: #eaeaea;
        }
        
        .current-image {
            margin-top: 10px;
            text-align: center;
        }
        
        .current-image img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .current-image small {
            display: block;
            margin-top: 5px;
            color: #666;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #eaeaea;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #eaeaea;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #d32f2f, #c62828);
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.2);
        }
        
        /* View Modal Specific */
        .product-view-image {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .product-view-image img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .product-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            padding: 12px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        
        .detail-description {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .detail-description .detail-label {
            margin-bottom: 10px;
        }
        
        /* Quick actions */
        .quick-action-btn {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .quick-action-btn:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
            transform: translateY(-1px);
        }
        
        .page-actions {
            margin-bottom: 20px;
        }
        
        /* Product card enhancements */
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            height: 44px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-category {
            display: inline-block;
            padding: 4px 10px;
            background: #e3f2fd;
            color: #1976D2;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        /* Filter section */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-section select,
        .filter-section input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-section select:focus,
        .filter-section input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .filter-section select {
            min-width: 180px;
        }
        
        .filter-section input {
            flex: 1;
            min-width: 250px;
        }
        
        /* Products grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
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
                            <li><a href="../php/Admin-Products-Page.php" class="active">Products</a></li>
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
            <h1 class="dashboard-title">Products Management</h1>

            <!-- Display success/error messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Page Actions -->
            <div class="page-actions">
                <button class="quick-action-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <select id="categoryFilter" onchange="filterProducts()">
                    <option value="all">All Categories</option>
                    <option value="tshirts">T-Shirts</option>
                    <option value="hoodies">Hoodies</option>
                    <option value="jeans">Jeans</option>
                </select>
                <input type="text" id="searchProducts" placeholder="Search by product name..." oninput="filterProducts()">
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <i class="fas fa-box-open"></i>
                        <p>No products found. Add your first product!</p>
                        <button class="quick-action-btn" onclick="openAddModal()" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add First Product
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        // Determine stock status
                        $stockStatus = 'in-stock';
                        $stockClass = 'stock-in-stock';
                        $stockText = $product['stock'] . ' pcs';
                        
                        if ($product['stock'] <= 0) {
                            $stockStatus = 'out';
                            $stockClass = 'stock-out';
                            $stockText = 'Out of Stock';
                        } elseif ($product['stock'] <= 10) {
                            $stockStatus = 'low';
                            $stockClass = 'stock-low';
                            $stockText = $product['stock'] . ' pcs (Low)';
                        }
                        ?>
                        
                        <div class="product-card" data-category="<?php echo htmlspecialchars($product['category']); ?>" data-stock="<?php echo $stockStatus; ?>">
                            <div class="product-image" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='../images/no-image.jpg'">
                                <?php else: ?>
                                    <img src="../images/no-image.jpg" alt="No Image">
                                <?php endif; ?>
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <span class="product-category"><?php echo ucfirst(htmlspecialchars($product['category'])); ?></span>
                            <div class="product-meta">
                                <span>ID: PROD-<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                <span>Added: <?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                            </div>
                            <div class="product-details">
                                <span>Stock: <span class="stock-status <?php echo $stockClass; ?>"><?php echo $stockText; ?></span></span>
                            </div>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-actions">
                                <button class="action-btn view-btn" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="action-btn edit-btn" onclick="editProduct(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="action-btn delete-btn" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add Product Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form id="addProductForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_product" value="1">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="addProductName">Product Name *</label>
                        <input type="text" id="addProductName" name="productName" required 
                               value="<?php echo isset($form_data['productName']) ? htmlspecialchars($form_data['productName']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="addProductCategory">Category *</label>
                        <select id="addProductCategory" name="productCategory" required>
                            <option value="">Select Category</option>
                            <option value="tshirts" <?php echo (isset($form_data['productCategory']) && $form_data['productCategory'] == 'tshirts') ? 'selected' : ''; ?>>T-Shirts</option>
                            <option value="hoodies" <?php echo (isset($form_data['productCategory']) && $form_data['productCategory'] == 'hoodies') ? 'selected' : ''; ?>>Hoodies</option>
                            <option value="jeans" <?php echo (isset($form_data['productCategory']) && $form_data['productCategory'] == 'jeans') ? 'selected' : ''; ?>>Jeans</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="addProductPrice">Price (₱) *</label>
                        <input type="number" id="addProductPrice" name="productPrice" step="0.01" min="0.01" required 
                               value="<?php echo isset($form_data['productPrice']) ? htmlspecialchars($form_data['productPrice']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="addProductStock">Stock Quantity *</label>
                        <input type="number" id="addProductStock" name="productStock" min="0" required 
                               value="<?php echo isset($form_data['productStock']) ? htmlspecialchars($form_data['productStock']) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="addProductImage">Product Image *</label>
                        <div class="file-input-wrapper">
                            <div class="file-input-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose Image File</span>
                            </div>
                            <input type="file" id="addProductImage" name="productImage" accept="image/*" required onchange="updateFileName(this, 'addFileName')">
                        </div>
                        <small id="addFileName">No file chosen</small>
                        <small style="display: block; margin-top: 5px;">Accepted formats: JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</small>
                    </div>
                    <div class="form-group">
                        <label for="addProductDescription">Description</label>
                        <textarea id="addProductDescription" name="productDescription" rows="3"><?php echo isset($form_data['productDescription']) ? htmlspecialchars($form_data['productDescription']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editProductForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_product" value="1">
                <input type="hidden" id="editProductId" name="productId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editProductName">Product Name *</label>
                        <input type="text" id="editProductName" name="productName" required>
                    </div>
                    <div class="form-group">
                        <label for="editProductCategory">Category *</label>
                        <select id="editProductCategory" name="productCategory" required>
                            <option value="">Select Category</option>
                            <option value="tshirts">T-Shirts</option>
                            <option value="hoodies">Hoodies</option>
                            <option value="jeans">Jeans</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editProductPrice">Price (₱) *</label>
                        <input type="number" id="editProductPrice" name="productPrice" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="editProductStock">Stock Quantity *</label>
                        <input type="number" id="editProductStock" name="productStock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editProductImage">Product Image</label>
                        <div class="file-input-wrapper">
                            <div class="file-input-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose New Image (Optional)</span>
                            </div>
                            <input type="file" id="editProductImage" name="productImage" accept="image/*" onchange="updateFileName(this, 'editFileName')">
                        </div>
                        <small id="editFileName">Keep current image</small>
                        <small style="display: block; margin-top: 5px;">Accepted formats: JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</small>
                        <div class="current-image">
                            <img id="currentProductImage" src="" alt="Current Image">
                            <small>Current Image</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editProductDescription">Description</label>
                        <textarea id="editProductDescription" name="productDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Product Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Product Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="product-view-image">
                    <img id="viewProductImage" src="" alt="Product Image">
                </div>
                <div class="product-details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Product Name</div>
                        <div class="detail-value" id="viewProductName"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Category</div>
                        <div class="detail-value" id="viewProductCategory"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Price</div>
                        <div class="detail-value" id="viewProductPrice"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Stock</div>
                        <div class="detail-value" id="viewProductStock"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Product ID</div>
                        <div class="detail-value" id="viewProductId"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Added Date</div>
                        <div class="detail-value" id="viewProductDate"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value" id="viewProductUpdated"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value" id="viewProductStatus"></div>
                    </div>
                </div>
                <div class="detail-description">
                    <div class="detail-label">Description</div>
                    <div class="detail-value" id="viewProductDescription"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                <button type="button" class="btn btn-primary" onclick="editCurrentProduct()">
                    <i class="fas fa-edit"></i> Edit Product
                </button>
            </div>
        </div>
    </div>

    <script>
        // Products data for JavaScript access
        const productsData = <?php echo json_encode($products); ?>;

        // Filter Products
        function filterProducts() {
            const category = document.getElementById('categoryFilter').value;
            const searchTerm = document.getElementById('searchProducts').value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');

            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                const cardName = card.querySelector('.product-name').textContent.toLowerCase();

                const categoryMatch = category === 'all' || cardCategory === category;
                const searchMatch = cardName.includes(searchTerm);

                if (categoryMatch && searchMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('addProductName').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(productId) {
            const product = productsData.find(p => p.id == productId);
            if (!product) return;

            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductCategory').value = product.category;
            document.getElementById('editProductPrice').value = product.price;
            document.getElementById('editProductStock').value = product.stock;
            document.getElementById('editProductDescription').value = product.description || '';
            
            // Set current image preview
            const currentImage = document.getElementById('currentProductImage');
            if (product.image_path) {
                currentImage.src = '../' + product.image_path;
                currentImage.style.display = 'block';
                currentImage.parentElement.style.display = 'block';
            } else {
                currentImage.style.display = 'none';
                currentImage.parentElement.style.display = 'none';
            }
            
            document.getElementById('editFileName').textContent = 'Keep current image';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function viewProduct(productId) {
            const product = productsData.find(p => p.id == productId);
            if (!product) return;

            // Set view modal content
            document.getElementById('viewProductName').textContent = product.name;
            document.getElementById('viewProductCategory').textContent = product.category.charAt(0).toUpperCase() + product.category.slice(1);
            document.getElementById('viewProductPrice').textContent = '₱' + parseFloat(product.price).toFixed(2);
            
            // Determine stock status
            let stockText = product.stock + ' pcs';
            let statusText = 'In Stock';
            let statusClass = 'stock-in-stock';
            
            if (product.stock <= 0) {
                stockText = 'Out of Stock';
                statusText = 'Out of Stock';
                statusClass = 'stock-out';
            } else if (product.stock <= 10) {
                stockText = product.stock + ' pcs (Low Stock)';
                statusText = 'Low Stock';
                statusClass = 'stock-low';
            }
            
            document.getElementById('viewProductStock').innerHTML = `<span class="stock-status ${statusClass}">${stockText}</span>`;
            document.getElementById('viewProductStatus').innerHTML = `<span class="stock-status ${statusClass}">${statusText}</span>`;
            
            document.getElementById('viewProductId').textContent = 'PROD-' + product.id.toString().padStart(4, '0');
            document.getElementById('viewProductDate').textContent = new Date(product.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
            document.getElementById('viewProductUpdated').textContent = new Date(product.updated_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            document.getElementById('viewProductDescription').textContent = product.description || 'No description provided';
            
            // Set image
            const productImage = document.getElementById('viewProductImage');
            if (product.image_path) {
                productImage.src = '../' + product.image_path;
                productImage.style.display = 'block';
            } else {
                productImage.src = '../images/no-image.jpg';
            }
            
            // Store current product ID for edit button
            document.getElementById('viewModal').dataset.currentProductId = productId;
            
            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function editCurrentProduct() {
            const productId = document.getElementById('viewModal').dataset.currentProductId;
            closeViewModal();
            setTimeout(() => openEditModal(productId), 300);
        }

        // Product Actions
        function editProduct(id) {
            openEditModal(id);
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                window.location.href = 'Admin-Products-Page.php?delete_id=' + id;
            }
        }

        // Helper function to update file name display
        function updateFileName(input, targetId) {
            const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
            document.getElementById(targetId).textContent = fileName;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['addModal', 'editModal', 'viewModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'addModal') closeAddModal();
                    if (modalId === 'editModal') closeEditModal();
                    if (modalId === 'viewModal') closeViewModal();
                }
            });
        }

        // Form validation for add form
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const price = document.getElementById('addProductPrice').value;
            const stock = document.getElementById('addProductStock').value;
            const image = document.getElementById('addProductImage').files[0];
            
            if (parseFloat(price) <= 0) {
                alert('Price must be greater than 0');
                e.preventDefault();
                return;
            }
            
            if (parseInt(stock) < 0) {
                alert('Stock cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (!image) {
                alert('Please select a product image');
                e.preventDefault();
                return;
            }
            
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!validTypes.includes(image.type)) {
                alert('Invalid file type. Please upload an image (JPG, JPEG, PNG, GIF, or WEBP)');
                e.preventDefault();
                return;
            }
            
            if (image.size > maxSize) {
                alert('Image size too large. Maximum size is 5MB');
                e.preventDefault();
                return;
            }
        });

        // Form validation for edit form
        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            const price = document.getElementById('editProductPrice').value;
            const stock = document.getElementById('editProductStock').value;
            const image = document.getElementById('editProductImage').files[0];
            
            if (parseFloat(price) <= 0) {
                alert('Price must be greater than 0');
                e.preventDefault();
                return;
            }
            
            if (parseInt(stock) < 0) {
                alert('Stock cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (image) {
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 5 * 1024 * 1024;
                
                if (!validTypes.includes(image.type)) {
                    alert('Invalid file type. Please upload an image (JPG, JPEG, PNG, GIF, or WEBP)');
                    e.preventDefault();
                    return;
                }
                
                if (image.size > maxSize) {
                    alert('Image size too large. Maximum size is 5MB');
                    e.preventDefault();
                    return;
                }
            }
        });

        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Escape key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('addModal').style.display === 'flex') closeAddModal();
                if (document.getElementById('editModal').style.display === 'flex') closeEditModal();
                if (document.getElementById('viewModal').style.display === 'flex') closeViewModal();
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>