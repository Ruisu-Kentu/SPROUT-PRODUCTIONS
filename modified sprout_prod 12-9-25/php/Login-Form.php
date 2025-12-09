<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sprout Productions - Sign Up</title>
<link rel="stylesheet" href="../css/login-form.css">
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
      <span class="breadcrumb-current">Login</span>
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
            <h2 class="form-title">LOGIN</h2>
            
            <form id="registrationForm">
              <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-wrapper">
                  <input
                    type="email"
                    id="email"
                    placeholder="Enter your email"
                    class="form-input"
                    required
                  >
                  <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                  </svg>
                </div>
              </div>

              <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                  <input
                    type="password"
                    id="password"
                    placeholder="Enter your password"
                    class="form-input"
                    required
                  >
                  <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </div>
              </div>

              <div class="login-link">
                <label for="">Remember Me</label>
                <input type="checkbox" name="" class="check-small">
                <a href="../php/Forgot-Password.php">Forgot Password?</a>
              </div>

              <button type="submit" class="submit-button">
                Login
              </button>

              <div class="signup-link">
                Don't have an account? <a href="../php/Register-Form.php">Sign Up</a>
              </div>
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

    

  <!-- JavaScript -->
  <script>
    // Form submission handler
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      
      console.log('Registration submitted:', { email, password });
      
      // Add your registration logic here
      alert('Registration form submitted! Check the console for details.');
      
      // Optionally reset the form
      // this.reset();
    });
  </script>
</body>
</html>
