<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<script>
        alert("⚠️\\n\\nPlease log in first!");
        window.location.href = "Login-Form.php";
    </script>';
    exit();
}

// Check if user is admin - deny access if they are
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo '<script>
        alert("⛔ ACCESS DENIED\\n\\nThis page is only for regular users!");
        window.location.href = "admin-dashboard.php";
    </script>';
    exit();
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

// Get user ID and email from session
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// Function to get cart count for header (COUNT OF DISTINCT PRODUCTS)
function getCartCount($conn, $userId) {
    $countQuery = "SELECT COUNT(*) as product_count FROM user_cart WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['product_count'] ?? 0;
}

// Get cart count for header display (COUNT OF DISTINCT PRODUCTS)
$cartCount = $userId ? getCartCount($conn, $userId) : 0;

// Fetch existing user address if any
$existingAddress = null;
if ($userId) {
    // Get user's default address or most recent address
    $addressQuery = "SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1";
    $stmt = $conn->prepare($addressQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $addressResult = $stmt->get_result();
    
    if ($addressResult->num_rows > 0) {
        $existingAddress = $addressResult->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $region = $_POST['region'];
    $province = $_POST['province'];
    $city = $_POST['city'];
    $barangay = $_POST['barangay'];
    $postal_code = trim($_POST['postal_code']);
    $street_address = trim($_POST['street_address']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone_number)) {
        $errors[] = "Please enter a valid phone number (10-11 digits)";
    }
    
    if (empty($region) || $region === 'Select Region') {
        $errors[] = "Please select a region";
    }
    
    if (empty($province) || $province === 'Select Province') {
        $errors[] = "Please select a province";
    }
    
    if (empty($city) || $city === 'Select City') {
        $errors[] = "Please select a city";
    }
    
    if (empty($barangay) || $barangay === 'Select Barangay') {
        $errors[] = "Please select a barangay";
    }
    
    // Postal code validation - only check if empty (should be auto-populated)
    if (empty($postal_code)) {
        $errors[] = "Please complete the address details to get postal code";
    }
    
    if (empty($street_address)) {
        $errors[] = "Street address is required";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        // Check if we're updating an existing address
        if ($existingAddress) {
            // Update existing address
            $updateQuery = "UPDATE user_addresses 
                           SET full_name = ?, phone_number = ?, region = ?, province = ?, 
                               city = ?, barangay = ?, postal_code = ?, street_address = ?
                           WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssssii", $full_name, $phone_number, $region, $province, 
                             $city, $barangay, $postal_code, $street_address, 
                             $existingAddress['id'], $userId);
            
            if ($stmt->execute()) {
                $success_message = "Address updated successfully!";
                // Update existingAddress with new values for form persistence
                $existingAddress = array_merge($existingAddress, $_POST);
            } else {
                $error_message = "Error updating address: " . $conn->error;
            }
        } else {
            // First, set all existing addresses as not default (if any)
            $resetDefaultQuery = "UPDATE user_addresses SET is_default = 0 WHERE user_id = ?";
            $stmt = $conn->prepare($resetDefaultQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            
            // Insert new address as default
            $insertQuery = "INSERT INTO user_addresses (user_id, full_name, phone_number, region, province, city, barangay, postal_code, street_address, is_default) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("issssssss", $userId, $full_name, $phone_number, $region, $province, $city, $barangay, $postal_code, $street_address);
            
            if ($stmt->execute()) {
                $success_message = "Address saved successfully!";
                // Update existingAddress with new values for form persistence
                $existingAddress = $_POST;
            } else {
                $error_message = "Error saving address: " . $conn->error;
            }
        }
        $stmt->close();
        
        // Redirect back to checkout after 2 seconds
        if ($success_message) {
            echo '<script>
                setTimeout(function() {
                    window.location.href = "checkout-section.php";
                }, 2000);
            </script>';
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $existingAddress ? 'Update Address' : 'New Address'; ?> - Sprout Productions</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="icon" href="../images/sprout logo bg-removed 3.png">
      <link rel="stylesheet" href="../css/land-pag-sec.css">
  <style>
    /* ===== MAIN CONTENT ===== */
    .main-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    .content-wrapper {
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .right-section {
      width: 100%;
      max-width: 700px;
    }

    .form-container {
      background: #fff;
      border-radius: 0.75rem;
      padding: 40px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .form-title {
      font-family: 'Georgia', serif;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
      text-align: center;
      color: #000;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .input-wrapper {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
    }

    .form-input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      font-family: Arial, sans-serif;
      transition: all 0.3s ease;
      background-color: #f9fafb;
    }

    .form-input:focus {
      outline: none;
      border-color: #000;
      background-color: #fff;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    .form-input::placeholder {
      color: #9ca3af;
    }

    /* Read-only input styling */
    .form-input:read-only {
        background-color: #f0f0f0;
        color: #666;
        cursor: not-allowed;
        border-color: #d1d5db;
    }

    .form-input:read-only:focus {
        border-color: #d1d5db;
        box-shadow: none;
    }

    /* Remove spinner from number input */
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    input[type="number"] {
      -moz-appearance: textfield;
    }

    .address-container {
      margin-bottom: 20px;
    }

    .address-label {
      display: block;
      font-weight: 600;
      margin-bottom: 12px;
      color: #000;
      font-size: 14px;
    }

    .dropdown-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    select {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      font-family: Arial, sans-serif;
      background-color: #f9fafb;
      cursor: pointer;
      transition: all 0.3s ease;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      padding-right: 40px;
    }

    select:focus {
      outline: none;
      border-color: #000;
      background-color: #fff;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    select:disabled {
      background-color: #f3f4f6;
      cursor: not-allowed;
      opacity: 0.6;
    }

    /* Button Container */
    .button-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-top: 30px;
    }

    .submit-button,
    .cancel-button {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 2rem;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .submit-button {
      background-color: #000;
      color: #fff;
    }

    .submit-button:hover {
      background-color: #333;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .submit-button:active {
      transform: translateY(0);
    }

    .cancel-button {
      background-color: #fff;
      color: #000;
      border: 2px solid #000;
    }

    .cancel-button:hover {
      background-color: #f5f5f5;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .cancel-button:active {
      transform: translateY(0);
    }

    /* Alert Messages */
    .alert {
      padding: 12px 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      font-weight: 500;
      animation: fadeIn 0.5s;
      font-family: Arial, sans-serif;
      font-size: 14px;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .alert-success {
      background-color: #E8F5E9;
      color: #2e7d32;
      border: 1px solid #4CAF50;
    }

    .alert-error {
      background-color: #FFEBEE;
      color: #c62828;
      border: 1px solid #f44336;
    }

    /* ===== FOOTER - MATCHING LANDING PAGE ===== */
    .footer {
      background-color: #000;
      color: #fff;
      padding: 40px 20px 20px;
      margin-top: 60px;
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 40px;
      max-width: 1200px;
      margin: 0 auto;
      padding-bottom: 30px;
      border-bottom: 1px solid #333;
    }

    .footer-column h3 {
      font-size: 18px;
      margin-bottom: 15px;
      font-family: 'Georgia', serif;
    }

    .footer-description {
      color: #ccc;
      line-height: 1.6;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .social-icons {
      display: flex;
      gap: 10px;
    }

    .social-icon-fb,
    .social-icon-insta,
    .social-icon-github,
    .social-icon-twitter {
      width: 30px;
      height: 30px;
      background-color: #333;
      border-radius: 50%;
      cursor: pointer;
      transition: transform 0.3s ease;
    }

    .social-icon-fb:hover,
    .social-icon-insta:hover,
    .social-icon-github:hover,
    .social-icon-twitter:hover {
      transform: translateY(-3px);
    }

    .footer-links {
      list-style: none;
    }

    .footer-links li {
      margin-bottom: 8px;
    }

    .footer-links a {
      color: #ccc;
      text-decoration: none;
      transition: color 0.2s;
      font-size: 14px;
    }

    .footer-links a:hover {
      color: #fff;
    }

    .footer-bottom {
      text-align: center;
      padding-top: 20px;
      color: #999;
      font-size: 14px;
      line-height: 1.6;
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      body {
        padding-top: 140px;
      }

      .nav-menu {
        gap: 20px;
      }

      .nav-menu li a {
        font-size: 12px;
      }

      .logo-text {
        font-size: 18px;
      }

      .input-wrapper,
      .dropdown-group,
      .button-group {
        grid-template-columns: 1fr;
      }

      .footer-content {
        grid-template-columns: repeat(2, 1fr);
      }

      .form-container {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>
  <!-- Fixed Header - Matching Landing Page -->
  <header class="sticky-header">
    <!-- Top Bar with User Info and Logout -->
    <div class="top-bar">
      <div class="container">
        <div class="top-bar-content">
          <div class="user-info-with-icon">
            <img src="../images/user_logo.png" alt="User" class="user-icon-small">
            <span class="welcome-text">Welcome,</span>
            <span class="user-email"><?php echo htmlspecialchars($userEmail); ?> (<?php echo $_SESSION['role']; ?>)</span>
          </div>
          <div class="top-bar-actions">
            <a href="logout.php" class="logout-link-no-icon">Logout</a>
            <img src="../images/close_logo.png" alt="Close" class="close-icon" onclick="window.location.href='checkout-section.php'">
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
            <a href="Landing-Page-Section.php" class="logo-link">
              <img src="../images/sprout logo bg-removed 3.png" alt="Sprout Logo" class="logo-img">
              <span class="logo-text">SPROUT PRODUCTIONS</span>
            </a>
          </div>

          <!-- Center Navigation Menu -->
          <nav class="center-nav">
            <ul class="nav-menu">
              <li><a href="New-Arrival-Section.php">New Arrivals</a></li>
              <li><a href="Best-Sellers-Section.php">Best Sellers</a></li>
              <li><a href="Limited-Time-Offers.php">Special Offers</a></li>
              <li><a href="my-orders.php">My Orders</a></li>
            </ul>
          </nav>

          <!-- Right Side Icons -->
          <div class="right-nav">
            <div class="action-icons">
              <a href="cart-section.php" class="icon-link">
                <img src="../images/cart_logo.png" alt="Cart" class="nav-icon">
                <!-- Cart badge now shows count of distinct products -->
                <span id="cart-badge" class="icon-badge"><?php echo $cartCount; ?></span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <span class="breadcrumb-current">Checkout › <?php echo $existingAddress ? 'Update Address' : 'New Address'; ?></span>
  </div>

  <!-- Main Content -->
  <main class="main-content">
    <div class="content-wrapper">
      <!-- Form Section -->
      <div class="right-section">
        <div class="form-container">
          <h2 class="form-title"><?php echo $existingAddress ? 'UPDATE ADDRESS' : 'NEW ADDRESS'; ?></h2>
          
          <!-- Display success/error messages -->
          <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
          <?php endif; ?>
          
          <form id="registrationForm" method="POST" action="">
            <!-- Name and Phone -->
            <div class="form-group">
              <div class="input-wrapper">
                <input type="text" id="full_name" name="full_name" placeholder="Enter your Full Name" class="form-input" required 
                       value="<?php 
                       echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : 
                            ($existingAddress ? htmlspecialchars($existingAddress['full_name']) : ''); 
                       ?>">
                <input type="tel" id="phone_number" name="phone_number" placeholder="Phone Number (10-11 digits)" class="form-input" required
                       value="<?php 
                       echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : 
                            ($existingAddress ? htmlspecialchars($existingAddress['phone_number']) : ''); 
                       ?>">
              </div>
            </div>

            <!-- Address Dropdowns -->
            <div class="address-container">
              <label class="address-label">Region, Province, City, Barangay</label>
              
              <div class="dropdown-group">
                <select id="regionSelect" name="region" required>
                  <option value="" disabled selected>Select Region</option>
                </select>

                <select id="provinceSelect" name="province" disabled required>
                  <option value="" disabled selected>Select Province</option> 
                </select>

                <select id="citySelect" name="city" disabled required>
                  <option value="" disabled selected>Select City</option>
                </select>

                <select id="barangaySelect" name="barangay" disabled required>
                  <option value="" disabled selected>Select Barangay</option>
                </select>
              </div>
            </div>

            <!-- Postal Code (Read-only) -->
            <div class="form-group">
              <input type="text" id="postal_code" name="postal_code" placeholder="Postal Code" class="form-input" readonly
                     value="<?php 
                     echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : 
                          ($existingAddress ? htmlspecialchars($existingAddress['postal_code']) : ''); 
                     ?>">
            </div>

            <!-- Street Address -->
            <div class="form-group">
              <input type="text" id="street_address" name="street_address" placeholder="Street Name, Building, House no." class="form-input" required
                     value="<?php 
                     echo isset($_POST['street_address']) ? htmlspecialchars($_POST['street_address']) : 
                          ($existingAddress ? htmlspecialchars($existingAddress['street_address']) : ''); 
                     ?>">
            </div>

            <!-- Buttons -->
            <div class="button-group">
              <button type="submit" class="submit-button"><?php echo $existingAddress ? 'Update Address' : 'Save Address'; ?></button>
              <button type="button" class="cancel-button" onclick="window.location.href='checkout-section.php'">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer - Matching Landing Page -->
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

  <script>
   const data = {
    "Luzon": {
        "National Capital Region (NCR)": {
            "Manila": {
                "Binondo": "1006",
                "Ermita": "1000",
                "Intramuros": "1002",
                "Malate": "1004",
                "Paco": "1007",
                "Quiapo": "1001",
                "Sampaloc": "1008",
                "San Miguel": "1005",
                "Santa Ana": "1009",
                "Santa Cruz": "1003",
                "Tondo": "1012"
            },
            "Quezon City": {
                "Cubao": "1109",
                "Diliman": "1101",
                "Katipunan": "1108",
                "Loyola Heights": "1108",
                "Project 6": "1100",
                "Sikatuna Village": "1101",
                "Tandang Sora": "1116",
                "UP Village": "1101",
                "West Triangle": "1104"
            },
            "Makati": {
                "Bel-Air": "1209",
                "Dasmarinas Village": "1222",
                "Forbes Park": "1219",
                "Poblacion": "1210",
                "San Lorenzo": "1223",
                "Urdaneta": "1225"
            },
            "Taguig": {
                "Bonifacio Global City": "1634",
                "Fort Bonifacio": "1630",
                "Western Bicutan": "1630"
            }
        },
        "CALABARZON": {
            "Cavite": {
                "Bacoor": "4102",
                "Dasmarinas": "4114",
                "Imus": "4103",
                "Tagaytay City": "4120"
            },
            "Laguna": {
                "Biñan": "4024",
                "Calamba": "4027",
                "Los Baños": "4030",
                "Santa Rosa": "4026",
                "San Pedro": "4023"
            },
            "Batangas": {
                "Batangas City": "4200",
                "Lipa": "4217",
                "Tanauan": "4232"
            }
        },
        "Central Luzon": {
            "Bulacan": {
                "Baliuag": "3006",
                "Malolos": "3000",
                "Marilao": "3019",
                "Meycauayan": "3020",
                "San Jose del Monte": "3023"
            },
            "Pampanga": {
                "Angeles City": "2009",
                "City of San Fernando": "2000",
                "Mabalacat": "2010"
            }
        }
    },
    "Visayas": {
        "Western Visayas": {
            "Iloilo": {
                "Iloilo City": "5000",
                "Pavia": "5001",
                "Santa Barbara": "5002"
            },
            "Bacolod": {
                "Bacolod City": "6100"
            },
            "Negros Occidental": {
                "Bago": "6101",
                "Cadiz": "6121",
                "Silay": "6116",
                "Talisay": "6115",
                "Victorias": "6119"
            }
        },
        "Central Visayas": {
            "Cebu": {
                "Cebu City": "6000",
                "Lapu-Lapu City": "6015",
                "Mandaue City": "6014",
                "Talisay City": "6045"
            },
            "Bohol": {
                "Tagbilaran City": "6300"
            }
        },
        "Eastern Visayas": {
            "Leyte": {
                "Ormoc City": "6541",
                "Tacloban City": "6500"
            },
            "Samar": {
                "Catbalogan City": "6700",
                "Calbayog City": "6710"
            }
        }
    },
    "Mindanao": {
        "Northern Mindanao": {
            "Bukidnon": {
                "Malaybalay City": "8700",
                "Valencia City": "8709"
            },
            "Cagayan de Oro": {
                "Cagayan de Oro City": "9000"
            },
            "Misamis Oriental": {
                "El Salvador": "9017",
                "Gingoog City": "9014"
            }
        },
        "Davao Region": {
            "Davao City": {
                "Davao City": "8000"
            },
            "Davao del Sur": {
                "Digos City": "8002"
            },
            "Davao del Norte": {
                "Tagum City": "8100"
            }
        },
        "SOCCSKSARGEN": {
            "South Cotabato": {
                "Koronadal City": "9506",
                "General Santos City": "9500"
            },
            "North Cotabato": {
                "Kidapawan City": "9400"
            },
            "Sultan Kudarat": {
                "Tacurong City": "9800"
            }
        },
        "Zamboanga Peninsula": {
            "Zamboanga City": {
                "Zamboanga City": "7000"
            },
            "Zamboanga del Norte": {
                "Dipolog City": "7100",
                "Dapitan City": "7101"
            }
        }
    }
};

// Grab select elements and postal code input
const regionSelect = document.getElementById("regionSelect");
const provinceSelect = document.getElementById("provinceSelect");
const citySelect = document.getElementById("citySelect");
const barangaySelect = document.getElementById("barangaySelect");
const postalCodeInput = document.getElementById("postal_code");

// Function to reset a select dropdown
function resetSelect(selectElement, placeholder) {
    selectElement.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
    selectElement.disabled = true;
}

// Helper function to get city postal code
function getCityPostalCode(region, province, city) {
    if (data[region] && data[region][province] && data[region][province][city]) {
        const cityData = data[region][province][city];
        const barangays = Object.keys(cityData);
        if (barangays.length > 0) {
            return cityData[barangays[0]];
        }
    }
    return '';
}

// Function to update postal code based on selection
function updatePostalCode() {
    if (regionSelect.value && provinceSelect.value && citySelect.value && barangaySelect.value) {
        const region = regionSelect.value;
        const province = provinceSelect.value;
        const city = citySelect.value;
        const barangay = barangaySelect.value;
        
        // Check if we have postal code for this specific barangay
        if (data[region] && 
            data[region][province] && 
            data[region][province][city] && 
            data[region][province][city][barangay]) {
            postalCodeInput.value = data[region][province][city][barangay];
        } else {
            // Try to get city postal code as fallback
            postalCodeInput.value = getCityPostalCode(region, province, city);
        }
    } else {
        postalCodeInput.value = '';
    }
}

// Helper function to trigger events
function triggerEvent(element, eventType) {
    if (element.fireEvent) {
        element.fireEvent('on' + eventType);
    } else {
        var event = new Event(eventType);
        element.dispatchEvent(event);
    }
}

// Load Regions
function loadRegions() {
    resetSelect(provinceSelect, "Select Province");
    resetSelect(citySelect, "Select City");
    resetSelect(barangaySelect, "Select Barangay");
    postalCodeInput.value = ''; // Clear postal code

    Object.keys(data).forEach(region => {
        let option = document.createElement("option");
        option.value = region;
        option.textContent = region;
        regionSelect.appendChild(option);
    });
    
    // Get existing address data from PHP
    const existingAddressData = <?php echo $existingAddress ? json_encode($existingAddress) : 'null'; ?>;
    
    if (existingAddressData) {
        // Set region if exists in database
        if (existingAddressData.region) {
            // Find and select the region
            const regionOption = Array.from(regionSelect.options).find(opt => opt.value === existingAddressData.region);
            if (regionOption) {
                regionSelect.value = existingAddressData.region;
                triggerEvent(regionSelect, 'change');
                
                // Wait for province dropdown to populate
                setTimeout(() => {
                    if (existingAddressData.province) {
                        // Find and select the province
                        const provinceOption = Array.from(provinceSelect.options).find(opt => opt.value === existingAddressData.province);
                        if (provinceOption) {
                            provinceSelect.value = existingAddressData.province;
                            triggerEvent(provinceSelect, 'change');
                            
                            // Wait for city dropdown to populate
                            setTimeout(() => {
                                if (existingAddressData.city) {
                                    // Find and select the city
                                    const cityOption = Array.from(citySelect.options).find(opt => opt.value === existingAddressData.city);
                                    if (cityOption) {
                                        citySelect.value = existingAddressData.city;
                                        triggerEvent(citySelect, 'change');
                                        
                                        // Wait for barangay dropdown to populate
                                        setTimeout(() => {
                                            if (existingAddressData.barangay) {
                                                // Find and select the barangay
                                                const barangayOption = Array.from(barangaySelect.options).find(opt => opt.value === existingAddressData.barangay);
                                                if (barangayOption) {
                                                    barangaySelect.value = existingAddressData.barangay;
                                                    triggerEvent(barangaySelect, 'change');
                                                }
                                            }
                                        }, 100);
                                    }
                                }
                            }, 100);
                        }
                    }
                }, 100);
            }
        }
    }
}

// On Region Change
regionSelect.addEventListener("change", () => {
    resetSelect(provinceSelect, "Select Province");
    resetSelect(citySelect, "Select City");
    resetSelect(barangaySelect, "Select Barangay");
    postalCodeInput.value = ''; // Clear postal code
    provinceSelect.disabled = false;

    const regionData = data[regionSelect.value];
    if (regionData) {
        Object.keys(regionData).forEach(province => {
            let option = document.createElement("option");
            option.value = province;
            option.textContent = province;
            provinceSelect.appendChild(option);
        });
    }
});

// On Province Change
provinceSelect.addEventListener("change", () => {
    resetSelect(citySelect, "Select City");
    resetSelect(barangaySelect, "Select Barangay");
    postalCodeInput.value = ''; // Clear postal code
    citySelect.disabled = false;

    const region = regionSelect.value;
    const province = provinceSelect.value;
    if (data[region] && data[region][province]) {
        const cities = data[region][province];
        Object.keys(cities).forEach(city => {
            let option = document.createElement("option");
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
        });
    }
});

// On City Change
citySelect.addEventListener("change", () => {
    resetSelect(barangaySelect, "Select Barangay");
    postalCodeInput.value = ''; // Clear postal code
    barangaySelect.disabled = false;

    const region = regionSelect.value;
    const province = provinceSelect.value;
    const city = citySelect.value;
    
    if (data[region] && data[region][province] && data[region][province][city]) {
        const barangays = data[region][province][city];
        Object.keys(barangays).forEach(brgy => {
            let option = document.createElement("option");
            option.value = brgy;
            option.textContent = brgy;
            barangaySelect.appendChild(option);
        });
        
        // Auto-select first barangay and update postal code if no existing barangay
        setTimeout(() => {
            if (barangaySelect.options.length > 1) {
                const existingAddressData = <?php echo $existingAddress ? json_encode($existingAddress) : 'null'; ?>;
                if (!existingAddressData || !existingAddressData.barangay) {
                    barangaySelect.selectedIndex = 1;
                    updatePostalCode();
                }
            }
        }, 100);
    }
});

// On Barangay Change - Update postal code
barangaySelect.addEventListener("change", updatePostalCode);

// Initialize regions on page load with auto-selection
window.onload = function() {
    loadRegions();
    
    // Auto-select first barangay when only one exists and no existing barangay
    citySelect.addEventListener('change', function() {
        setTimeout(() => {
            const existingAddressData = <?php echo $existingAddress ? json_encode($existingAddress) : 'null'; ?>;
            if (barangaySelect.options.length === 2 && (!existingAddressData || !existingAddressData.barangay)) {
                barangaySelect.selectedIndex = 1;
                triggerEvent(barangaySelect, 'change');
            }
        }, 150);
    });
};

    // Close icon functionality
    document.querySelector('.close-icon').addEventListener('click', function() {
        window.location.href = 'checkout-section.php';
    });

    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
  </script>
</body>
</html>
<?php $conn->close(); ?>