<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sprout Productions Admin</title>
    <link rel="stylesheet" href="../css/admin-dash.css">
    <style>
        /* Additional styles for Products page */
        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .filter-section select,
        .filter-section input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-section select {
            min-width: 150px;
        }

        .filter-section input {
            flex: 1;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            font-family: Arial, Helvetica, sans-serif;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background-color: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 48px;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            color: #8B4513;
            margin-bottom: 10px;
        }

        .product-category {
            display: inline-block;
            background-color: #f0f0f0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .product-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .product-price {
            font-size: 20px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .product-actions button {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-edit {
            background-color: #8B4513;
            color: white;
        }

        .btn-edit:hover {
            background-color: #A0522D;
        }

        .btn-delete {
            background-color: #d32f2f;
            color: white;
        }

        .btn-delete:hover {
            background-color: #b71c1c;
        }

        .stock-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .stock-in-stock {
            background-color: #c8e6c9;
            color: #2e7d32;
        }

        .stock-low {
            background-color: #fff9c4;
            color: #f57f17;
        }

        .stock-out {
            background-color: #ffcdd2;
            color: #c62828;
        }

        .add-product-modal {
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
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #8B4513;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-save {
            background-color: #8B4513;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-save:hover {
            background-color: #A0522D;
        }

        .btn-cancel {
            background-color: #666;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-cancel:hover {
            background-color: #555;
        }

        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-actions h1 {
            color: #8B4513;
        }
    </style>
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
                <li><a href="../php/Admin-Dashboard.php">Dashboard</a></li>
                <li><a href="../php/Admin-Products-Page.php" class="active" onclick="navigate('products')">Products</a></li>
                <li><a href="../php/Admin-Orders-Page.php" onclick="navigate('orders')">Orders</a></li>
                <li><a href="../php/Admin-Customers-Page.php" onclick="navigate('customers')">Customers</a></li>
                <li><a href="../php/Admin-Analytics-Page.php" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="../php/Admin-Settings-Page.php" onclick="navigate('settings')">Settings</a></li>
            </ul>
            <div class="nav-search">
                <input type="text" class="search-box" placeholder="Search products..." onkeyup="handleSearch(event)">
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-actions">
            <h1>Products Management</h1>
            <button class="quick-action-btn" onclick="openAddProductModal()">+ Add New Product</button>
        </div>

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

        <div class="products-grid" id="productsGrid">
            <!-- Product 1 -->
            <div class="product-card" data-category="tshirts" data-stock="in-stock">
                <div class="product-image">👕
                    <!-- pwede pani ma butangan img src, ilisan ang emogi -->
                </div>
                <div class="product-name">T-Shirt 1</div>
                <span class="product-category">T-Shirts</span>
                <div class="product-details">
                    <span>Stock: <span class="stock-status stock-in-stock">156 pcs</span></span>
                </div>
                <div class="product-price">₱25.00</div>
                <div class="product-details">
                    <span>TS: T-001</span>
                    <span>Sales: 342</span>
                </div>
                <div class="product-actions">
                    <button class="btn-edit" onclick="editProduct(1)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(1)">Delete</button>
                </div>
            </div>

            <!-- Product 2 -->
            <div class="product-card" data-category="hoodies" data-stock="in-stock">
                <div class="product-image">🧥</div>
                <div class="product-name">Hoodies 1</div>
                <span class="product-category">Hoodies</span>
                <div class="product-details">
                    <span>Stock: <span class="stock-status stock-in-stock">89 psc</span></span>
                </div>
                <div class="product-price">₱50.00</div>
                <div class="product-details">
                    <span>SKU: H-001</span>
                    <span>Sales: 287</span>
                </div>
                <div class="product-actions">
                    <button class="btn-edit" onclick="editProduct(2)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(2)">Delete</button>
                </div>
            </div>

            <!-- Product 3 -->
            <div class="product-card" data-category="jeans" data-stock="low">
                <div class="product-image">👖</div>
                <div class="product-name">Jeans 1</div>
                <span class="product-category">Jeans</span>
                <div class="product-details">
                    <span>Stock: <span class="stock-status stock-low">15 pcs</span></span>
                </div>
                <div class="product-price">₱50.00</div>
                <div class="product-details">
                    <span>JH: J-001</span>
                    <span>Sales: 198</span>
                </div>
                <div class="product-actions">
                    <button class="btn-edit" onclick="editProduct(3)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(3)">Delete</button>
                </div>
            </div>

            <!-- Product 4 -->
            <div class="product-card" data-category="tshirts" data-stock="in-stock">
                <div class="product-image">👔</div>
                <div class="product-name">T-Shirt 2</div>
                <span class="product-category">T-Shirt</span>
                <div class="product-details">
                    <span>Stock: <span class="stock-status stock-in-stock">124 pcs</span></span>
                </div>
                <div class="product-price">₱89.99</div>
                <div class="product-details">
                    <span>SKU: T-002</span>
                    <span>Sales: 156</span>
                </div>
                <div class="product-actions">
                    <button class="btn-edit" onclick="editProduct(4)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(4)">Delete</button>
                </div>
            </div>

            <!-- Product 5 -->
            <div class="product-card" data-category="hoodies" data-stock="in-stock">
                <div class="product-image">🧥</div>
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
                    <button class="btn-edit" onclick="editProduct(5)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(5)">Delete</button>
                </div>
            </div>

            <!-- Product 6 -->
            <div class="product-card" data-category="jeans" data-stock="out">
                <div class="product-image">👖</div>
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
                    <button class="btn-edit" onclick="editProduct(6)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(6)">Delete</button>
                </div>
            </div>

              <!-- Product 7 -->
            <div class="product-card" data-category="tshirts" data-stock="in-stock">
                <div class="product-image">👔</div>
                <div class="product-name">T-Shirt 3</div>
                <span class="product-category">T-Shirt</span>
                <div class="product-details">
                    <span>Stock: <span class="stock-status stock-in-stock">124 pcs</span></span>
                </div>
                <div class="product-price">₱89.99</div>
                <div class="product-details">
                    <span>SKU: T-003</span>
                    <span>Sales: 156</span>
                </div>
                <div class="product-actions">
                    <button class="btn-edit" onclick="editProduct(7)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(7)">Delete</button>
                </div>
            </div>

            <!-- Product 8 -->
            <div class="product-card" data-category="hoodies" data-stock="in-stock">
                <div class="product-image">🧥</div>
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
                    <button class="btn-edit" onclick="editProduct(8)">Edit</button>
                    <button class="btn-delete" onclick="deleteProduct(8)">Delete</button>
                </div>
            </div>


  
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
                        <option value="seeds">Seeds</option>
                        <option value="tools">Tools</option>
                        <option value="composting">Composting</option>
                        <option value="fertilizers">Fertilizers</option>
                        <option value="accessories">Accessories</option>
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
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                filterProducts();
            }
        }

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

        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'flex';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('addProductForm').reset();
        }

        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Product added successfully! (This will connect to backend later)');
            closeAddProductModal();
        });

        function editProduct(id) {
            alert('Edit product #' + id + ' (This will open edit modal later)');
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                alert('Product #' + id + ' deleted! (This will connect to backend later)');
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../php/Login-Form.php';
            }
        }
    </script>
</body>
</html>