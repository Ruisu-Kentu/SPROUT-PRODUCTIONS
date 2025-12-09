<?php
session_start();

// ============================
// DATABASE CONNECTION
// ============================
$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "ecommerce_db";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ============================
// INITIALIZE VARIABLES
// ============================
$success = "";
$error = "";

// ============================
// HANDLE REGISTRATION
// ============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : '';

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $insert->bind_param("ss", $username, $hashedPassword);

            if ($insert->execute()) {
                $success = "Account created successfully! You can now <a href='Login-Form.php'>login</a>.";
            } else {
                $error = "Error creating account. Please try again.";
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
            <a href="../php/Landing-Page-Section.php">SPROUT PRODUCTIONS</a>
            <img src="../images/sprout logo bg-removed 3.png" alt="">
        </div>
        <nav>
            <ul class="nav-menu">
                <li><a href="#">New Arrivals</a></li>
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
            
            <form id="registrationForm" method="POST" action="">
                <?php if($error) echo "<p style='color:red;'>$error</p>"; ?>
                <?php if($success) echo "<p style='color:green;'>$success</p>"; ?>
              <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-wrapper">
                  <input type="username" id="email" name="username" placeholder="Enter your username" class="form-input" required>
                </div>
              </div>

              <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                 <input type="password" id="password" name="password" placeholder="Enter your password" class="form-input" required>
                </div>
              </div>

              <div class="login-link">
                Already have an account? <a href="../php/Login-Form.php">Login</a>
              </div>

              <button type="submit" class="submit-button">
                Register
              </button>
            </form>
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

</body>
</html>
