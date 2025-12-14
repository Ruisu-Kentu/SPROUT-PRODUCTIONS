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

$message = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'email';

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 'email') {
        $email = trim($_POST['email']);
        
        // Validate email
        if (empty($email)) {
            $message = "Email is required!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format!";
        } else {
            // Check if email exists in database
            $check_query = "SELECT id, security_question FROM users WHERE email = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // Store user info in session for next steps
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_question'] = $user['security_question'];
                
                // Redirect to security question step
                header("Location: forgot-password.php?step=question");
                exit();
            } else {
                $message = "Email not found in our system!";
            }
        }
    }
    elseif ($step == 'question') {
        if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_question'])) {
            // Session expired, redirect to first step
            header("Location: forgot-password.php");
            exit();
        }
        
        $answer = trim($_POST['security_answer']);
        
        if (empty($answer)) {
            $message = "Please enter your security answer!";
        } else {
            // Verify security answer
            $query = "SELECT security_answer FROM users WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $_SESSION['reset_email']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify the hashed answer (case-insensitive)
                if (password_verify(strtolower(trim($answer)), $user['security_answer'])) {
                    $_SESSION['reset_verified'] = true;
                    header("Location: forgot-password.php?step=reset");
                    exit();
                } else {
                    $message = "Incorrect answer! Please try again.";
                }
            } else {
                $message = "User not found. Please start over.";
                session_destroy();
            }
        }
    }
    elseif ($step == 'reset') {
        if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
            // Not verified, redirect to first step
            header("Location: forgot-password.php");
            exit();
        }
        
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($new_password) || empty($confirm_password)) {
            $message = "Both password fields are required!";
        } elseif ($new_password !== $confirm_password) {
            $message = "Passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $message = "Password must be at least 6 characters!";
        } else {
            // Update password in database
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ss", $hashed_password, $_SESSION['reset_email']);
            
            if ($stmt->execute()) {
                // Clear session data
                session_unset();
                session_destroy();
                
                // Start new session for success message
                session_start();
                $_SESSION['password_reset_success'] = "Password reset successful! Please login with your new password.";
                header("Location: Login-Form.php");
                exit();
            } else {
                $message = "Password reset failed. Please try again.";
            }
        }
    }
}

// Clear session if user manually changes step
if (isset($_GET['step']) && $_GET['step'] == 'email' && isset($_SESSION['reset_email'])) {
    session_destroy();
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sprout Productions - Forgot Password</title>
  <link rel="stylesheet" href="../css/forgot-pass.css">
  <link rel="icon" href="../images/sprout logo bg-removed 3.png">
</head>
<body>
  <div class="page-container">

    <!-- Breadcrumb --> 
    <div class="breadcrumb">
      <a href="Landing-Page-Section.php" class="breadcrumb-link">Home</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">Forgot Password</span>
    </div>

    <!-- Main Content -->
    <main class="main-content">
      <div class="content-wrapper">
        <!-- Right Section - Forgot Password Form -->
        <div class="right-section">
          <div class="form-container">
            <h2 class="form-title">
              <?php 
                if ($step == 'email') echo 'FIND YOUR ACCOUNT';
                elseif ($step == 'question') echo 'SECURITY QUESTION';
                elseif ($step == 'reset') echo 'RESET PASSWORD';
              ?>
            </h2>
            
            <?php if (!empty($message)): ?>
              <div class="error-message">
                <?= htmlspecialchars($message) ?>
              </div>
            <?php endif; ?>

            <?php if ($step == 'email'): ?>
              <!-- Step 1: Email Entry -->
              <form method="POST" action="">
                <div class="form-group">
                  <label for="email" class="form-label">Enter Email Address</label>
                  <div class="input-wrapper">
                    <input
                      type="email"
                      name="email"
                      id="email"
                      placeholder="Enter your registered email"
                      class="form-input"
                      value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                      required
                    >
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                      <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                  </div>
                </div>

                <div class="login-link">
                  Remember your password? <a href="Login-Form.php">Login here</a>
                </div>

                <button type="submit" class="submit-button">
                  Continue
                </button>
              </form>

            <?php elseif ($step == 'question' && isset($_SESSION['reset_question'])): ?>
              <!-- Step 2: Security Question -->
              <form method="POST" action="">
                <div class="form-group">
                  <label class="form-label">Security Question</label>
                  <div class="security-question-box">
                    <?= htmlspecialchars($_SESSION['reset_question']) ?>
                  </div>
                </div>

                <div class="form-group">
                  <label for="security_answer" class="form-label">Your Answer</label>
                  <div class="input-wrapper">
                    <input
                      type="text"
                      name="security_answer"
                      id="security_answer"
                      placeholder="Enter your answer (case-insensitive)"
                      class="form-input"
                      required
                    >
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                  </div>
                </div>

                <div class="form-actions">
                  <a href="forgot-password.php?step=email" class="back-button">Back</a>
                  <button type="submit" class="submit-button">Verify Answer</button>
                </div>
              </form>

            <?php elseif ($step == 'reset' && isset($_SESSION['reset_verified'])): ?>
              <!-- Step 3: Reset Password -->
              <form method="POST" action="">
                <div class="form-group">
                  <label for="new_password" class="form-label">New Password</label>
                  <div class="input-wrapper">
                    <input
                      type="password"
                      name="new_password"
                      id="new_password"
                      placeholder="Enter new password (min. 6 characters)"
                      class="form-input"
                      minlength="6"
                      required
                    >
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                  </div>
                </div>

                <div class="form-group">
                  <label for="confirm_password" class="form-label">Confirm New Password</label>
                  <div class="input-wrapper">
                    <input
                      type="password"
                      name="confirm_password"
                      id="confirm_password"
                      placeholder="Confirm your new password"
                      class="form-input"
                      minlength="6"
                      required
                    >
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                  </div>
                </div>

                <div class="password-requirements">
                  <small>Password must be at least 6 characters long.</small>
                </div>

                <div class="form-actions">
                  <a href="forgot-password.php?step=question" class="back-button">Back</a>
                  <button type="submit" class="submit-button">Reset Password</button>
                </div>
              </form>

            <?php else: ?>
              <!-- Invalid step or session expired -->
              <div class="error-message">
                Session expired or invalid request. Please start over.
              </div>
              <div class="login-link" style="text-align: center; margin-top: 20px;">
                <a href="forgot-password.php" class="submit-button" style="display: inline-block;">Start Over</a>
              </div>
            <?php endif; ?>
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

  <!-- Add some CSS for the new elements -->
  <style>
    .error-message {
      color: #dc3545;
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
      font-size: 14px;
      text-align: center;
    }
    
    .security-question-box {
      background: #f5f5f5;
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
      font-size: 16px;
      border-left: 4px solid #4CAF50;
    }
    
    .form-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
    }
    
    .back-button {
      padding: 10px 20px;
      background-color: #6c757d;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      font-size: 14px;
      transition: background-color 0.3s;
    }
    
    .back-button:hover {
      background-color: #5a6268;
    }
    
    .password-requirements {
      color: #666;
      font-size: 12px;
      margin-top: 5px;
      margin-bottom: 15px;
      font-style: italic;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    .input-icon {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 18px;
      height: 18px;
      color: #666;
    }
    
    .form-input {
      padding-right: 40px !important;
    }
  </style>
</body>
</html>