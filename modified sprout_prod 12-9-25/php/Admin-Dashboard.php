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
        
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
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
            <h1 class="dashboard-title">Dashboard Overview</h1>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Total Orders Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Total Orders</h3>
                        <div class="chart-value">1,247</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up">↑ 12% from last month</span>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Revenue</h3>
                        <div class="chart-value">$18,450</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up">↑ 8% from last month</span>
                    </div>
                </div>

                <!-- New Customers Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">New Customers</h3>
                        <div class="chart-value">342</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="customersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up">↑ 15% from last month</span>
                    </div>
                </div>

                <!-- Active Products Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Active Products</h3>
                        <div class="chart-value">89</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="change-up">↑ 3% from last month</span>
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

        // Chart configurations
        document.addEventListener('DOMContentLoaded', function() {
            // Chart color scheme
            const chartColors = {
                primary: '#8B4513',      // Brown
                secondary: '#6B3410',    // Darker brown
                accent: '#2e7d32',       // Green
                background: '#f9f9f9',
                grid: '#e0e0e0',
                text: '#333333'
            };

            // Monthly data for all charts
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            // Total Orders Chart
            const ordersCtx = document.getElementById('ordersChart').getContext('2d');
            const ordersChart = new Chart(ordersCtx, {
                type: 'line',
                data: {
                    labels: months.slice(0, 7), // Last 7 months
                    datasets: [{
                        label: 'Orders',
                        data: [980, 1020, 1100, 1150, 1200, 1247, 1300],
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
                options: {
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
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
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
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: months.slice(0, 6), // Last 6 months
                    datasets: [{
                        label: 'Revenue ($)',
                        data: [14500, 15200, 16800, 17450, 18000, 18450],
                        backgroundColor: chartColors.primary,
                        borderColor: chartColors.secondary,
                        borderWidth: 1,
                        borderRadius: 6,
                        hoverBackgroundColor: chartColors.secondary
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
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
                                    return '$' + value.toLocaleString();
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
                }
            });

            // New Customers Chart
            const customersCtx = document.getElementById('customersChart').getContext('2d');
            const customersChart = new Chart(customersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['New Customers', 'Returning Customers'],
                    datasets: [{
                        data: [342, 905],
                        backgroundColor: [
                            chartColors.primary,
                            chartColors.accent
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: chartColors.text,
                                font: {
                                    family: 'Arial, sans-serif',
                                    size: 12
                                },
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });

            // Active Products Chart
            const productsCtx = document.getElementById('productsChart').getContext('2d');
            const productsChart = new Chart(productsCtx, {
                type: 'radar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Active Products',
                        data: [75, 78, 80, 82, 85, 87, 89],
                        backgroundColor: chartColors.primary + '40',
                        borderColor: chartColors.primary,
                        borderWidth: 2,
                        pointBackgroundColor: chartColors.primary,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: false,
                            min: 70,
                            grid: {
                                color: chartColors.grid
                            },
                            angleLines: {
                                color: chartColors.grid
                            },
                            pointLabels: {
                                color: chartColors.text,
                                font: {
                                    family: 'Arial, sans-serif'
                                }
                            },
                            ticks: {
                                color: chartColors.text,
                                font: {
                                    family: 'Arial, sans-serif'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Update charts on window resize
            window.addEventListener('resize', function() {
                ordersChart.resize();
                revenueChart.resize();
                customersChart.resize();
                productsChart.resize();
            });
        });
    </script>
</body>
</html>