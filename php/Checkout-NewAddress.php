<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sprout Productions - Sign Up</title>
  <link rel="stylesheet" href="../css/checkout-newAdd.css">
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
      <span class="breadcrumb-current">Checkout</span>
    </div>

    <!-- Main Content -->
    <main class="main-content">
      <div class="content-wrapper">

        <!-- Right Section - Sign Up Form -->
        <div class="right-section">
          <div class="form-container">
            <h2 class="form-title">NEW ADDRESS</h2>
            
            <form id="registrationForm">
              <div class="form-group">
                <div class="input-wrapper">
                  <input type="text" id="name" placeholder="Enter your Full Name" class="form-input" required>
                  <input type="number" id="phone" placeholder="Phone Number" class="form-input" required>
                </div>
              </div>

              <div class="address-container">
                    <label for="regionSelect" class="address-label">Region, Province, City, Barangay</label>
                    
                    <div class="dropdown-group">
                        <select id="regionSelect">
                            <option value="" disabled selected>Select Region</option>
                        </select>

                        <select id="provinceSelect" disabled>
                            <option value="" disabled selected>Select Province</option> 
                        </select>

                        <select id="citySelect" disabled>
                            <option value="" disabled selected>Select City</option>
                        </select>

                        <select id="barangaySelect" disabled>
                            <option value="" disabled selected>Select Barangay</option>
                        </select>
                    </div>
            </div>

            <form id="registrationForm">
              <div class="form-group">
                <div class="input-wrapper">
                  <input type="text" id="postalCode" placeholder="Postal Code" class="form-input" required>
                 
                </div>
              </div>

                <form id="registrationForm">
              <div class="form-group">
                <div class="input-wrapper">
                  <input type="text" id="streetBuilding" placeholder="Street Name, Building, House no." class="form-input" required>
                </div>
              </div>


            
              <button type="submit" class="submit-button">
                Submit
              </button>

               <button type="submit" class="cancel-button">
                Cancel
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
                    "Ilocos Region": {
                    "Ilocos Norte": {
                        "Laoag City": ["San Lorenzo", "Santa Joaquina", "Rosario", "San Guillermo", "San Pedro"],
                        "Batac City": ["Valdez", "Ablan", "Quiling Norte", "Quiling Sur", "Mabuhay"],
                        "Paoay": ["Suba", "Nagbacalan", "San Agustin", "Nalasin", "Paru-Paru"],
                        "Currimao": ["Poblacion 1", "Poblacion 2", "San Juan", "San Jose", "San Nicolas"],
                        "Solsona": ["Poblacion", "San Roque", "San Vicente", "San Antonio", "San Pedro"]
                    },
                    "Ilocos Sur": {
                        "Vigan City": ["Barangay I", "Barangay II", "Barangay III", "Barangay IV", "Barangay V"],
                        "Candon City": ["Bagani Campo", "San Andres", "San Isidro", "San Jose", "San Nicolas"],
                        "Santa": ["Centro 1", "Centro 2", "San Pedro", "San Roque", "San Juan"],
                        "Narvacan": ["Poblacion 1", "Poblacion 2", "San Miguel", "San Vicente", "San Antonio"],
                        "Tagudin": ["Centro Norte", "Centro Sur", "San Jose", "San Pedro", "San Roque"]
                    },
                    "La Union": {
                        "San Fernando City": ["Poblacion", "Biday", "Dalumpinas Este", "Ilocanos Sur", "Ilocanos Norte"],
                        "Bauang": ["Central East", "Central West", "Calumbaya", "Payocpoc Sur", "Payocpoc Norte"],
                        "Agoo": ["Poblacion 1", "Poblacion 2", "San Roque", "San Vicente", "San Pedro"],
                        "Caba": ["Centro 1", "Centro 2", "San Antonio", "San Miguel", "San Juan"],
                        "Bagulin": ["Poblacion", "San Jose", "San Nicolas", "San Roque", "San Pedro"]
                    },
                    "Pangasinan": {
                        "Dagupan City": ["Bonuan Gueset", "Bonuan Boquig", "Bonuan Binloc", "Calmay", "Lucao"],
                        "Urdaneta City": ["Anonas", "Bayaoas", "Cayambanan", "Cabituculan", "Nancayasan"],
                        "Alaminos": ["Poblacion 1", "Poblacion 2", "San Roque", "San Pedro", "San Antonio"],
                        "San Carlos City": ["Centro Norte", "Centro Sur", "San Jose", "San Miguel", "San Juan"],
                        "Lingayen": ["Poblacion 1", "Poblacion 2", "San Vicente", "San Roque", "San Pedro"]
                    },
                    "Cagayan Valley": {
                        "Tuguegarao City": ["Centro 1", "Centro 2", "Centro 3", "San Gabriel", "San Vicente"],
                        "Aparri": ["San Jose", "Centro Norte", "Centro Sur", "San Nicolas", "Santa Rita"],
                        "Sanchez Mira": ["Poblacion 1", "Poblacion 2", "San Pedro", "San Isidro", "San Roque"],
                        "Ilagan City": ["San Vicente", "San Miguel", "San Mateo", "Santo Tomas", "Centro 1"],
                        "Cauayan City": ["Bagumbayan", "Poblacion 1", "Poblacion 2", "San Isidro", "San Roque"]
                    }
                    }
                },
                "Visayas": {
                    "Western Visayas": {
                    "Aklan": {
                        "Kalibo": ["Poblacion 1", "Poblacion 2", "San Jose", "San Nicolas", "San Roque"],
                        "Numancia": ["Poblacion", "San Juan", "San Vicente", "San Miguel", "San Pedro"],
                        "Malay": ["Boracay", "Balabag", "Manoc-Manoc", "Yapak", "Poblacion"],
                        "Banga": ["Poblacion 1", "Poblacion 2", "San Antonio", "San Isidro", "San Roque"],
                        "Lezo": ["Poblacion", "San Jose", "San Juan", "San Vicente", "San Pedro"]
                    },
                    "Antique": {
                        "San Jose de Buenavista": ["Poblacion 1", "Poblacion 2", "San Roque", "San Pedro", "San Antonio"],
                        "Patnongon": ["Centro Norte", "Centro Sur", "San Miguel", "San Vicente", "San Jose"],
                        "Barbaza": ["Poblacion", "San Juan", "San Pedro", "San Nicolas", "San Roque"],
                        "Hamtic": ["Poblacion 1", "Poblacion 2", "San Vicente", "San Antonio", "San Jose"],
                        "Tibiao": ["Poblacion", "San Miguel", "San Pedro", "San Roque", "San Juan"]
                    },
                    "Capiz": {
                        "Roxas City": ["Poblacion 1", "Poblacion 2", "San Roque", "San Pedro", "San Vicente"],
                        "Panay": ["Poblacion", "San Jose", "San Juan", "San Miguel", "San Antonio"],
                        "Pontevedra": ["Poblacion", "San Vicente", "San Roque", "San Pedro", "San Nicolas"],
                        "Cuartero": ["Poblacion 1", "Poblacion 2", "San Miguel", "San Pedro", "San Juan"],
                        "Dumalag": ["Poblacion", "San Antonio", "San Roque", "San Vicente", "San Jose"]
                    },
                    "Iloilo": {
                        "Iloilo City": ["Molo", "Jaro", "Mandurriao", "Arevalo", "La Paz"],
                        "Santa Barbara": ["Poblacion 1", "Poblacion 2", "San Jose", "San Roque", "San Pedro"],
                        "Dumangas": ["Poblacion", "San Vicente", "San Antonio", "San Juan", "San Nicolas"],
                        "Pavia": ["Poblacion", "San Roque", "San Pedro", "San Miguel", "San Vicente"],
                        "Passi City": ["Poblacion 1", "Poblacion 2", "San Jose", "San Juan", "San Pedro"]
                    },
                    "Negros Occidental": {
                        "Bacolod City": ["Poblacion 1", "Poblacion 2", "Lantawan", "Singcang", "Sum-ag"],
                        "Silay City": ["Poblacion", "San Juan", "San Jose", "San Roque", "San Pedro"],
                        "Talisay City": ["Poblacion 1", "Poblacion 2", "San Vicente", "San Antonio", "San Miguel"],
                        "Victorias City": ["Poblacion", "San Pedro", "San Roque", "San Jose", "San Juan"],
                        "Cadiz City": ["Poblacion 1", "Poblacion 2", "San Miguel", "San Antonio", "San Nicolas"]
                    }
                    }
                },
                "Mindanao": {
                    "Northern Mindanao": {
                    "Bukidnon": {
                        "Malaybalay City": ["Poblacion 1", "Poblacion 2", "Cabangahan", "San Jose", "Managok"],
                        "Valencia City": ["Poblacion", "Bagontaas", "Lurogan", "San Antonio", "Can-ayan"],
                        "Maramag": ["Poblacion 1", "Poblacion 2", "San Miguel", "San Roque", "San Vicente"],
                        "Kibawe": ["Poblacion", "San Juan", "San Pedro", "San Antonio", "San Nicolas"],
                        "Manolo Fortich": ["Poblacion", "San Jose", "San Juan", "San Roque", "San Miguel"]
                    },
                    "Camiguin": {
                        "Mambajao": ["Poblacion 1", "Poblacion 2", "Casinglot", "Bajo", "Bitoon"],
                        "Catarman": ["Poblacion", "Guinsiliban", "Kauswagan", "San Roque", "San Jose"],
                        "Mahinog": ["Poblacion", "San Juan", "San Miguel", "San Vicente", "San Antonio"],
                        "Sagay": ["Poblacion 1", "Poblacion 2", "San Pedro", "San Nicolas", "San Roque"],
                        "Guinsiliban": ["Poblacion", "San Jose", "San Juan", "San Miguel", "San Pedro"]
                    },
                    "Lanao del Norte": {
                        "Iligan City": ["Poblacion 1", "Poblacion 2", "San Roque", "San Pedro", "San Vicente"],
                        "Tubod": ["Poblacion", "San Jose", "San Antonio", "San Miguel", "San Juan"],
                        "Kapatagan": ["Poblacion 1", "Poblacion 2", "San Pedro", "San Roque", "San Vicente"],
                        "Baroy": ["Poblacion", "San Juan", "San Jose", "San Antonio", "San Miguel"],
                        "Baloi": ["Poblacion", "San Roque", "San Pedro", "San Vicente", "San Juan"]
                    },
                    "Misamis Occidental": {
                        "Ozamiz City": ["Poblacion 1", "Poblacion 2", "San Roque", "San Jose", "San Juan"],
                        "Oroquieta City": ["Poblacion", "San Antonio", "San Pedro", "San Vicente", "San Miguel"],
                        "Tangub City": ["Poblacion 1", "Poblacion 2", "San Roque", "San Juan", "San Pedro"],
                        "Plaridel": ["Poblacion", "San Jose", "San Miguel", "San Vicente", "San Antonio"],
                        "Don Victoriano Chiongbian": ["Poblacion", "San Roque", "San Pedro", "San Juan", "San Jose"]
                    },
                    "Misamis Oriental": {
                        "Cagayan de Oro City": ["Barangay 1", "Barangay 2", "Barangay 3", "Barangay 4", "Barangay 5"],
                        "El Salvador": ["Poblacion", "San Juan", "San Miguel", "San Pedro", "San Vicente"],
                        "Gingoog City": ["Poblacion 1", "Poblacion 2", "San Roque", "San Jose", "San Juan"],
                        "Opol": ["Poblacion", "San Antonio", "San Pedro", "San Vicente", "San Miguel"],
                        "Tagoloan": ["Poblacion", "San Jose", "San Juan", "San Pedro", "San Roque"]
                    }
                    }
                }
                };


     
     

  
        // Grab select elements
        const regionSelect = document.getElementById("regionSelect");
        const provinceSelect = document.getElementById("provinceSelect");
        const citySelect = document.getElementById("citySelect");
        const barangaySelect = document.getElementById("barangaySelect");

        // Function to reset a select dropdown
        function resetSelect(selectElement, placeholder) {
            selectElement.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
            selectElement.disabled = true;
        }

        // Load Regions
        function loadRegions() {
            resetSelect(provinceSelect, "Select Province");
            resetSelect(citySelect, "Select City");
            resetSelect(barangaySelect, "Select Barangay");

            Object.keys(data).forEach(region => {
                let option = document.createElement("option");
                option.value = region;
                option.textContent = region;
                regionSelect.appendChild(option);
            });
        }

        // On Region Change
        regionSelect.addEventListener("change", () => {
            resetSelect(provinceSelect, "Select Province");
            resetSelect(citySelect, "Select City");
            resetSelect(barangaySelect, "Select Barangay");
            provinceSelect.disabled = false;

            const provinces = data[regionSelect.value];
            Object.keys(provinces).forEach(province => {
                let option = document.createElement("option");
                option.value = province;
                option.textContent = province;
                provinceSelect.appendChild(option);
            });
        });

        // On Province Change
        provinceSelect.addEventListener("change", () => {
            resetSelect(citySelect, "Select City");
            resetSelect(barangaySelect, "Select Barangay");
            citySelect.disabled = false;

            const cities = data[regionSelect.value][provinceSelect.value];
            Object.keys(cities).forEach(city => {
                let option = document.createElement("option");
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        });

        // On City Change
        citySelect.addEventListener("change", () => {
            resetSelect(barangaySelect, "Select Barangay");
            barangaySelect.disabled = false;

            const barangays = data[regionSelect.value][provinceSelect.value][citySelect.value];
            if (Array.isArray(barangays)) {
                barangays.forEach(brgy => {
                    let option = document.createElement("option");
                    option.value = brgy;
                    option.textContent = brgy;
                    barangaySelect.appendChild(option);
                });
            }
        });

        // Capture form submission
        document.getElementById("registrationForm").addEventListener("submit", e => {
            e.preventDefault();
            const address = {
                region: regionSelect.value,
                province: provinceSelect.value,
                city: citySelect.value,
                barangay: barangaySelect.value,
                name: document.getElementById("name").value,
                phone: document.getElementById("phone").value
            };
            console.log("Selected Address:", address);
        });

        // Initialize regions on page load
        loadRegions();

 

 
</script>


 
</body>
</html>
