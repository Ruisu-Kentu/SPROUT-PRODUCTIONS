<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // Redirect based on role with alert
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            echo '<script>
                alert("⚠️ Already Logged In\\n\\nYou are currently logged in as ADMINISTRATOR!\\nPlease logout first to access this page.");
                window.location.href = "admin-dashboard.php";
            </script>';
            exit();
        } else {
            // Regular user - redirect to their dashboard/home page
            echo '<script>
                alert("⚠️ Already Logged In\\n\\nYou are currently logged in as USER!\\nPlease logout first to access this page.");
                window.location.href = "Landing-Page-Section.php";
            </script>';
            exit();
        }
    } else {
        // Role not set, redirect to default page
        echo '<script>
            alert("⚠️ Already Logged In\\n\\nYou are currently logged in!\\nPlease logout first to access this page.");
            window.location.href = "Landing-Page-Section.php";
        </script>';
        exit();
    }
}

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

// Predefined security questions (you can also fetch these from a database table)
$security_questions = [
    "What was your first pet's name?",
    "What city were you born in?",
    "What is your mother's maiden name?",
    "What was the name of your elementary school?",
    "What is your favorite movie?",
    "What was your childhood nickname?"
];

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $security_question = trim($_POST['security_question']);
    $security_answer = trim($_POST['security_answer']);
    
    // Validation
    if (empty($email) || empty($password) || empty($security_question) || empty($security_answer)) {
        $message = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters!";
    } elseif (!in_array($security_question, $security_questions)) {
        $message = "Please select a valid security question!";
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
            
            // Hash security answer (for privacy)
            $hashed_security_answer = password_hash(strtolower(trim($security_answer)), PASSWORD_DEFAULT);
            
            // Insert new user with security question and answer
            $insert_query = "INSERT INTO users (email, password, security_question, security_answer, updated_at) VALUES (?, ?, ?, ?, NULL)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssss", $email, $hashed_password, $security_question, $hashed_security_answer);
            
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

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="Landing-Page-Section.php" class="breadcrumb-link">Home</a>
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
          <img src="../images/logo-loginForm.png" alt="Sprout Productions Logo">
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
                    placeholder="Enter your password (min. 6 characters)"
                    class="form-input"
                    minlength="6"
                    required
                  >
                </div>
              </div>

              <div class="form-group">
                <label for="security_question" class="form-label">Security Question (For Password Recovery)</label>
                <div class="input-wrapper">
                  <select
                    name="security_question"
                    id="security_question"
                    class="form-input"
                    required
                  >
                    <option value="">Select a security question</option>
                    <?php foreach ($security_questions as $question): ?>
                      <option value="<?= htmlspecialchars($question) ?>" 
                        <?php if (isset($_POST['security_question']) && $_POST['security_question'] === $question) echo 'selected'; ?>>
                        <?= htmlspecialchars($question) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="security_answer" class="form-label">Answer to Security Question</label>
                <div class="input-wrapper">
                  <input
                    type="text"
                    name="security_answer"
                    id="security_answer"
                    placeholder="Enter your answer (case-insensitive)"
                    class="form-input"
                    value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>"
                    required
                  >
                  <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                    Note: This answer will be used to verify your identity if you forget your password.
                  </small>
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
                    <div class="social-icon-fb">
                        <img src="../images/facebook_logo.png" alt="Facebook">
                    </div>
                    <div class="social-icon-insta">
                        <img src="../images/insta_logo.png" alt="Instagram">
                    </div>
                    <div class="social-icon-github">
                        <img src="../images/github_logo.png" alt="GitHub">
                    </div>
                    <div class="social-icon-twitter">
                        <img src="../images/twitter.png" alt="Twitter">
                    </div>
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