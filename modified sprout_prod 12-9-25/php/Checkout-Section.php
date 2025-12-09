<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Page</title>
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
   <link rel="stylesheet" href="../css/checkout-sec.css">
</head>
<body>

     <!-- Header -->
     <header class="header-main">
        <div class="logo">
            <a href="../php/Landing-Page-Section.php">SPROUT PRODUCTIONS</a>
            <img src="../images/sprout logo bg-removed 3.png" alt="">
        </div>

        <nav>
            <ul class="nav-menu">
                <li><a href="../php/New-Arrival-Section.php">New Arrivals</a></li>
                <li><a href="../php/Best-Sellers-Section.php">Best Sellers</a></li>
                <li><a href="../php/Limited-Time-Offers.php">Limited-Time Offers</a></li>
            </ul>
        </nav>

        <div class="header-right">
            <div class="search-bar">
                <img src="../images/Search_logo.png" alt="">
                <input type="text" placeholder="Search for products...">
            </div>
            <div class="header-icons">
                <div class="icon-placeholder">
                    <img src="../images/cart_logo.png" alt="">
                </div>
                <div class="icon-placeholder">
                    <img src="../images/user_logo.png" alt="">
                </div>
            </div>
        </div>
    </header>

    <div class="container">

        <!-- Delivery Address -->
        <div class="delivery-address">
            <h1>Delivery Address</h1>
            <div class="address-info">
                <span>Full Name</span>
                <span>Contact Number</span>
                <span>Street House, Bldg, House no.</span>
                <span>Barangay, City, Province, Region</span>
                <span>Postal Code</span>
                <button class="change-btn">Change</button>
            </div>
        </div>

        <!-- Main Checkout Grid -->
        <div class="checkout-grid">
            <!-- Left Column - Cart -->
            <div class="cart-section">
                <h2>Your Cart</h2>
                
                <!-- Cart Item 1 -->
                <div class="cart-item">
                    <img src="../images/new-arrival-section/Gradient.png" alt="Gradient Graphic T-shirt" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3 class="cart-item-name">Gradient Graphic T-shirt</h3>
                        <p class="cart-item-size">Size: Large</p>
                        <p class="cart-item-price">₱145</p>
                    </div>
                    <div class="cart-item-actions">
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decreaseQuantity(this)">−</button>
                            <span class="quantity-display">1</span>
                            <button class="quantity-btn" onclick="increaseQuantity(this)">+</button>
                        </div>
                        <button class="delete-btn" onclick="deleteItem(this)">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Cart Item 2 -->
                <div class="cart-item">
                    <img src="../images/new-arrival-section/CHECKERED SHIRT.png" alt="Checkered Shirt" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3 class="cart-item-name">Checkered Shirt</h3>
                        <p class="cart-item-size">Size: Medium</p>
                        <p class="cart-item-price">₱180</p>
                    </div>
                    <div class="cart-item-actions">
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decreaseQuantity(this)">−</button>
                            <span class="quantity-display">1</span>
                            <button class="quantity-btn" onclick="increaseQuantity(this)">+</button>
                        </div>
                        <button class="delete-btn" onclick="deleteItem(this)">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Cart Item 3 -->
                <div class="cart-item">
                    <img src="../images/new-arrival-section/SkinnyFitJeans.png" alt="Skinny Fit Jeans" class="cart-item-image">
                    <div class="cart-item-details">
                        <h3 class="cart-item-name">Skinny Fit Jeans</h3>
                        <p class="cart-item-size">Size: Large</p>
                        <p class="cart-item-price">₱240</p>
                    </div>
                    <div class="cart-item-actions">
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decreaseQuantity(this)">−</button>
                            <span class="quantity-display">1</span>
                            <button class="quantity-btn" onclick="increaseQuantity(this)">+</button>
                        </div>
                        <button class="delete-btn" onclick="deleteItem(this)">
                            <svg class="icon" viewBox="0 0 24 24">
                                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column - Payment & Summary -->
            <div class="payment-summary">
                <!-- Payment Method -->
                <div class="card">
                    <h2>Payment Method</h2>
                    <div class="payment-methods">
                        <button class="payment-method-btn active" onclick="selectPayment(this)">Cash on Delivery</button>
                        <button class="payment-method-btn" onclick="selectPayment(this)">Gcash</button>
                        <button class="payment-method-btn" onclick="selectPayment(this)">Bank Account</button>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card">
                    <h2>Order Summary</h2>
                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value" id="subtotal">₱565</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Discount (-20%)</span>
                        <span class="summary-value discount" id="discount">-₱113</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Delivery Fee</span>
                        <span class="summary-value">₱15</span>
                    </div>
                    <hr class="summary-divider">
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="total">₱467</span>
                    </div>
                    <button class="proceed-btn">
                        PROCEED TO ORDER
                        <svg class="icon" viewBox="0 0 24 24">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Shop Voucher -->
        <div class="voucher-section">
            <div class="voucher-left">
                <div class="voucher-icon">%</div>
                <span class="voucher-text">Shop Voucher</span>
            </div>
            <button class="select-voucher-btn">Select Voucher</button>
        </div>
    </div>

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
        function selectPayment(button) {
            // Remove active class from all payment buttons
            document.querySelectorAll('.payment-method-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            // Add active class to clicked button
            button.classList.add('active');
        }

        function increaseQuantity(button) {
            const quantityDisplay = button.parentElement.querySelector('.quantity-display');
            let quantity = parseInt(quantityDisplay.textContent);
            quantity++;
            quantityDisplay.textContent = quantity;
            updateTotal();
        }

        function decreaseQuantity(button) {
            const quantityDisplay = button.parentElement.querySelector('.quantity-display');
            let quantity = parseInt(quantityDisplay.textContent);
            if (quantity > 1) {
                quantity--;
                quantityDisplay.textContent = quantity;
                updateTotal();
            }
        }

        function deleteItem(button) {
            const cartItem = button.closest('.cart-item');
            cartItem.remove();
            updateTotal();
        }

        function updateTotal() {
            const cartItems = document.querySelectorAll('.cart-item');
            let subtotal = 0;

            cartItems.forEach(item => {
                const priceText = item.querySelector('.cart-item-price').textContent;
                const price = parseInt(priceText.replace('₱', ''));
                const quantity = parseInt(item.querySelector('.quantity-display').textContent);
                subtotal += price * quantity;
            });

            const discount = Math.round(subtotal * 0.2);
            const deliveryFee = 15;
            const total = subtotal - discount + deliveryFee;

            document.getElementById('subtotal').textContent = '₱' + subtotal;
            document.getElementById('discount').textContent = '-₱' + discount;
            document.getElementById('total').textContent = '₱' + total;
        }
    </script>
</body>
</html>
