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

// Handle AJAX requests for order and product details
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_order_details' && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $order_id = intval($_GET['id']);
        
        // Get order details
        $stmt = $conn->prepare("
            SELECT 
                o.*,
                u.email as customer_email,
                GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            // Set customer_name as email since we don't have username
            $order['customer_name'] = $order['customer_email'];
            echo json_encode($order);
        } else {
            echo json_encode(['error' => 'Order not found']);
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
    
    if ($_GET['action'] == 'get_product_details' && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $product_id = intval($_GET['id']);
        
        // Get product details
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                (p.price * p.sold_count) as total_revenue
            FROM products p
            WHERE p.id = ?
        ");
        
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            echo json_encode($product);
        } else {
            echo json_encode(['error' => 'Product not found']);
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
}

// Function to get dashboard statistics - UPDATED: Revenue only from delivered/completed orders
function getDashboardStats($conn, $period) {
    $stats = [];
    $today = date('Y-m-d');
    
    switch($period) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $prev_start = date('Y-m-d', strtotime('-14 days'));
            $prev_end = date('Y-m-d', strtotime('-8 days'));
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $prev_start = date('Y-m-d', strtotime('-60 days'));
            $prev_end = date('Y-m-d', strtotime('-31 days'));
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            $prev_start = date('Y-m-d', strtotime('-730 days'));
            $prev_end = date('Y-m-d', strtotime('-366 days'));
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $prev_start = date('Y-m-d', strtotime('-14 days'));
            $prev_end = date('Y-m-d', strtotime('-8 days'));
    }
    
    // Get current period stats
    // Total Orders (all orders regardless of status)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE created_at >= ?");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_orders = $result->fetch_assoc();
    $stats['current']['orders'] = $current_orders['count'] ?? 0;
    $stmt->close();
    
    // Total Revenue (ONLY from delivered/completed orders)
    $stmt = $conn->prepare("SELECT SUM(total_amount) as revenue FROM orders WHERE status IN ('completed', 'delivered') AND created_at >= ?");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_revenue = $result->fetch_assoc();
    $stats['current']['revenue'] = $current_revenue['revenue'] ?? 0;
    $stmt->close();
    
    // Total Customers (unique users who placed orders)
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM orders WHERE created_at >= ?");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_customers = $result->fetch_assoc();
    $stats['current']['customers'] = $current_customers['count'] ?? 0;
    $stmt->close();
    
    // Total Products Sold from order_items (ONLY from delivered/completed orders)
    $stmt = $conn->prepare("
        SELECT SUM(oi.quantity) as total_sold 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.status IN ('completed', 'delivered') AND o.created_at >= ?
    ");
    $stmt->bind_param("s", $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_products = $result->fetch_assoc();
    $stats['current']['products'] = $current_products['total_sold'] ?? 0;
    $stmt->close();
    
    // Get previous period stats for comparison
    // Previous Orders (all orders)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE created_at >= ? AND created_at <= ?");
    $stmt->bind_param("ss", $prev_start, $prev_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $prev_orders = $result->fetch_assoc();
    $stats['previous']['orders'] = $prev_orders['count'] ?? 0;
    $stmt->close();
    
    // Previous Revenue (ONLY from delivered/completed orders)
    $stmt = $conn->prepare("SELECT SUM(total_amount) as revenue FROM orders WHERE status IN ('completed', 'delivered') AND created_at >= ? AND created_at <= ?");
    $stmt->bind_param("ss", $prev_start, $prev_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $prev_revenue = $result->fetch_assoc();
    $stats['previous']['revenue'] = $prev_revenue['revenue'] ?? 0;
    $stmt->close();
    
    // Previous Customers
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM orders WHERE created_at >= ? AND created_at <= ?");
    $stmt->bind_param("ss", $prev_start, $prev_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $prev_customers = $result->fetch_assoc();
    $stats['previous']['customers'] = $prev_customers['count'] ?? 0;
    $stmt->close();
    
    // Previous Products Sold (ONLY from delivered/completed orders)
    $stmt = $conn->prepare("
        SELECT SUM(oi.quantity) as total_sold 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.status IN ('completed', 'delivered') AND o.created_at >= ? AND o.created_at <= ?
    ");
    $stmt->bind_param("ss", $prev_start, $prev_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $prev_products = $result->fetch_assoc();
    $stats['previous']['products'] = $prev_products['total_sold'] ?? 0;
    $stmt->close();
    
    // Calculate percentage changes
    foreach(['orders', 'revenue', 'customers', 'products'] as $metric) {
        $current = $stats['current'][$metric] ?? 0;
        $previous = $stats['previous'][$metric] ?? 0;
        
        if ($previous > 0) {
            $change = (($current - $previous) / $previous) * 100;
        } else {
            $change = ($current > 0) ? 100 : 0;
        }
        
        $stats['changes'][$metric] = [
            'value' => round($change, 1),
            'direction' => $change >= 0 ? 'up' : 'down',
            'text' => ($change >= 0 ? '↑' : '↓') . ' ' . abs(round($change, 1)) . '% from last period'
        ];
    }
    
    return $stats;
}

// Function to get chart data - UPDATED: Revenue only from delivered/completed orders
function getChartData($conn, $period) {
    $data = [];
    
    switch($period) {
        case 'week':
            // Last 7 days
            $labels = [];
            $orders_data = [];
            $revenue_data = [];
            $customers_data = [];
            $products_data = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $day_label = date('D', strtotime($date));
                $labels[] = $day_label;
                
                // Get orders for this day - Revenue ONLY from delivered/completed
                $next_date = date('Y-m-d', strtotime($date . ' +1 day'));
                
                // Total orders for this day (all statuses)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as orders
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $date, $next_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $orders_data[] = $row['orders'] ?? 0;
                $stmt->close();
                
                // Revenue for this day (ONLY delivered/completed)
                $stmt = $conn->prepare("
                    SELECT SUM(total_amount) as revenue
                    FROM orders 
                    WHERE status IN ('completed', 'delivered') 
                    AND created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $date, $next_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $revenue_data[] = $row['revenue'] ?? 0;
                $stmt->close();
                
                // Customers for this day
                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT user_id) as customers
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $date, $next_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $customers_data[] = $row['customers'] ?? 0;
                $stmt->close();
                
                // Products sold for this day (ONLY from delivered/completed)
                $stmt = $conn->prepare("
                    SELECT COALESCE(SUM(oi.quantity), 0) as products
                    FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE o.status IN ('completed', 'delivered') 
                    AND o.created_at >= ? AND o.created_at < ?
                ");
                $stmt->bind_param("ss", $date, $next_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $products_data[] = $row['products'] ?? 0;
                $stmt->close();
            }
            
            $data = [
                'labels' => $labels,
                'orders' => $orders_data,
                'revenue' => $revenue_data,
                'customers' => $customers_data,
                'products' => $products_data
            ];
            break;
            
        case 'month':
            // Last 4 weeks
            $labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            $orders_data = [];
            $revenue_data = [];
            $customers_data = [];
            $products_data = [];
            
            for ($i = 3; $i >= 0; $i--) {
                $week_start = date('Y-m-d', strtotime("-$i weeks"));
                $week_end = date('Y-m-d', strtotime($week_start . ' +1 week'));
                
                // Total orders for this week (all statuses)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as orders
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $week_start, $week_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $orders_data[] = $row['orders'] ?? 0;
                $stmt->close();
                
                // Revenue for this week (ONLY delivered/completed)
                $stmt = $conn->prepare("
                    SELECT SUM(total_amount) as revenue
                    FROM orders 
                    WHERE status IN ('completed', 'delivered') 
                    AND created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $week_start, $week_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $revenue_data[] = $row['revenue'] ?? 0;
                $stmt->close();
                
                // Customers for this week
                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT user_id) as customers
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $week_start, $week_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $customers_data[] = $row['customers'] ?? 0;
                $stmt->close();
                
                // Products sold for this week (ONLY from delivered/completed)
                $stmt = $conn->prepare("
                    SELECT COALESCE(SUM(oi.quantity), 0) as products
                    FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE o.status IN ('completed', 'delivered') 
                    AND o.created_at >= ? AND o.created_at < ?
                ");
                $stmt->bind_param("ss", $week_start, $week_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $products_data[] = $row['products'] ?? 0;
                $stmt->close();
            }
            
            $data = [
                'labels' => $labels,
                'orders' => $orders_data,
                'revenue' => $revenue_data,
                'customers' => $customers_data,
                'products' => $products_data
            ];
            break;
            
        case 'year':
            // Last 12 months
            $labels = [];
            $orders_data = [];
            $revenue_data = [];
            $customers_data = [];
            $products_data = [];
            
            for ($i = 11; $i >= 0; $i--) {
                $month_start = date('Y-m-01', strtotime("-$i months"));
                $month_end = date('Y-m-01', strtotime($month_start . ' +1 month'));
                $month_label = date('M', strtotime($month_start));
                $labels[] = $month_label;
                
                // Total orders for this month (all statuses)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as orders
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $month_start, $month_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $orders_data[] = $row['orders'] ?? 0;
                $stmt->close();
                
                // Revenue for this month (ONLY delivered/completed)
                $stmt = $conn->prepare("
                    SELECT SUM(total_amount) as revenue
                    FROM orders 
                    WHERE status IN ('completed', 'delivered') 
                    AND created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $month_start, $month_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $revenue_data[] = $row['revenue'] ?? 0;
                $stmt->close();
                
                // Customers for this month
                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT user_id) as customers
                    FROM orders 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->bind_param("ss", $month_start, $month_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $customers_data[] = $row['customers'] ?? 0;
                $stmt->close();
                
                // Products sold for this month (ONLY from delivered/completed)
                $stmt = $conn->prepare("
                    SELECT COALESCE(SUM(oi.quantity), 0) as products
                    FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE o.status IN ('completed', 'delivered') 
                    AND o.created_at >= ? AND o.created_at < ?
                ");
                $stmt->bind_param("ss", $month_start, $month_end);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $products_data[] = $row['products'] ?? 0;
                $stmt->close();
            }
            
            $data = [
                'labels' => $labels,
                'orders' => $orders_data,
                'revenue' => $revenue_data,
                'customers' => $customers_data,
                'products' => $products_data
            ];
            break;
    }
    
    return $data;
}

// Get recent orders (limit 10, newest first)
function getRecentOrders($conn) {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.total_amount,
            o.status,
            o.created_at,
            o.payment_receipt,
            u.email as customer_email,
            GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Add customer_name as email for display
        $row['customer_name'] = $row['customer_email'];
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

// Get top selling products (only where sold_count > 0)
function getTopSellingProducts($conn) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            category,
            price,
            stock,
            sold_count,
            (price * sold_count) as revenue
        FROM products
        WHERE sold_count > 0
        ORDER BY sold_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
    return $products;
}

// Get current period from GET or default to 'week'
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
if (!in_array($period, ['week', 'month', 'year'])) {
    $period = 'week';
}

// Get all data
$stats = getDashboardStats($conn, $period);
$chartData = getChartData($conn, $period);
$recentOrders = getRecentOrders($conn);
$topProducts = getTopSellingProducts($conn);

// Format data for JavaScript
$jsStats = [
    'week' => [
        'labels' => $chartData['labels'],
        'orders' => $chartData['orders'],
        'revenue' => $chartData['revenue'],
        'customers' => $chartData['customers'],
        'products' => $chartData['products'],
        'totals' => [
            'orders' => $stats['current']['orders'],
            'revenue' => $stats['current']['revenue'],
            'customers' => $stats['current']['customers'],
            'products' => $stats['current']['products']
        ],
        'changes' => [
            'orders' => $stats['changes']['orders']['text'],
            'revenue' => $stats['changes']['revenue']['text'],
            'customers' => $stats['changes']['customers']['text'],
            'products' => $stats['changes']['products']['text']
        ]
    ]
];

// Get data for other periods if needed
$monthStats = getDashboardStats($conn, 'month');
$monthChartData = getChartData($conn, 'month');
$yearStats = getDashboardStats($conn, 'year');
$yearChartData = getChartData($conn, 'year');

$jsStats['month'] = [
    'labels' => $monthChartData['labels'],
    'orders' => $monthChartData['orders'],
    'revenue' => $monthChartData['revenue'],
    'customers' => $monthChartData['customers'],
    'products' => $monthChartData['products'],
    'totals' => [
        'orders' => $monthStats['current']['orders'],
        'revenue' => $monthStats['current']['revenue'],
        'customers' => $monthStats['current']['customers'],
        'products' => $monthStats['current']['products']
    ],
    'changes' => [
        'orders' => $monthStats['changes']['orders']['text'],
        'revenue' => $monthStats['changes']['revenue']['text'],
        'customers' => $monthStats['changes']['customers']['text'],
        'products' => $monthStats['changes']['products']['text']
    ]
];

$jsStats['year'] = [
    'labels' => $yearChartData['labels'],
    'orders' => $yearChartData['orders'],
    'revenue' => $yearChartData['revenue'],
    'customers' => $yearChartData['customers'],
    'products' => $yearChartData['products'],
    'totals' => [
        'orders' => $yearStats['current']['orders'],
        'revenue' => $yearStats['current']['revenue'],
        'customers' => $yearStats['current']['customers'],
        'products' => $yearStats['current']['products']
    ],
    'changes' => [
        'orders' => $yearStats['changes']['orders']['text'],
        'revenue' => $yearStats['changes']['revenue']['text'],
        'customers' => $yearStats['changes']['customers']['text'],
        'products' => $yearStats['changes']['products']['text']
    ]
];

// Don't close connection yet as we might need it for AJAX requests
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
        
        /* Scrollable tables */
        .scrollable-table-container {
            overflow-x: auto;
            position: relative;
            padding-bottom: 10px;
        }
        
        .scrollable-table {
            min-width: 100%;
            display: table;
        }
        
        .scroll-indicator {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 20px;
            background: linear-gradient(to right, transparent, rgba(139, 69, 19, 0.1));
            pointer-events: none;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #000;
        }
        
        .modal-title {
            color: #8B4513;
            margin-bottom: 20px;
            font-family: 'Georgia', serif;
        }
        
        .modal-section {
            margin-bottom: 20px;
        }
        
        .modal-label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .modal-value {
            color: #333;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        /* Email styling in tables */
        .email-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Revenue badge for completed orders */
        .revenue-badge {
            background-color: #E8F5E9;
            color: #4CAF50;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Receipt preview styles */
        .receipt-preview {
            margin-top: 10px;
        }
        
        .receipt-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .receipt-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .receipt-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        /* Fullscreen receipt modal */
        .fullscreen-modal {
            display: none;
            position: fixed;
            z-index: 1002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.95);
            justify-content: center;
            align-items: center;
        }
        
        .fullscreen-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .close-fullscreen {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            background: none;
            border: none;
            z-index: 1003;
        }
        
        .close-fullscreen:hover {
            color: #ff6666;
        }
        
        .no-receipt {
            color: #999;
            font-style: italic;
            font-size: 14px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            text-align: center;
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
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
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
            
            .modal-content {
                padding: 20px;
                width: 95%;
            }
            
            .email-cell {
                max-width: 100px;
            }
            
            .fullscreen-image {
                max-width: 95%;
                max-height: 80%;
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
                        <button class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>" data-filter="week">Week</button>
                        <button class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>" data-filter="month">Month</button>
                        <button class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>" data-filter="year">Year</button>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Total Orders Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Total Orders</h3>
                        <div class="chart-value" id="ordersValue"><?php echo number_format($stats['current']['orders']); ?></div>
                    </div>
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="<?php echo $stats['changes']['orders']['direction'] == 'up' ? 'change-up' : 'change-down'; ?>" id="ordersChange">
                            <?php echo $stats['changes']['orders']['text']; ?>
                        </span>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Revenue</h3>
                        <div class="chart-value" id="revenueValue">$<?php echo number_format($stats['current']['revenue'], 2); ?></div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="<?php echo $stats['changes']['revenue']['direction'] == 'up' ? 'change-up' : 'change-down'; ?>" id="revenueChange">
                            <?php echo $stats['changes']['revenue']['text']; ?>
                        </span>
                    </div>
                </div>

                <!-- Customers Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Customers</h3>
                        <div class="chart-value" id="customersValue"><?php echo number_format($stats['current']['customers']); ?></div>
                    </div>
                    <div class="chart-container">
                        <canvas id="customersChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="<?php echo $stats['changes']['customers']['direction'] == 'up' ? 'change-up' : 'change-down'; ?>" id="customersChange">
                            <?php echo $stats['changes']['customers']['text']; ?>
                        </span>
                    </div>
                </div>

                <!-- Sold Products Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Sold Products</h3>
                        <div class="chart-value" id="productsValue"><?php echo number_format($stats['current']['products']); ?></div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                    <div class="chart-change">
                        <span class="<?php echo $stats['changes']['products']['direction'] == 'up' ? 'change-up' : 'change-down'; ?>" id="productsChange">
                            <?php echo $stats['changes']['products']['text']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Recent Orders</h2>
                <div class="scrollable-table-container">
                    <div class="scroll-indicator"></div>
                    <table class="scrollable-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Email</th>
                                <th>Products</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTable">
                            <?php if (count($recentOrders) > 0): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td class="email-cell"><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($order['products'], 0, 50)) . (strlen($order['products']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            $<?php echo number_format($order['total_amount'], 2); ?>
                                            <?php if (in_array(strtolower($order['status']), ['completed', 'delivered'])): ?>
                                                <span class="revenue-badge">Revenue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = '';
                                            switch(strtolower($order['status'])) {
                                                case 'completed': 
                                                case 'delivered': 
                                                    $statusClass = 'status-completed'; 
                                                    break;
                                                case 'processing': 
                                                    $statusClass = 'status-active'; 
                                                    break;
                                                case 'pending': 
                                                    $statusClass = 'status-pending'; 
                                                    break;
                                                default: 
                                                    $statusClass = 'status-pending';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <button class="action-btn view-order-btn" data-order-id="<?php echo $order['id']; ?>">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">No recent orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-section">
                <h2 class="section-title">Top Selling Products</h2>
                <div class="scrollable-table-container">
                    <div class="scroll-indicator"></div>
                    <table class="scrollable-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($topProducts) > 0): ?>
                                <?php foreach ($topProducts as $product): ?>
                                    <?php if ($product['sold_count'] > 0): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                                            <td><?php echo number_format($product['sold_count']); ?></td>
                                            <td>$<?php echo number_format($product['revenue'], 2); ?></td>
                                            <td><?php echo number_format($product['stock']); ?></td>
                                            <td>
                                                <button class="action-btn view-product-btn" data-product-id="<?php echo $product['id']; ?>">View</button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">No top selling products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">Order Details</h2>
            <div id="orderModalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="modal-title">Product Details</h2>
            <div id="productModalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Fullscreen Receipt Modal -->
    <div id="fullscreenReceiptModal" class="fullscreen-modal">
        <button class="close-fullscreen">&times;</button>
        <img id="fullscreenReceiptImage" class="fullscreen-image" src="" alt="Payment Receipt">
    </div>

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

        // Data for different time periods (now from PHP)
        const chartData = <?php echo json_encode($jsStats); ?>;

        // Chart instances
        let ordersChart, revenueChart, customersChart, productsChart;
        let currentFilter = '<?php echo $period; ?>';

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
                    
                    // Update URL with period parameter
                    const url = new URL(window.location);
                    url.searchParams.set('period', currentFilter);
                    window.history.pushState({}, '', url);
                    
                    // Reload page to get new data for this period
                    window.location.href = url.toString();
                });
            });
        }

        // Modal functionality
        function setupModals() {
            const orderModal = document.getElementById('orderModal');
            const productModal = document.getElementById('productModal');
            const fullscreenModal = document.getElementById('fullscreenReceiptModal');
            const closeButtons = document.querySelectorAll('.close-modal');
            const closeFullscreen = document.querySelector('.close-fullscreen');
            
            // Close modals when clicking X
            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    orderModal.style.display = 'none';
                    productModal.style.display = 'none';
                });
            });
            
            // Close fullscreen modal
            closeFullscreen.addEventListener('click', function() {
                fullscreenModal.style.display = 'none';
            });
            
            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === orderModal) {
                    orderModal.style.display = 'none';
                }
                if (event.target === productModal) {
                    productModal.style.display = 'none';
                }
                if (event.target === fullscreenModal) {
                    fullscreenModal.style.display = 'none';
                }
            });
            
            // Escape key to close modals
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    orderModal.style.display = 'none';
                    productModal.style.display = 'none';
                    fullscreenModal.style.display = 'none';
                }
            });
            
            // View order button click handlers
            document.querySelectorAll('.view-order-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    viewOrderDetails(orderId);
                });
            });
            
            // View product button click handlers
            document.querySelectorAll('.view-product-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    viewProductDetails(productId);
                });
            });
        }
        
        // Get proper receipt URL
        function getReceiptUrl(receiptPath) {
            if (!receiptPath) return '';
            
            // Clean the path
            receiptPath = receiptPath.trim().replace(/\\/g, '/');
            
            // If it's already a full URL, return it
            if (receiptPath.startsWith('http://') || receiptPath.startsWith('https://')) {
                return receiptPath;
            }
            
            // If path starts with uploads/, return it as is
            if (receiptPath.startsWith('uploads/')) {
                return '../' + receiptPath;
            }
            
            // If path contains uploads/receipts, use it
            if (receiptPath.includes('uploads/receipts/')) {
                // Check if it needs a prefix
                if (!receiptPath.startsWith('../')) {
                    return '../' + receiptPath;
                }
                return receiptPath;
            }
            
            // If it's just a filename, put it in the receipts folder
            const filename = receiptPath.split('/').pop();
            if (filename === receiptPath) {
                return '../uploads/receipts/' + filename;
            }
            
            // Default: return as is
            return receiptPath;
        }
        
        // Show fullscreen receipt
        function showFullscreenReceipt(imageUrl) {
            const modal = document.getElementById('fullscreenReceiptModal');
            const image = document.getElementById('fullscreenReceiptImage');
            
            // Clean the URL
            imageUrl = getReceiptUrl(imageUrl);
            
            // Create array of possible paths to try
            const possibleUrls = [
                imageUrl,
                '../' + imageUrl,
                '../../' + imageUrl,
                'uploads/receipts/' + basename(imageUrl),
                '../uploads/receipts/' + basename(imageUrl),
                '../../uploads/receipts/' + basename(imageUrl),
                '../' + basename(imageUrl),
                '../../' + basename(imageUrl)
            ];
            
            // Function to try loading image
            function tryLoadImage(urlIndex) {
                if (urlIndex >= possibleUrls.length) {
                    // All paths failed - show error
                    console.error('All receipt paths failed');
                    image.src = '';
                    image.alt = 'Receipt not found';
                    return;
                }
                
                const currentUrl = possibleUrls[urlIndex];
                
                // Set the image source
                image.src = currentUrl;
                
                // Set up onerror handler to try next URL
                image.onerror = function() {
                    console.log('Failed to load from:', currentUrl);
                    // Try next URL after a short delay
                    setTimeout(() => tryLoadImage(urlIndex + 1), 100);
                };
                
                // Set up onload handler
                image.onload = function() {
                    console.log('Successfully loaded receipt from:', currentUrl);
                };
            }
            
            // Start trying from first URL
            tryLoadImage(0);
            
            // Show modal
            modal.style.display = 'flex';
        }
        
        // Helper function to get basename
        function basename(path) {
            return path.split('/').pop().split('\\').pop();
        }
        
        // View order details
        async function viewOrderDetails(orderId) {
            try {
                const response = await fetch(`Admin-Dashboard.php?action=get_order_details&id=${orderId}`);
                const order = await response.json();
                
                if (order.error) {
                    alert(order.error);
                    return;
                }
                
                const modalContent = document.getElementById('orderModalContent');
                
                // Format date
                const orderDate = new Date(order.created_at);
                const formattedDate = orderDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Build receipt section
                let receiptSection = '';
                if (order.payment_receipt && order.payment_receipt !== 'NULL' && order.payment_receipt.trim() !== '') {
                    const cleanReceiptPath = order.payment_receipt.trim().replace(/\\/g, '/');
                    const receiptUrl = getReceiptUrl(cleanReceiptPath);
                    
                    receiptSection = `
                        <div class="modal-section">
                            <span class="modal-label">Payment Receipt:</span>
                            <div class="receipt-preview">
                                <img src="${receiptUrl}" 
                                    alt="Payment Receipt" 
                                    class="receipt-image"
                                    onclick="showFullscreenReceipt('${cleanReceiptPath.replace(/'/g, "\\'")}')"
                                    onerror="this.onerror=null; this.src=''; this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="no-receipt" style="display: none;">
                                    <i class="fas fa-exclamation-circle"></i> Receipt image not found
                                </div>
                                <p class="receipt-info">
                                    <i class="fas fa-info-circle"></i> Click on the receipt to view fullscreen
                                </p>
                            </div>
                        </div>
                    `;
                } else {
                    receiptSection = `
                        <div class="modal-section">
                            <span class="modal-label">Payment Receipt:</span>
                            <div class="no-receipt">
                                <i class="fas fa-exclamation-circle"></i> No receipt uploaded
                            </div>
                        </div>
                    `;
                }
                
                modalContent.innerHTML = `
                    <div class="modal-section">
                        <span class="modal-label">Order Number:</span>
                        <div class="modal-value">#${order.order_number}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Customer Email:</span>
                        <div class="modal-value">${order.customer_email}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Order Date:</span>
                        <div class="modal-value">${formattedDate}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Status:</span>
                        <div class="modal-value">
                            <span class="status-badge ${order.status === 'completed' || order.status === 'delivered' ? 'status-completed' : 
                                                       order.status === 'processing' ? 'status-active' : 
                                                       'status-pending'}">
                                ${order.status}
                            </span>
                            ${order.status === 'completed' || order.status === 'delivered' ? 
                                '<span class="revenue-badge" style="margin-left: 10px;">Revenue Counted</span>' : ''}
                        </div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Payment Method:</span>
                        <div class="modal-value">${order.payment_method || 'N/A'}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Total Amount:</span>
                        <div class="modal-value">$${parseFloat(order.total_amount).toFixed(2)}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Products:</span>
                        <div class="modal-value">${order.products || 'No products found'}</div>
                    </div>
                    ${receiptSection}
                `;
                
                document.getElementById('orderModal').style.display = 'block';
            } catch (error) {
                console.error('Error loading order details:', error);
                alert('Error loading order details. Please try again.');
            }
        }
        
        // View product details
        async function viewProductDetails(productId) {
            try {
                const response = await fetch(`Admin-Dashboard.php?action=get_product_details&id=${productId}`);
                const product = await response.json();
                
                if (product.error) {
                    alert(product.error);
                    return;
                }
                
                const modalContent = document.getElementById('productModalContent');
                modalContent.innerHTML = `
                    <div class="modal-section">
                        <span class="modal-label">Product Name:</span>
                        <div class="modal-value">${product.name}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Category:</span>
                        <div class="modal-value">${product.category}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Description:</span>
                        <div class="modal-value">${product.description || 'No description available'}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Price:</span>
                        <div class="modal-value">$${parseFloat(product.price).toFixed(2)}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Stock Available:</span>
                        <div class="modal-value">${product.stock}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Units Sold:</span>
                        <div class="modal-value">${product.sold_count}</div>
                    </div>
                    <div class="modal-section">
                        <span class="modal-label">Total Revenue:</span>
                        <div class="modal-value">$${(parseFloat(product.price) * parseInt(product.sold_count)).toFixed(2)}</div>
                    </div>
                    ${product.discount_percent > 0 ? `
                    <div class="modal-section">
                        <span class="modal-label">Discount:</span>
                        <div class="modal-value">${product.discount_percent}% ($${parseFloat(product.discount_price).toFixed(2)})</div>
                    </div>` : ''}
                    ${product.image_path ? `
                    <div class="modal-section">
                        <span class="modal-label">Product Image:</span>
                        <div class="modal-value">
                            <img src="../${product.image_path}" alt="${product.name}" style="max-width: 200px; max-height: 200px;">
                        </div>
                    </div>` : ''}
                `;
                
                document.getElementById('productModal').style.display = 'block';
            } catch (error) {
                console.error('Error loading product details:', error);
                alert('Error loading product details. Please try again.');
            }
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            setupFilterButtons();
            setupModals();
            
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
<?php
// Close the database connection
$conn->close();
?>