<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-dash.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="#" class="active" onclick="navigate('analytics')">Analytics</a></li>
                <li><a href="../php/Admin-Settings-Page.php" onclick="navigate('settings')">Settings</a></li>
            </ul>
            <div class="nav-search">
                <input type="text" class="search-box" placeholder="Filter charts..." onkeyup="handleChartFilter(event)">
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="dashboard-title">Analytics</h1>

        <div class="content-section">
            <h2 class="section-title">Sales (Monthly)</h2>
            <canvas id="salesChart" style="max-height:360px"></canvas>
        </div>

        <div class="stats-grid" style="margin-top:18px;">
            <div class="stat-card" style="flex:1;min-width:300px;">
                <h3>Customers (Monthly)</h3>
                <canvas id="customersChart" style="max-height:240px"></canvas>
            </div>
            <div class="stat-card" style="flex:1;min-width:300px;">
                <h3>Orders by Status</h3>
                <canvas id="ordersStatusChart" style="max-height:240px"></canvas>
            </div>
            <div class="stat-card" style="flex:1;min-width:300px;">
                <h3>Category Breakdown</h3>
                <canvas id="categoryChart" style="max-height:240px"></canvas>
            </div>
        </div>
    </div>

    <script>
        let salesChart, customersChart, ordersStatusChart, categoryChart;

        async function loadAnalytics(){
            try {
                const res = await fetch('analytics-data.php');
                const data = await res.json();
                renderSales(data.salesByDate || []);
                renderCustomers(data.customersByMonth || []);
                renderOrdersStatus(data.ordersByStatus || {});
                renderCategories(data.categoryBreakdown || {});
            } catch (err) {
                console.error('Failed to load analytics', err);
                alert('Failed to load analytics data.');
            }
        }

        function renderSales(records){
            const labels = records.map(r => r.label);
            const values = records.map(r => r.value);
            const ctx = document.getElementById('salesChart').getContext('2d');
            if (salesChart) salesChart.destroy();
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue',
                        data: values,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76,175,80,0.1)',
                        fill: true,
                        tension: 0.25
                    }]
                },
                options: { responsive: true }
            });
        }

        function renderCustomers(records){
            const labels = records.map(r => r.label);
            const values = records.map(r => r.value);
            const ctx = document.getElementById('customersChart').getContext('2d');
            if (customersChart) customersChart.destroy();
            customersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ label: 'New Customers', data: values, backgroundColor: '#2196F3' }]
                },
                options: { responsive: true }
            });
        }

        function renderOrdersStatus(obj){
            const labels = Object.keys(obj);
            const values = Object.values(obj);
            const colors = ['#FFC107','#2196F3','#4CAF50','#F44336'];
            const ctx = document.getElementById('ordersStatusChart').getContext('2d');
            if (ordersStatusChart) ordersStatusChart.destroy();
            ordersStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: values, backgroundColor: colors }] },
                options: { responsive: true }
            });
        }

        function renderCategories(obj){
            const labels = Object.keys(obj);
            const values = Object.values(obj);
            const colors = ['#3F51B5','#009688','#FF5722','#9C27B0'];
            const ctx = document.getElementById('categoryChart').getContext('2d');
            if (categoryChart) categoryChart.destroy();
            categoryChart = new Chart(ctx, {
                type: 'pie',
                data: { labels: labels, datasets: [{ data: values, backgroundColor: colors }] },
                options: { responsive: true }
            });
        }

        function handleChartFilter(event){
            // simple filter stub if you want later to implement interactive filters
            if (event.key === 'Enter') {
                alert('Chart filter: ' + event.target.value + ' (not implemented)');
            }
        }

        function toggleMenu(){const navLinks = document.getElementById('navLinks');navLinks.classList.toggle('active');}
        function navigate(section){const links=document.querySelectorAll('.nav-links a');links.forEach(l=>l.classList.remove('active')); if(event&&event.target) event.target.classList.add('active');}
        function logout(){ if(confirm('Are you sure you want to logout?')){ alert('Logging out...'); } }

        loadAnalytics();
    </script>
</body>
</html>
