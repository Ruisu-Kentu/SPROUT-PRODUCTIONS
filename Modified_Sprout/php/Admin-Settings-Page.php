<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Sprout Productions</title>
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
                <li><a href="../php/Admin-Customers-Page.php" onclick="navigate('customers')">Customers</a></li>
                <li><a href="../php/Admin-Analytics-Page.php" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="#" class="active" onclick="navigate('settings')">Settings</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 class="dashboard-title">Settings</h1>

        <div class="content-section">
            <h2 class="section-title">Store Information</h2>
            <form id="settingsForm">
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:260px;">
                        <label>Store Name</label>
                        <input type="text" id="storeName" required>
                    </div>
                    <div style="flex:1;min-width:260px;">
                        <label>Contact Email</label>
                        <input type="email" id="storeEmail" required>
                    </div>
                </div>

                <h3 style="margin-top:12px">Payment Methods</h3>
                <div>
                    <label><input type="checkbox" id="pm_card"> Card</label>
                    <label style="margin-left:12px"><input type="checkbox" id="pm_cod"> Cash on Delivery</label>
                    <label style="margin-left:12px"><input type="checkbox" id="pm_paypal"> PayPal</label>
                </div>

                <h3 style="margin-top:12px">Shipping</h3>
                <div>
                    <label>Shipping Fee (PHP)</label>
                    <input type="number" id="shippingFee" step="0.01" min="0">
                </div>

                <h3 style="margin-top:12px">Admin Password</h3>
                <p style="color:#777">Enter a new password to change the admin password (optional).</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;"><label>New Password</label><input type="password" id="newPassword"></div>
                    <div style="flex:1;min-width:200px;"><label>Confirm Password</label><input type="password" id="confirmPassword"></div>
                </div>

                <div style="margin-top:12px;">
                    <button type="submit" class="quick-action-btn">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function fetchSettings(){
            try {
                const res = await fetch('settings-data.php');
                const data = await res.json();
                return data;
            } catch (e) {
                console.error(e);
                alert('Failed to load settings');
            }
        }

        function populateSettings(data){
            document.getElementById('storeName').value = data.storeName || '';
            document.getElementById('storeEmail').value = data.storeEmail || '';
            document.getElementById('pm_card').checked = !!(data.paymentMethods && data.paymentMethods.card);
            document.getElementById('pm_cod').checked = !!(data.paymentMethods && data.paymentMethods.cod);
            document.getElementById('pm_paypal').checked = !!(data.paymentMethods && data.paymentMethods.paypal);
            document.getElementById('shippingFee').value = data.shippingFee || 0;
        }

        document.getElementById('settingsForm').addEventListener('submit', async function(e){
            e.preventDefault();
            const newPwd = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;
            if (newPwd || confirm) {
                if (newPwd !== confirm) { alert('Passwords do not match'); return; }
            }

            const payload = {
                storeName: document.getElementById('storeName').value,
                storeEmail: document.getElementById('storeEmail').value,
                shippingFee: parseFloat(document.getElementById('shippingFee').value) || 0,
                paymentMethods: {
                    card: document.getElementById('pm_card').checked,
                    cod: document.getElementById('pm_cod').checked,
                    paypal: document.getElementById('pm_paypal').checked
                }
            };
            if (newPwd) payload.newAdminPassword = newPwd;

            try {
                const res = await fetch('settings-data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (result.success) {
                    alert('Settings saved');
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                } else {
                    alert('Failed to save settings');
                }
            } catch (err) { console.error(err); alert('Error saving settings'); }
        });

        // Init
        (async function(){
            const data = await fetchSettings();
            if (data) populateSettings(data);
        })();

        function toggleMenu(){const navLinks=document.getElementById('navLinks');navLinks.classList.toggle('active');}
        function navigate(section){const links=document.querySelectorAll('.nav-links a');links.forEach(l=>l.classList.remove('active')); if(event&&event.target) event.target.classList.add('active');}
        function logout(){ if(confirm('Are you sure you want to logout?')){ alert('Logging out...'); } }
    </script>
</body>
</html>
