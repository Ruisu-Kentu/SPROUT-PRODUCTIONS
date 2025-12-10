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
    <title>Products Management - Sprout Productions Admin</title>
    <link rel="stylesheet" href="../css/admin-products-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
</head>
<body>
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
    <!-- Fixed Header -->
    <header class="sticky-header">
        <!-- Top Bar with User Info and Logout -->
        <div class="top-bar">
            <div class="container">
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

            <!-- Page Actions -->
            <div class="page-actions">
                <button class="quick-action-btn" onclick="openAddProductModal()">+ Add New Product</button>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <select id="categoryFilter" onchange="filterProducts()">
                    <option value="all">All Categories</option>
                    <option value="tshirts">T-Shirts</option>
                    <option value="hoodies">Hoodies</option>
                    <option value="jeans">Jeans</option>
                </select>
                <select id="stockFilter" onchange="filterProducts()">
                    <option value="all">All Stock Status</option>
                    <option value="in-stock">In Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                </select>
                <input type="text" id="searchProducts" placeholder="Search by product name..." oninput="filterProducts()">
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <!-- Product 1 -->
                <div class="product-card" data-category="tshirts" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/tshirt-sample.jpg" alt="T-Shirt 1">
                    </div>
                    <div class="product-name">T-Shirt 1</div>
                    <span class="product-category">T-Shirts</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">156 pcs</span></span>
                    </div>
                    <div class="product-price">₱25.00</div>
                    <div class="product-details">
                        <span>SKU: T-001</span>
                        <span>Sales: 342</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(1)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(1)">Delete</button>
                    </div>
                </div>

                <!-- Product 2 -->
                <div class="product-card" data-category="hoodies" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/hoodie-sample.jpg" alt="Hoodies 1">
                    </div>
                    <div class="product-name">Hoodies 1</div>
                    <span class="product-category">Hoodies</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">89 pcs</span></span>
                    </div>
                    <div class="product-price">₱50.00</div>
                    <div class="product-details">
                        <span>SKU: H-001</span>
                        <span>Sales: 287</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(2)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(2)">Delete</button>
                    </div>
                </div>

                <!-- Product 3 -->
                <div class="product-card" data-category="jeans" data-stock="low">
                    <div class="product-image">
                        <img src="../images/jeans-sample.jpg" alt="Jeans 1">
                    </div>
                    <div class="product-name">Jeans 1</div>
                    <span class="product-category">Jeans</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-low">15 pcs</span></span>
                    </div>
                    <div class="product-price">₱50.00</div>
                    <div class="product-details">
                        <span>SKU: J-001</span>
                        <span>Sales: 198</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(3)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(3)">Delete</button>
                    </div>
                </div>

                <!-- Product 4 -->
                <div class="product-card" data-category="tshirts" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/tshirt-sample2.jpg" alt="T-Shirt 2">
                    </div>
                    <div class="product-name">T-Shirt 2</div>
                    <span class="product-category">T-Shirts</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">124 pcs</span></span>
                    </div>
                    <div class="product-price">₱89.99</div>
                    <div class="product-details">
                        <span>SKU: T-002</span>
                        <span>Sales: 156</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(4)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(4)">Delete</button>
                    </div>
                </div>

                <!-- Product 5 -->
                <div class="product-card" data-category="hoodies" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/hoodie-sample2.jpg" alt="Hoodies 2">
                    </div>
                    <div class="product-name">Hoodies 2</div>
                    <span class="product-category">Hoodies</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">98 pcs</span></span>
                    </div>
                    <div class="product-price">₱34.99</div>
                    <div class="product-details">
                        <span>SKU: H-002</span>
                        <span>Sales: 234</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(5)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(5)">Delete</button>
                    </div>
                </div>

                <!-- Product 6 -->
                <div class="product-card" data-category="jeans" data-stock="out">
                    <div class="product-image">
                        <img src="../images/jeans-sample2.jpg" alt="Jeans 2">
                    </div>
                    <div class="product-name">Jeans 2</div>
                    <span class="product-category">Jeans</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-out">0 pcs</span></span>
                    </div>
                    <div class="product-price">₱49.99</div>
                    <div class="product-details">
                        <span>SKU: J-002</span>
                        <span>Sales: 412</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(6)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(6)">Delete</button>
                    </div>
                </div>

                <!-- Product 7 -->
                <div class="product-card" data-category="tshirts" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/tshirt-sample3.jpg" alt="T-Shirt 3">
                    </div>
                    <div class="product-name">T-Shirt 3</div>
                    <span class="product-category">T-Shirts</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">124 pcs</span></span>
                    </div>
                    <div class="product-price">₱89.99</div>
                    <div class="product-details">
                        <span>SKU: T-003</span>
                        <span>Sales: 156</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(7)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(7)">Delete</button>
                    </div>
                </div>

                <!-- Product 8 -->
                <div class="product-card" data-category="hoodies" data-stock="in-stock">
                    <div class="product-image">
                        <img src="../images/hoodie-sample3.jpg" alt="Hoodies 3">
                    </div>
                    <div class="product-name">Hoodies 3</div>
                    <span class="product-category">Hoodies</span>
                    <div class="product-details">
                        <span>Stock: <span class="stock-status stock-in-stock">98 pcs</span></span>
                    </div>
                    <div class="product-price">₱34.99</div>
                    <div class="product-details">
                        <span>SKU: H-003</span>
                        <span>Sales: 234</span>
                    </div>
                    <div class="product-actions">
                        <button class="action-btn" onclick="editProduct(8)">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteProduct(8)">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Product Modal -->
    <div class="add-product-modal" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button class="close-modal" onclick="closeAddProductModal()">&times;</button>
            </div>
            <form id="addProductForm">
                <div class="form-group">
                    <label for="productName">Product Name *</label>
                    <input type="text" id="productName" required>
                </div>
                <div class="form-group">
                    <label for="productCategory">Category *</label>
                    <select id="productCategory" required>
                        <option value="">Select Category</option>
                        <option value="tshirts">T-Shirts</option>
                        <option value="hoodies">Hoodies</option>
                        <option value="jeans">Jeans</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="productPrice">Price *</label>
                    <input type="number" id="productPrice" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="productStock">Stock Quantity *</label>
                    <input type="number" id="productStock" required>
                </div>
                <div class="form-group">
                    <label for="productSKU">SKU *</label>
                    <input type="text" id="productSKU" required>
                </div>
                <div class="form-group">
                    <label for="productDescription">Description</label>
                    <textarea id="productDescription"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddProductModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter Products
        function filterProducts() {
            const category = document.getElementById('categoryFilter').value;
            const stock = document.getElementById('stockFilter').value;
            const searchTerm = document.getElementById('searchProducts').value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');

            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                const cardStock = card.getAttribute('data-stock');
                const cardName = card.querySelector('.product-name').textContent.toLowerCase();

                const categoryMatch = category === 'all' || cardCategory === category;
                const stockMatch = stock === 'all' || cardStock === stock;
                const searchMatch = cardName.includes(searchTerm);

                if (categoryMatch && stockMatch && searchMatch) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Modal Functions
        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'flex';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('addProductForm').reset();
        }

        // Form Submission
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Product added successfully! (This will connect to backend later)');
            closeAddProductModal();
        });

        // Product Actions
        function editProduct(id) {
            alert('Edit product #' + id + ' (This will open edit modal later)');
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                alert('Product #' + id + ' deleted! (This will connect to backend later)');
            }
        }
    </script>
</body>
</html>