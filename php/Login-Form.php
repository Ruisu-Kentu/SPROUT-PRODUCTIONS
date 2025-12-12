<?php
session_start();

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$dbname = "sprout_productions";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

$message = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $message = $_SESSION['registration_success'];
    $message_type = 'success';
    unset($_SESSION['registration_success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $message = "Please enter email and password!";
        $message_type = 'error';
    } else {
        // Hardcoded admin credentials
        $admin_email = "admin@gmail.com";
        $admin_password = "admin123";
        
        // Check if it's the hardcoded admin
        if ($email === $admin_email && $password === $admin_password) {
            // Set admin session variables
            $_SESSION['user_id'] = 0; // Special ID for admin
            $_SESSION['email'] = $admin_email;
            $_SESSION['loggedin'] = true;
            $_SESSION['role'] = 'admin'; // Set admin role
            $_SESSION['user_type'] = 'admin'; // Additional identifier
            
            // Store login time
            $_SESSION['login_time'] = time();
            
            // Redirect to admin dashboard
            header("Location: Admin-Dashboard.php");
            exit();
        }
        
        // If not admin, check database for regular users
        $query = "SELECT id, email, password FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables for regular user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['loggedin'] = true;
                $_SESSION['role'] = 'user'; // Always 'user' for database users
                $_SESSION['user_type'] = 'user';
                
                // Store login time
                $_SESSION['login_time'] = time();
                
                // Redirect to user landing page
                header("Location: Landing-Page-Section.php");
                exit();
            } else {
                $message = "Invalid password!";
                $message_type = 'error';
            }
        } else {
            $message = "Email not found!";
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sprout Productions - Login</title>
  <link rel="stylesheet" href="../css/login-form.css">
  <link rel="icon" href="../images/sprout logo bg-removed 3.png">
</head>

<body>
  <div class="page-container">
    <!-- Header -->
    <header class="header-main">
        <div class="logo">
            <a href="Landing-Page-Section.php">SPROUT PRODUCTIONS</a>
            <img src="../images/sprout logo bg-removed 3.png" alt="">
        </div>

        <nav>
            <ul class="nav-menu">
                <li><a href="#">New Arrivals</a></li>
                <li><a href="Best-Sellers-Section.php">Best Sellers</a></li>
                <li><a href="Limited-Time-Offers.php">Limited-Time Offers</a></li>
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

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" class="breadcrumb-link">Home</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Login</span>
    </div>

    <!-- Main Content -->
    <main class="main-content">
      <div class="content-wrapper">
        
        <!-- Left Section -->
        <div class="left-section">
          <h1 class="heading">
            Join Us & Get 20% Off<br>Your First Purchase!
          </h1>
          <img src="../images/logo-loginForm.png" alt="">
        </div>

        <!-- Right Section - Login Form -->
        <div class="right-section">
          <div class="form-container">
            <h2 class="form-title">LOGIN</h2>

            <!-- PHP Message -->
            <?php if (!empty($message)): ?>
              <div style="color: <?php echo isset($message_type) && $message_type == 'success' ? '#28a745' : '#dc3545'; ?>; 
                    background-color: <?php echo isset($message_type) && $message_type == 'success' ? '#d4edda' : '#f8d7da'; ?>; 
                    border: 1px solid <?php echo isset($message_type) && $message_type == 'success' ? '#c3e6cb' : '#f5c6cb'; ?>; 
                    padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; text-align: center;">
                <?= htmlspecialchars($message) ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="">
              <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-wrapper">
                  <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="Enter your email"
                    class="form-input"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                  >
                </div>
              </div>

              <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                  <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Enter your password"
                    class="form-input"
                    required
                  >
                </div>
              </div>

              <div class="login-link">
                <label>Remember Me</label>
                <input type="checkbox" class="check-small">
                <a href="Forgot-Password.php">Forgot Password?</a>
              </div>

              <button type="submit" class="submit-button">
                Login
              </button>

              <div class="signup-link">
                Don't have an account? <a href="Register-Form.php">Sign Up</a>
              </div>

            </form>
          </div>
        </div>

      </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>SPROUT PRODUCTIONS</h3>
                <p class="footer-description">
                    Proudly Bisaya. Proudly Bisdak. Style with Soul.
                    Rooted in Bisaya Pride. Bisaya-Born. Culture-Worn.
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
  </div>
</body>
</html>