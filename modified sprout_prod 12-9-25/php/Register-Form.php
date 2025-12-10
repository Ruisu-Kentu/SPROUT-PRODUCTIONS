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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($email) || empty($password)) {
        $message = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters!";
    } else {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user with updated_at as NULL
            $insert_query = "INSERT INTO users (email, password, updated_at) VALUES (?, ?, NULL)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ss", $email, $hashed_password);
            
            if ($stmt->execute()) {
                // Registration successful - redirect to login
                $_SESSION['registration_success'] = "Registration successful! Please login.";
                header("Location: Login-Form.php");
                exit();
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sprout Productions - Sign Up</title>
  <link rel="stylesheet" href="../css/regis-form.css">
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
      <span class="breadcrumb-current">Register</span>
    </div>

    <!-- Main Content -->
    <main class="main-content">
      <div class="content-wrapper">
        <!-- Left Section -->
        <div class="left-section">
          <h1 class="heading">
            Join Us & Get 20% Off<br>
            Your First Purchase!
          </h1>
          <img src="../images/logo-loginForm.png" alt="">
        </div>
       
        <!-- Right Section - Sign Up Form -->
        <div class="right-section">
          <div class="form-container">
            <h2 class="form-title">SIGN UP</h2>
            
            <!-- Show PHP Message -->
            <?php if (!empty($message)): ?>
              <div style="color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; text-align: center;">
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
                Already have an account? <a href="Login-Form.php">Login</a>
              </div>

              <button type="submit" class="submit-button">
                Register
              </button>
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
  </div>
</body>
</html>