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
    <title>Admin Dashboard - Sprout Productions</title>
    <link rel="stylesheet" href="../css/admin-dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../images/sprout logo bg-removed 3.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Chart container styles */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #eee;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .chart-title {
            font-family: 'Georgia', serif;
            font-size: 20px;
            color: #8B4513;
            margin: 0;
        }
        
        .chart-value {
            font-size: 28px;
            font-weight: bold;
            color: #2e7d32;
            font-family: 'Georgia', serif;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .chart-change {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .change-up {
            color: #4CAF50;
        }
        
        .change-down {
            color: #f44336;
        }
        
        /* Chart Filter Styles */
        .chart-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-label {
            font-weight: 600;
            color: #8B4513;
            font-family: 'Georgia', serif;
        }
        
        .filter-btn {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-family: 'Arial', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            color: #555;
        }
        
        .filter-btn:hover {
            background: #e8e8e8;
            border-color: #8B4513;
        }
        
        .filter-btn.active {
            background: #8B4513;
            color: white;
            border-color: #8B4513;
        }
        
        .filter-group {
            display: flex;
            gap: 5px;
            background: #f9f9f9;
            padding: 5px;
            border-radius: 25px;
        }
        
        .chart-filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-filter-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
            
            .chart-value {
                font-size: 24px;
            }
            
            .chart-card {
                padding: 20px;
            }
            
            .filter-group {
                flex-wrap: wrap;
            }
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
                            <li><a href="Admin-Dashboard.php" class="active">Dashboard</a></li>
                            <li><a href="../php/Admin-Products-Page.php">Products</a></li>
                            <li><a href="../php/Admin-Orders-Page.php">Orders</a></li>
                            <li><a href="../php/Admin-Customers-Page.php">Customers</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="chart-filter-section">
                <h1 class="dashboard-title" id="dashboardOverview">Dashboard Overview</h1>
                
                <!-- Chart Filter -->
                <div class="chart-filter">
                    <span class="filter-label">View by:</span>
                    <div class="filter-group">
                        <button class="filter-btn active" data-filter="week">Week</button>
                        <button class="filter-btn" data-filter="month">Month</button>
                        <button class="filter-btn" data-filter="year">Year</button>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Total Orders Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Total Orders</h3>
                        <div class="chart-value" id="ordersValue">1,247</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up" id="ordersChange">↑ 12% from last period</span>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Revenue</h3>
                        <div class="chart-value" id="revenueValue">$18,450</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up" id="revenueChange">↑ 8% from last period</span>
                    </div>
                </div>

                <!-- Customers Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Customers</h3>
                        <div class="chart-value" id="customersValue">905</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="customersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up" id="customersChange">↑ 15% from last period</span>
                    </div>
                </div>

                <!-- Sold Products Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Sold Products</h3>
                        <div class="chart-value" id="productsValue">1,128</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up" id="productsChange">↑ 3% from last period</span>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Recent Orders</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <tr>
                                <td>#10247</td>
                                <td>Sarah Johnson</td>
                                <td>Organic Seed Kit</td>
                                <td>$49.99</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>Nov 28, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10247')">View</button>
                                    <button class="action-btn" onclick="editOrder('10247')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10246</td>
                                <td>Michael Chen</td>
                                <td>Garden Tool Set</td>
                                <td>$89.99</td>
                                <td><span class="status-badge status-active">Processing</span></td>
                                <td>Nov 28, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10246')">View</button>
                                    <button class="action-btn" onclick="editOrder('10246')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10245</td>
                                <td>Emily Rodriguez</td>
                                <td>Plant Food Bundle</td>
                                <td>$34.99</td>
                                <td><span class="status-badge status-pending">Pending</span></td>
                                <td>Nov 27, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10245')">View</button>
                                    <button class="action-btn" onclick="editOrder('10245')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10244</td>
                                <td>David Kim</td>
                                <td>Composting Kit</td>
                                <td>$64.99</td>
                                <td><span class="status-badge status-completed">Completed</span></td>
                                <td>Nov 27, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10244')">View</button>
                                    <button class="action-btn" onclick="editOrder('10244')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>#10243</td>
                                <td>Lisa Anderson</td>
                                <td>Indoor Herb Garden</td>
                                <td>$79.99</td>
                                <td><span class="status-badge status-active">Processing</span></td>
                                <td>Nov 26, 2025</td>
                                <td>
                                    <button class="action-btn" onclick="viewOrder('10243')">View</button>
                                    <button class="action-btn" onclick="editOrder('10243')">Edit</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Top Selling Products</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Organic Seed Collection</td>
                                <td>Seeds</td>
                                <td>342</td>
                                <td>$8,550</td>
                                <td>156</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('1')">View</button>
                                    <button class="action-btn" onclick="editProduct('1')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Premium Garden Tools</td>
                                <td>Tools</td>
                                <td>287</td>
                                <td>$14,350</td>
                                <td>89</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('2')">View</button>
                                    <button class="action-btn" onclick="editProduct('2')">Edit</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Composting Starter Kit</td>
                                <td>Composting</td>
                                <td>198</td>
                                <td>$9,900</td>
                                <td>45</td>
                                <td>
                                    <button class="action-btn" onclick="viewProduct('3')">View</button>
                                    <button class="action-btn" onclick="editProduct('3')">Edit</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Dashboard functions
        function addProduct() {
            window.location.href = '../php/Admin-Products-Page.php';
        }

        function viewOrders() {
            window.location.href = '../php/Admin-Orders-Page.php';
        }

        function manageCustomers() {
            window.location.href = '../php/Admin-Customers-Page.php';
        }

        function generateReport() {
            alert('Generate Report functionality would open here');
        }

        function viewOrder(orderId) {
            window.location.href = `../php/Admin-Orders-Page.php?id=${orderId}`;
        }

        function editOrder(orderId) {
            window.location.href = `../php/Admin-Orders-Page.php?edit=${orderId}`;
        }

        function viewProduct(productId) {
            window.location.href = `../php/Admin-Products-Page.php?id=${productId}`;
        }

        function editProduct(productId) {
            window.location.href = `../php/Admin-Products-Page.php?edit=${productId}`;
        }

        // Data for different time periods
        const chartData = {
            week: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                orders: [165, 180, 192, 210, 195, 205, 100],
                revenue: [2450, 2750, 3120, 2980, 2650, 2890, 1500],
                customers: [85, 92, 105, 98, 110, 115, 60],
                products: [210, 195, 220, 205, 215, 225, 110],
                totals: {
                    orders: 1247,
                    revenue: 18450,
                    customers: 905,
                    products: 1128
                },
                changes: {
                    orders: '↑ 12% from last week',
                    revenue: '↑ 8% from last week',
                    customers: '↑ 15% from last week',
                    products: '↑ 3% from last week'
                }
            },
            month: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                orders: [520, 580, 620, 710],
                revenue: [7800, 8200, 8650, 9200],
                customers: [280, 310, 325, 340],
                products: [620, 680, 720, 780],
                totals: {
                    orders: 2430,
                    revenue: 33850,
                    customers: 1255,
                    products: 2800
                },
                changes: {
                    orders: '↑ 8% from last month',
                    revenue: '↑ 12% from last month',
                    customers: '↑ 18% from last month',
                    products: '↑ 5% from last month'
                }
            },
            year: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                orders: [980, 1020, 1100, 1150, 1200, 1247, 1300, 1350, 1400, 1450, 1500, 1550],
                revenue: [14500, 15200, 16800, 17450, 18000, 18450, 19000, 19500, 20000, 20500, 21000, 21500],
                customers: [720, 780, 820, 850, 880, 905, 930, 950, 980, 1000, 1020, 1040],
                products: [950, 980, 1020, 1050, 1100, 1128, 1150, 1180, 1200, 1220, 1240, 1260],
                totals: {
                    orders: 15647,
                    revenue: 221450,
                    customers: 10835,
                    products: 13478
                },
                changes: {
                    orders: '↑ 15% from last year',
                    revenue: '↑ 22% from last year',
                    customers: '↑ 28% from last year',
                    products: '↑ 10% from last year'
                }
            }
        };

        // Chart instances
        let ordersChart, revenueChart, customersChart, productsChart;
        let currentFilter = 'week';

        // Chart color scheme
        const chartColors = {
            primary: '#8B4513',
            secondary: '#6B3410',
            accent: '#2e7d32',
            background: '#f9f9f9',
            grid: '#e0e0e0',
            text: '#333333'
        };

        // Initialize charts
        function initializeCharts() {
            const data = chartData[currentFilter];
            
            // Orders Chart
            const ordersCtx = document.getElementById('ordersChart').getContext('2d');
            if (ordersChart) ordersChart.destroy();
            ordersChart = new Chart(ordersCtx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Orders',
                        data: data.orders,
                        borderColor: chartColors.primary,
                        backgroundColor: chartColors.primary + '20',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: chartColors.primary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: getChartOptions('Orders')
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            if (revenueChart) revenueChart.destroy();
            revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: data.revenue,
                        backgroundColor: chartColors.primary,
                        borderColor: chartColors.secondary,
                        borderWidth: 1,
                        borderRadius: 6,
                        hoverBackgroundColor: chartColors.secondary
                    }]
                },
                options: getChartOptions('Revenue ($)', true)
            });

            // Customers Chart
            const customersCtx = document.getElementById('customersChart').getContext('2d');
            if (customersChart) customersChart.destroy();
            customersChart = new Chart(customersCtx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Customers',
                        data: data.customers,
                        borderColor: chartColors.accent,
                        backgroundColor: chartColors.accent + '20',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: chartColors.accent,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: getChartOptions('Customers')
            });

            // Products Chart
            const productsCtx = document.getElementById('productsChart').getContext('2d');
            if (productsChart) productsChart.destroy();
            productsChart = new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Sold Products',
                        data: data.products,
                        backgroundColor: chartColors.primary,
                        borderColor: chartColors.secondary,
                        borderWidth: 1,
                        borderRadius: 6,
                        hoverBackgroundColor: chartColors.secondary
                    }]
                },
                options: getChartOptions('Products Sold')
            });

            // Update total values
            updateTotalValues(data.totals, data.changes);
        }

        // Get chart options based on type
        function getChartOptions(label, isCurrency = false) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let value = context.parsed.y;
                                let suffix = '';
                                
                                if (isCurrency) {
                                    value = '$' + value.toLocaleString();
                                    suffix = '';
                                } else {
                                    value = value.toLocaleString();
                                }
                                
                                return label + ': ' + value + suffix;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartColors.grid,
                            drawBorder: false
                        },
                        ticks: {
                            color: chartColors.text,
                            font: {
                                family: 'Arial, sans-serif'
                            },
                            callback: function(value) {
                                if (isCurrency) {
                                    return '$' + value.toLocaleString();
                                }
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: chartColors.grid,
                            drawBorder: false
                        },
                        ticks: {
                            color: chartColors.text,
                            font: {
                                family: 'Arial, sans-serif'
                            }
                        }
                    }
                }
            };
        }

        // Update total values and changes
        function updateTotalValues(totals, changes) {
            document.getElementById('ordersValue').textContent = totals.orders.toLocaleString();
            document.getElementById('revenueValue').textContent = '$' + totals.revenue.toLocaleString();
            document.getElementById('customersValue').textContent = totals.customers.toLocaleString();
            document.getElementById('productsValue').textContent = totals.products.toLocaleString();
            
            document.getElementById('ordersChange').textContent = changes.orders;
            document.getElementById('revenueChange').textContent = changes.revenue;
            document.getElementById('customersChange').textContent = changes.customers;
            document.getElementById('productsChange').textContent = changes.products;
        }

        // Handle filter button clicks
        function setupFilterButtons() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Update current filter
                    currentFilter = this.dataset.filter;
                    
                    // Update charts
                    initializeCharts();
                    
                    // Update dashboard title
                    const periodText = currentFilter === 'week' ? 'Weekly' : 
                                      currentFilter === 'month' ? 'Monthly' : 'Yearly';
                    document.getElementById('dashboardOverview').textContent = `${periodText} Dashboard Overview`;
                });
            });
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            setupFilterButtons();
            
            // Update charts on window resize
            window.addEventListener('resize', function() {
                if (ordersChart) ordersChart.resize();
                if (revenueChart) revenueChart.resize();
                if (customersChart) customersChart.resize();
                if (productsChart) productsChart.resize();
            });
        });
    </script>
</body>
</html>