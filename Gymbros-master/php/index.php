<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$isLoggedIn = Auth::isLoggedIn();
$isAdmin = Auth::isAdmin();
$isSuperAdmin = Auth::isSuperAdmin();

$db = new Database();
$conn = $db->getConnection();

// Fetch boarding houses from database
$boardingHouses = [];
$res = $conn->query("SELECT * FROM boarding_houses ORDER BY featured DESC, rating DESC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $boardingHouses[] = $row;
    }
} else {
    // Fallback data
    $boardingHouses = [
        [
            'id' => 1,
            'name' => 'Oakwood Student Residences & Boarding House',
            'address' => '24 Acacia Street, Poblacion',
            'city' => 'Bayugan City',
            'description' => 'Safe, modern 2-story student residence with landscaped garden, secure biometric gate, high-speed fiber internet, and quiet study environment.',
            'room_types' => 'Single Room, Bedspace, Studio Pad',
            'gender_policy' => 'All',
            'price_range' => '₱2,800 - ₱6,500/mo',
            'min_price' => 2800,
            'max_price' => 6500,
            'available_rooms' => 5,
            'total_rooms' => 14,
            'rating' => 4.9,
            'reviews_count' => 42,
            'landlord_name' => 'Atty. Roberto Dela Cruz',
            'landlord_phone' => '0917-882-9011',
            'landlord_email' => 'oakwood@boardinghub.com',
            'proximity' => '3 mins walk to Main University Gate',
            'image_path' => '../images/boarding_house_exterior.jpg',
            'amenities' => '["High-Speed WiFi 150Mbps", "24/7 CCTV & Security", "Study Lounge", "Free Drinking Water", "Kitchen Access", "Laundry Area", "Backup Generator"]'
        ],
        [
            'id' => 2,
            'name' => 'The Elm’s Haven Luxury Student Living',
            'address' => '118 University Boulevard',
            'city' => 'Bayugan City',
            'description' => 'Charming 3-story boutique boarding residence featuring balcony suites, ambient garden lighting, en-suite bathrooms, and designer study areas.',
            'room_types' => 'Studio Pad, Deluxe Room',
            'gender_policy' => 'Co-ed',
            'price_range' => '₱5,200 - ₱8,500/mo',
            'min_price' => 5200,
            'max_price' => 8500,
            'available_rooms' => 3,
            'total_rooms' => 10,
            'rating' => 5.0,
            'reviews_count' => 31,
            'landlord_name' => 'Mrs. Elena Rostova-Tan',
            'landlord_phone' => '0928-334-1190',
            'landlord_email' => 'elms@boardinghub.com',
            'proximity' => '5 mins walk to Medical Center & Campus',
            'image_path' => '../images/boarding_house_villa.jpg',
            'amenities' => '["Fiber Optic WiFi", "Air Conditioned Units", "Private Bathroom (CR)", "Kitchenette with Microwave", "Rooftop Study Deck", "Daily Common Area Cleaning"]'
        ],
        [
            'id' => 3,
            'name' => 'Pinecrest Ladies Dormitory & Bedspace',
            'address' => '45 Laurel Street, Barangay Central',
            'city' => 'Bayugan City',
            'description' => 'Exclusive, highly secure boarding house for female students and boarders. Features resident matron, fingerprint entry, individual study lamps and lockers.',
            'room_types' => 'Bedspace, Single Room',
            'gender_policy' => 'Female Only',
            'price_range' => '₱2,200 - ₱4,000/mo',
            'min_price' => 2200,
            'max_price' => 4000,
            'available_rooms' => 6,
            'total_rooms' => 20,
            'rating' => 4.8,
            'reviews_count' => 56,
            'landlord_name' => 'Ma’am Corazon Bautista',
            'landlord_phone' => '0908-112-9844',
            'landlord_email' => 'pinecrest@boardinghub.com',
            'proximity' => '2 mins walk to City College & Market',
            'image_path' => '../images/boarding_room_bedspace.jpg',
            'amenities' => '["Female Only Matron", "Lockers & Built-in Storage", "Individual Study Desks", "High Speed WiFi", "Curfew & Night Guard", "Full Kitchen & Fridge Access"]'
        ],
        [
            'id' => 4,
            'name' => 'Skyline Executive Studio Pads',
            'address' => '88 Rizal Avenue Downtown',
            'city' => 'Bayugan City',
            'description' => 'Premium studio units designed for working professionals, board exam reviewees, and college scholars wanting utmost privacy and peace.',
            'room_types' => 'Studio Pad',
            'gender_policy' => 'All',
            'price_range' => '₱6,800 - ₱8,000/mo',
            'min_price' => 6800,
            'max_price' => 8000,
            'available_rooms' => 2,
            'total_rooms' => 8,
            'rating' => 4.9,
            'reviews_count' => 28,
            'landlord_name' => 'Engr. Marcus Vance',
            'landlord_phone' => '0919-445-6671',
            'landlord_email' => 'skyline@boardinghub.com',
            'proximity' => 'Direct access to Transit Terminals & Malls',
            'image_path' => '../images/boarding_room_studio.jpg',
            'amenities' => '["Inverter Air Conditioning", "Private T&B with Hot Shower", "Granite Kitchenette & Sink", "100Mbps Dedicated Fiber", "Balcony View", "Keycard Door Access"]'
        ],
        [
            'id' => 5,
            'name' => 'Sunridge Cozy Private Rooms',
            'address' => '12 Mabini Street, Greenview',
            'city' => 'Bayugan City',
            'description' => 'Peaceful, sunlit single rooms with personal study workstations, big sunny windows, and garden views. Perfect for focused learning.',
            'room_types' => 'Single Room',
            'gender_policy' => 'Co-ed',
            'price_range' => '₱3,400 - ₱4,200/mo',
            'min_price' => 3400,
            'max_price' => 4200,
            'available_rooms' => 4,
            'total_rooms' => 12,
            'rating' => 4.7,
            'reviews_count' => 34,
            'landlord_name' => 'Mrs. Teresa Morales',
            'landlord_phone' => '0933-776-5520',
            'landlord_email' => 'sunridge@boardinghub.com',
            'proximity' => '7 mins walk to City Library & Campus',
            'image_path' => '../images/boarding_room_single.jpg',
            'amenities' => '["Single Bed & Foam Mattress", "Personal Desk & Ergonomic Chair", "Spacious Wardrobe", "Free Drinking Water", "Fast WiFi", "Motorcycle Parking Space"]'
        ],
        [
            'id' => 6,
            'name' => 'Campus Gateway Student Commons',
            'address' => '7 College Way, North Campus',
            'city' => 'Bayugan City',
            'description' => 'The premier student hub with a 24/7 collaborative study lounge, coffee bar, and diverse room options for active collegiate life.',
            'room_types' => 'Bedspace, Single Room, Studio Pad',
            'gender_policy' => 'All',
            'price_range' => '₱2,500 - ₱5,800/mo',
            'min_price' => 2500,
            'max_price' => 5800,
            'available_rooms' => 7,
            'total_rooms' => 24,
            'rating' => 4.9,
            'reviews_count' => 63,
            'landlord_name' => 'Coach David Briggs',
            'landlord_phone' => '0945-889-1234',
            'landlord_email' => 'gateway@boardinghub.com',
            'proximity' => 'Across the street from Engineering Building',
            'image_path' => '../images/boarding_lounge.jpg',
            'amenities' => '["24/7 Co-working & Study Hall", "Pantry with Microwave & Coffee", "High Speed WiFi Mesh", "Laundry Washing Machines", "Bicycle Racks", "Air Conditioned Lounge"]'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BoardingHub - Find Your Perfect Boarding House & Dormitory</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>

<body>
  <!-- Loading Animation -->
  <div class="page-loader">
    <div class="loader">
      <div class="house-loader-icon">
        <i class="fas fa-home"></i>
        <div class="loader-pulse-ring"></div>
      </div>
      <p>Finding your next home at <strong>BoardingHub</strong>...</p>
    </div>
  </div>

  <header>
    <div class="logo">
      <a href="index.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">
        <div class="logo-icon-box">
          <i class="fas fa-building"></i>
        </div>
        <div>
          <h1>Boarding<span>Hub</span></h1>
          <span class="logo-sub">Dorm & House Finder</span>
        </div>
      </a>
    </div>

    <!-- Mobile Menu Button -->
    <div class="mobile-menu-btn">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <div class="navBar">
      <ul>
        <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="#browse-section"><i class="fas fa-compass"></i> Explore Houses</a></li>
        <li><a href="#room-types"><i class="fas fa-bed"></i> Room Types</a></li>
        <li><a href="#why-us"><i class="fas fa-shield-alt"></i> Why Choose Us</a></li>
        <?php if ($isLoggedIn): ?>
          <li><a href="dashboard.php" class="nav-highlight"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <?php if (Auth::isAdmin()): ?>
            <li><a href="logs.php"><i class="fas fa-history"></i> Logs</a></li>
          <?php endif; ?>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Password</a></li>
          <li><a href="logout.php" class="nav-logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
          <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
          <li><a href="login.php" class="nav-highlight"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </header>

  <main>
    <!-- HERO SECTION -->
    <section class="hero">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <div class="hero-badge">
          <i class="fas fa-check-circle"></i> #1 Verified Boarding House Finder in Bayugan City
        </div>
        <h2>Find Your Ideal <span>Boarding House</span> & <span>Dormitory</span></h2>
        <p>Discover clean, safe, and student-friendly boarding houses, private rooms, and bedspaces near colleges, universities, and commercial hubs.</p>
        
        <!-- Interactive Search Bar -->
        <div class="hero-search-container">
          <div class="search-input-group">
            <i class="fas fa-map-marker-alt"></i>
            <div class="input-col">
              <label>Location / Landmark</label>
              <input type="text" id="filter-location" placeholder="e.g. Near University, Poblacion, Central...">
            </div>
          </div>

          <div class="search-divider"></div>

          <div class="search-input-group">
            <i class="fas fa-door-open"></i>
            <div class="input-col">
              <label>Room Category</label>
              <select id="filter-room-type">
                <option value="">All Room Types</option>
                <option value="Single Room">Private Single Room</option>
                <option value="Bedspace">Shared Bedspace / Dorm</option>
                <option value="Studio Pad">Executive Studio Pad</option>
                <option value="Deluxe">Deluxe Room</option>
              </select>
            </div>
          </div>

          <div class="search-divider"></div>

          <div class="search-input-group">
            <i class="fas fa-tag"></i>
            <div class="input-col">
              <label>Monthly Budget</label>
              <select id="filter-budget">
                <option value="">Any Budget</option>
                <option value="3000">Up to ₱3,000 / month</option>
                <option value="4500">Up to ₱4,500 / month</option>
                <option value="6500">Up to ₱6,500 / month</option>
                <option value="10000">₱7,000+ / month</option>
              </select>
            </div>
          </div>

          <button type="button" class="hero-search-btn" onclick="executeSearch()">
            <i class="fas fa-search"></i>
            <span>Search Houses</span>
          </button>
        </div>

        <div class="hero-quick-tags">
          <span>Popular searches:</span>
          <button type="button" class="quick-pill" onclick="quickFilter('WiFi')"><i class="fas fa-wifi"></i> Fast WiFi</button>
          <button type="button" class="quick-pill" onclick="quickFilter('Air Conditioning')"><i class="fas fa-snowflake"></i> Aircon</button>
          <button type="button" class="quick-pill" onclick="quickFilter('Female Only')"><i class="fas fa-female"></i> Female Only</button>
          <button type="button" class="quick-pill" onclick="quickFilter('Private Bathroom')"><i class="fas fa-bath"></i> Own CR</button>
        </div>
      </div>

      <!-- Hero Stats -->
      <div class="hero-stats">
        <div class="stat">
          <div class="stat-icon"><i class="fas fa-home"></i></div>
          <div class="stat-info">
            <span class="stat-number" data-count="150">0</span><span class="stat-plus">+</span>
            <span class="stat-label">Verified Boarding Houses</span>
          </div>
        </div>
        <div class="stat">
          <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
          <div class="stat-info">
            <span class="stat-number" data-count="1850">0</span><span class="stat-plus">+</span>
            <span class="stat-label">Happy Students & Boarders</span>
          </div>
        </div>
        <div class="stat">
          <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="stat-info">
            <span class="stat-number" data-count="100">0</span><span class="stat-plus">%</span>
            <span class="stat-label">Safe & Landlord Verified</span>
          </div>
        </div>
      </div>
    </section>

    <!-- EXPLORE BOARDING HOUSES SECTION -->
    <section class="browse-section" id="browse-section">
      <div class="section-header">
        <div class="section-eyebrow"><i class="fas fa-fire"></i> Featured Listings</div>
        <h2>Explore Available Boarding Houses & Dorms</h2>
        <p>Choose from our top-rated student residences, bedspaces, and private studios with real photos and transparent rates</p>
      </div>

      <!-- Filter Tabs -->
      <div class="filter-tabs-container">
        <button class="filter-tab active" onclick="filterByTab('all')"><i class="fas fa-th"></i> All Boarding Houses</button>
        <button class="filter-tab" onclick="filterByTab('Single Room')"><i class="fas fa-user"></i> Single Rooms</button>
        <button class="filter-tab" onclick="filterByTab('Bedspace')"><i class="fas fa-bed"></i> Bedspaces</button>
        <button class="filter-tab" onclick="filterByTab('Studio Pad')"><i class="fas fa-door-closed"></i> Studio Pads</button>
        <button class="filter-tab" onclick="filterByTab('Female Only')"><i class="fas fa-female"></i> Female Only</button>
        <button class="filter-tab" onclick="filterByTab('budget')"><i class="fas fa-wallet"></i> Budget (Under ₱3.5k)</button>
      </div>

      <!-- Boarding Houses Grid -->
      <div class="houses-grid" id="houses-grid">
        <?php foreach ($boardingHouses as $house): 
          $amenitiesList = is_string($house['amenities']) ? json_decode($house['amenities'], true) : (array)$house['amenities'];
          if (!is_array($amenitiesList)) $amenitiesList = ["Free WiFi", "CCTV Security", "Study Area"];
        ?>
          <div class="house-card" 
               data-id="<?php echo $house['id']; ?>"
               data-name="<?php echo htmlspecialchars($house['name']); ?>"
               data-address="<?php echo htmlspecialchars($house['address']); ?>"
               data-roomtypes="<?php echo htmlspecialchars($house['room_types']); ?>"
               data-gender="<?php echo htmlspecialchars($house['gender_policy']); ?>"
               data-minprice="<?php echo (float)$house['min_price']; ?>"
               data-maxprice="<?php echo (float)$house['max_price']; ?>"
               data-amenities='<?php echo htmlspecialchars(json_encode($amenitiesList)); ?>'
               data-image="<?php echo htmlspecialchars($house['image_path']); ?>"
               data-landlord="<?php echo htmlspecialchars($house['landlord_name']); ?>"
               data-phone="<?php echo htmlspecialchars($house['landlord_phone']); ?>"
               data-desc="<?php echo htmlspecialchars($house['description']); ?>"
               data-proximity="<?php echo htmlspecialchars($house['proximity']); ?>"
               data-avail="<?php echo (int)$house['available_rooms']; ?>"
               data-rating="<?php echo $house['rating']; ?>">
            
            <div class="house-image-wrapper">
              <img src="<?php echo htmlspecialchars($house['image_path']); ?>" alt="<?php echo htmlspecialchars($house['name']); ?>" loading="lazy">
              
              <div class="house-badges">
                <span class="badge-status"><i class="fas fa-check-circle"></i> Verified</span>
                <?php if ($house['gender_policy'] === 'Female Only'): ?>
                  <span class="badge-gender female"><i class="fas fa-female"></i> Female Only</span>
                <?php elseif ($house['gender_policy'] === 'Male Only'): ?>
                  <span class="badge-gender male"><i class="fas fa-male"></i> Male Only</span>
                <?php else: ?>
                  <span class="badge-gender coed"><i class="fas fa-users"></i> <?php echo htmlspecialchars($house['gender_policy']); ?></span>
                <?php endif; ?>
              </div>

              <div class="house-price-tag">
                <span class="price-amount"><?php echo htmlspecialchars($house['price_range']); ?></span>
              </div>
            </div>

            <div class="house-details">
              <div class="house-rating-row">
                <div class="stars">
                  <i class="fas fa-star"></i>
                  <span><?php echo number_format((float)$house['rating'], 1); ?></span>
                  <small>(<?php echo (int)($house['reviews_count'] ?? 25); ?> reviews)</small>
                </div>
                <div class="slots-available">
                  <i class="fas fa-door-open"></i> <strong><?php echo (int)$house['available_rooms']; ?></strong> rooms left
                </div>
              </div>

              <h3 class="house-title"><?php echo htmlspecialchars($house['name']); ?></h3>
              <p class="house-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($house['address']); ?>, <?php echo htmlspecialchars($house['city']); ?></p>
              
              <div class="house-proximity">
                <i class="fas fa-walking"></i> <?php echo htmlspecialchars($house['proximity']); ?>
              </div>

              <div class="house-amenities-pills">
                <?php foreach (array_slice($amenitiesList, 0, 4) as $amenity): ?>
                  <span class="amenity-pill"><i class="fas fa-check"></i> <?php echo htmlspecialchars($amenity); ?></span>
                <?php endforeach; ?>
                <?php if (count($amenitiesList) > 4): ?>
                  <span class="amenity-pill more">+<?php echo count($amenitiesList) - 4; ?> more</span>
                <?php endif; ?>
              </div>

              <div class="house-card-footer">
                <button type="button" class="btn-card-details" onclick="openHouseModal(<?php echo $house['id']; ?>)">
                  <i class="fas fa-info-circle"></i> View Details
                </button>
                <button type="button" class="btn-card-reserve" onclick="initiateBooking(<?php echo $house['id']; ?>)">
                  <i class="fas fa-calendar-check"></i> Inquire / Book
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ROOM TYPES SHOWCASE SECTION -->
    <section class="room-types-section" id="room-types">
      <div class="section-header">
        <div class="section-eyebrow"><i class="fas fa-couch"></i> Accommodation Choices</div>
        <h2>Tailored Room Options for Every Boarder</h2>
        <p>Whether you prioritize budget, community, or total privacy for focused study, we have the perfect accommodation.</p>
      </div>

      <div class="room-types-grid">
        <div class="room-type-card">
          <div class="room-type-img">
            <img src="../images/boarding_room_single.jpg" alt="Private Single Room">
            <span class="room-type-badge">Most Focused</span>
          </div>
          <div class="room-type-body">
            <h3><i class="fas fa-user"></i> Private Single Room</h3>
            <p>Your personal sanctuary equipped with an ergonomic study desk, comfortable single bed, wardrobe, and natural window lighting.</p>
            <ul class="room-perks">
              <li><i class="fas fa-check-circle"></i> Dedicated Study Workstation</li>
              <li><i class="fas fa-check-circle"></i> Complete Peace & Quiet for Exams</li>
              <li><i class="fas fa-check-circle"></i> Shared or Semi-Private Bathroom</li>
              <li><i class="fas fa-check-circle"></i> Free Drinking Water & Fiber WiFi</li>
            </ul>
            <div class="room-price-info">
              <span>Starting from</span>
              <strong>₱3,200 / month</strong>
            </div>
            <button class="room-cta-btn" onclick="filterByTab('Single Room')">View Single Rooms</button>
          </div>
        </div>

        <div class="room-type-card featured-type">
          <div class="room-type-img">
            <img src="../images/boarding_room_bedspace.jpg" alt="Student Shared Bedspace">
            <span class="room-type-badge highlight">Best Value</span>
          </div>
          <div class="room-type-body">
            <h3><i class="fas fa-bed"></i> Student Shared Bedspace</h3>
            <p>Economical and organized shared rooms with sturdy wooden bunk beds, individual storage lockers, privacy curtains, and reading lamps.</p>
            <ul class="room-perks">
              <li><i class="fas fa-check-circle"></i> Budget-Friendly Monthly Rates</li>
              <li><i class="fas fa-check-circle"></i> Individual Security Lockers</li>
              <li><i class="fas fa-check-circle"></i> Privacy Bed Curtains & Reading Lamp</li>
              <li><i class="fas fa-check-circle"></i> Ideal for College Students & Friends</li>
            </ul>
            <div class="room-price-info">
              <span>Starting from</span>
              <strong>₱2,200 / month</strong>
            </div>
            <button class="room-cta-btn featured" onclick="filterByTab('Bedspace')">View Bedspace Listings</button>
          </div>
        </div>

        <div class="room-type-card">
          <div class="room-type-img">
            <img src="../images/boarding_room_studio.jpg" alt="Executive Studio Pad">
            <span class="room-type-badge">Premium Comfort</span>
          </div>
          <div class="room-type-body">
            <h3><i class="fas fa-door-closed"></i> Executive Studio Pad</h3>
            <p>All-inclusive modern studio unit featuring inverter air conditioning, private en-suite bathroom with hot shower, kitchenette, and city view.</p>
            <ul class="room-perks">
              <li><i class="fas fa-check-circle"></i> Private Bathroom (T&B) & Hot Shower</li>
              <li><i class="fas fa-check-circle"></i> Inverter Air Conditioning</li>
              <li><i class="fas fa-check-circle"></i> Mini Kitchenette & Refrigerator</li>
              <li><i class="fas fa-check-circle"></i> Premium Privacy for Reviewees & Pros</li>
            </ul>
            <div class="room-price-info">
              <span>Starting from</span>
              <strong>₱6,000 / month</strong>
            </div>
            <button class="room-cta-btn" onclick="filterByTab('Studio Pad')">View Studio Pads</button>
          </div>
        </div>
      </div>
    </section>

    <!-- WHY CHOOSE BOARDINGHUB -->
    <section class="why-us-section" id="why-us">
      <div class="section-header">
        <div class="section-eyebrow"><i class="fas fa-star"></i> The BoardingHub Advantage</div>
        <h2>Why Students & Parents Trust BoardingHub</h2>
        <p>Finding a boarding house shouldn't require walking for hours under the sun. We verify every property so you can rent with complete confidence.</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-check-double"></i></div>
          <h3>100% Verified Landlords</h3>
          <p>Every listed boarding house is verified with legitimate owner credentials, business permits, and on-site inspection.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-wifi"></i></div>
          <h3>High-Speed Fiber WiFi</h3>
          <p>No dropped Zoom lectures or lagging research. All partner houses provide fast, stable student internet connections.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-video"></i></div>
          <h3>24/7 Security & CCTV</h3>
          <p>Safety is non-negotiable. Enjoy biometric entrance gates, perimeter CCTV monitoring, and dedicated house matrons.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-walking"></i></div>
          <h3>Walkable to Campus</h3>
          <p>Save money and commuting time. Our boarding houses are located 2 to 10 minutes from campuses and major colleges.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-receipt"></i></div>
          <h3>Zero Hidden Charges</h3>
          <p>Clear, transparent breakdown of monthly rent, water, and electric consumption. No surprise charges upon move-in.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon"><i class="fas fa-headset"></i></div>
          <h3>Boarder Support & Maintenance</h3>
          <p>Quick tenant maintenance request portal. Report water leaks, electrical, or WiFi issues with real-time tracking.</p>
        </div>
      </div>
    </section>

    <!-- COMMON AMENITIES / LIFESTYLE GALLERY -->
    <section class="gallery-banner">
      <div class="gallery-content">
        <div class="gallery-text">
          <span class="gallery-tag"><i class="fas fa-coffee"></i> Student Community</span>
          <h2>Modern Amenities Built for Student Living</h2>
          <p>From communal study halls with air conditioning to shared kitchenettes with microwaves and drinking water dispensers, experience a warm, collaborative environment designed for academic success.</p>
          <div class="amenity-checklist">
            <div><i class="fas fa-check-circle"></i> Co-working Study Lounges</div>
            <div><i class="fas fa-check-circle"></i> Cooking Allowed in Kitchens</div>
            <div><i class="fas fa-check-circle"></i> Automated Laundry Washers</div>
            <div><i class="fas fa-check-circle"></i> Backup Power Generators</div>
          </div>
          <?php if (!$isLoggedIn): ?>
            <a href="register.php" class="cta-button"><i class="fas fa-user-plus"></i> Register as a Boarder Today</a>
          <?php else: ?>
            <a href="dashboard.php" class="cta-button"><i class="fas fa-tachometer-alt"></i> Go to Boarder Dashboard</a>
          <?php endif; ?>
        </div>
        <div class="gallery-img-box">
          <img src="../images/boarding_lounge.jpg" alt="Boarding House Study Lounge">
        </div>
      </div>
    </section>

    <!-- TESTIMONIALS SECTION -->
    <section class="testimonials" id="testimonials">
      <div class="section-header">
        <div class="section-eyebrow"><i class="fas fa-quote-left"></i> Resident Reviews</div>
        <h2>What Our Boarders Say</h2>
        <p>Real stories from college students, board exam reviewees, and young professionals who found their room on BoardingHub</p>
      </div>

      <div class="testimonials-container">
        <div class="testimonial-card">
          <div class="testimonial-rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <div class="testimonial-content">
            <p>"Finding a quiet room for my nursing board exams was stressful until I used BoardingHub. I found a private single room at Oakwood in 10 minutes. The WiFi is blazing fast and the landlord is so hospitable!"</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar"><i class="fas fa-user-graduate"></i></div>
            <div class="author-info">
              <h4>Kristine Joy Ramirez</h4>
              <span>Nursing Student & Boarder</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <div class="testimonial-content">
            <p>"The Elm's Haven studio unit is the best place I've stayed in during my 4 years of college. Having my own bathroom, balcony view, and AC makes studying feel comfortable and productive."</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar"><i class="fas fa-laptop-code"></i></div>
            <div class="author-info">
              <h4>Jervin Matthew Tan</h4>
              <span>IT Scholar, 3rd Year</span>
            </div>
          </div>
        </div>

        <div class="testimonial-card">
          <div class="testimonial-rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <div class="testimonial-content">
            <p>"As a parent from the province, safety was my number one worry. Pinecrest Ladies Dorm gave me peace of mind with their resident matron, night security, and clean facilities for my daughter."</p>
          </div>
          <div class="testimonial-author">
            <div class="author-avatar"><i class="fas fa-shield-alt"></i></div>
            <div class="author-info">
              <h4>Rosario Bautista</h4>
              <span>Parent of College Boarder</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ SECTION -->
    <section class="faq-section">
      <div class="section-header">
        <div class="section-eyebrow"><i class="fas fa-question-circle"></i> Got Questions?</div>
        <h2>Frequently Asked Questions</h2>
        <p>Everything you need to know about reserving and moving into your new boarding house</p>
      </div>

      <div class="faq-grid">
        <div class="faq-item">
          <h4><i class="fas fa-chevron-right"></i> How do I reserve a room or bedspace?</h4>
          <p>Browse our verified listings, click <strong>"Inquire / Book"</strong>, and submit your preferred move-in date. If you already have an account, the reservation request is sent directly to the landlord for confirmation.</p>
        </div>
        <div class="faq-item">
          <h4><i class="fas fa-chevron-right"></i> Are utilities (water & electricity) included?</h4>
          <p>Most bedspaces include basic water and electric fan usage. For studio units with air conditioning, individual sub-meters or fixed monthly rates apply as detailed on each house card.</p>
        </div>
        <div class="faq-item">
          <h4><i class="fas fa-chevron-right"></i> Can I view the boarding house in person?</h4>
          <p>Yes! Every verified listing displays the landlord's contact number and address so you can schedule an on-site ocular visit anytime.</p>
        </div>
        <div class="faq-item">
          <h4><i class="fas fa-chevron-right"></i> What are the typical contract terms?</h4>
          <p>Most boarding houses offer flexible terms: per semester (5 months), 1 year, or month-to-month contracts depending on property policies.</p>
        </div>
      </div>
    </section>
  </main>

  <!-- PROPERTY DETAILS & INQUIRY MODAL -->
  <div class="modal-backdrop" id="house-modal" style="display: none;">
    <div class="modal-card">
      <button type="button" class="modal-close-btn" onclick="closeHouseModal()">&times;</button>
      <div class="modal-grid">
        <div class="modal-media">
          <img id="modal-img" src="../images/boarding_house_exterior.jpg" alt="Boarding House Image">
          <div class="modal-media-overlay">
            <span class="modal-badge-roomtype" id="modal-roomtypes">Single, Bedspace</span>
            <span class="modal-badge-price" id="modal-price">₱3,500/mo</span>
          </div>
        </div>
        <div class="modal-info">
          <div class="modal-header-row">
            <span class="modal-rating"><i class="fas fa-star"></i> <span id="modal-rating">4.9</span> Verified</span>
            <span class="modal-gender" id="modal-gender">Co-ed</span>
          </div>
          <h2 id="modal-title">Oakwood Student Residences</h2>
          <p class="modal-address"><i class="fas fa-map-marker-alt"></i> <span id="modal-address">24 Acacia Street</span></p>
          <p class="modal-proximity"><i class="fas fa-walking"></i> <span id="modal-proximity">3 mins walk to Campus</span></p>

          <div class="modal-section">
            <h4>Description</h4>
            <p id="modal-desc">Safe, modern boarding house with high-speed internet.</p>
          </div>

          <div class="modal-section">
            <h4>Amenities & Inclusions</h4>
            <div class="modal-amenities-list" id="modal-amenities">
              <!-- Injected by JS -->
            </div>
          </div>

          <div class="modal-section landlord-box">
            <h4>Landlord & Property Contact</h4>
            <div class="landlord-info-row">
              <div><i class="fas fa-user-tie"></i> <strong>Host:</strong> <span id="modal-landlord">Atty. Roberto Dela Cruz</span></div>
              <div><i class="fas fa-phone-alt"></i> <strong>Phone:</strong> <span id="modal-phone">0917-882-9011</span></div>
            </div>
          </div>

          <div class="modal-actions">
            <?php if ($isLoggedIn): ?>
              <button type="button" class="modal-btn-confirm" onclick="proceedToReservation()">
                <i class="fas fa-calendar-check"></i> Reserve a Room Now
              </button>
            <?php else: ?>
              <a href="register.php" class="modal-btn-confirm">
                <i class="fas fa-user-plus"></i> Register to Reserve
              </a>
              <a href="login.php" class="modal-btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RESERVATION ACTION MODAL (For Logged In Users) -->
  <div class="modal-backdrop" id="reserve-modal" style="display: none;">
    <div class="modal-card small-modal">
      <button type="button" class="modal-close-btn" onclick="closeReserveModal()">&times;</button>
      <div class="reserve-form-container">
        <h3><i class="fas fa-bookmark"></i> Room Reservation Request</h3>
        <p>Send your reservation request to the landlord for <strong id="reserve-bh-name">Selected Boarding House</strong></p>
        
        <form id="public-reserve-form" onsubmit="submitReservationForm(event)">
          <input type="hidden" id="reserve-bh-id" value="">
          <input type="hidden" name="csrf_token" id="reserve-csrf" value="<?php echo Security::generateCSRFToken(); ?>">

          <div class="form-field">
            <label>Target Move-in Date</label>
            <input type="date" id="reserve-date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
          </div>

          <div class="form-field">
            <label>Duration of Stay</label>
            <select id="reserve-duration">
              <option value="1">1 Month (Trial / Transient)</option>
              <option value="5" selected>1 Semester (5 Months)</option>
              <option value="10">Academic Year (10 Months)</option>
              <option value="12">Full Year (12 Months)</option>
            </select>
          </div>

          <div class="form-field">
            <label>Contact Phone Number</label>
            <input type="tel" id="reserve-phone" placeholder="e.g. 0917-000-0000" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
          </div>

          <div class="form-field">
            <label>Special Requests or Questions</label>
            <textarea id="reserve-notes" rows="3" placeholder="e.g. Prefer lower bunk, bringing laptop & desktop, need motorcycle parking..."></textarea>
          </div>

          <div id="reserve-alert" style="display:none; margin-bottom: 12px; font-size: 13px; padding: 10px; border-radius: 6px;"></div>

          <button type="submit" class="modal-btn-confirm" id="btn-submit-reserve">
            <i class="fas fa-paper-plane"></i> Submit Reservation
          </button>
        </form>
      </div>
    </div>
  </div>

  <footer>
    <div class="footer-content">
      <div class="footer-section">
        <div class="logo">
          <h1>Boarding<span>Hub</span></h1>
          <p class="tagline">Bayugan City Boarding House Finder</p>
        </div>
        <p>Your trusted platform for discovering safe, verified, and affordable student dormitories, bedspaces, and private boarding houses near academic institutions.</p>
        <div class="social-icons">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <div class="footer-section">
        <h3>Quick Links</h3>
        <ul>
          <li><a href="index.php"><i class="fas fa-angle-right"></i> Home</a></li>
          <li><a href="#browse-section"><i class="fas fa-angle-right"></i> Explore Houses</a></li>
          <li><a href="#room-types"><i class="fas fa-angle-right"></i> Room Types</a></li>
          <?php if ($isLoggedIn): ?>
            <li><a href="dashboard.php"><i class="fas fa-angle-right"></i> Tenant Dashboard</a></li>
            <li><a href="change-password.php"><i class="fas fa-angle-right"></i> Change Password</a></li>
          <?php else: ?>
            <li><a href="login.php"><i class="fas fa-angle-right"></i> Login</a></li>
            <li><a href="register.php"><i class="fas fa-angle-right"></i> Register as Boarder</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="footer-section">
        <h3>Room Categories</h3>
        <ul>
          <li><a href="javascript:filterByTab('Single Room')"><i class="fas fa-angle-right"></i> Private Single Rooms</a></li>
          <li><a href="javascript:filterByTab('Bedspace')"><i class="fas fa-angle-right"></i> Shared Bedspaces</a></li>
          <li><a href="javascript:filterByTab('Studio Pad')"><i class="fas fa-angle-right"></i> Executive Studio Pads</a></li>
          <li><a href="javascript:filterByTab('Female Only')"><i class="fas fa-angle-right"></i> Ladies Dormitories</a></li>
        </ul>
      </div>

      <div class="footer-section">
        <h3>Contact & Support</h3>
        <ul class="contact-info">
          <li><i class="fas fa-map-marker-alt"></i> 128 University Avenue, Bayugan City, Agusan del Sur</li>
          <li><i class="fas fa-phone"></i> (085) 233-1288 / 0917-882-9011</li>
          <li><i class="fas fa-envelope"></i> help@boardinghub.com</li>
          <li><i class="fas fa-clock"></i> Mon - Sat: 8:00 AM - 6:00 PM</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="copyright">&copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved. Connecting boarders to verified homes.</p>
    </div>
  </footer>

  <script src="../js/loader.js"></script>
  <script>
    // Current active house for modal
    let activeHouseId = null;

    function openHouseModal(id) {
      const card = document.querySelector(`.house-card[data-id="${id}"]`);
      if (!card) return;

      activeHouseId = id;
      document.getElementById('modal-title').textContent = card.dataset.name;
      document.getElementById('modal-address').textContent = card.dataset.address;
      document.getElementById('modal-proximity').textContent = card.dataset.proximity;
      document.getElementById('modal-desc').textContent = card.dataset.desc;
      document.getElementById('modal-landlord').textContent = card.dataset.landlord;
      document.getElementById('modal-phone').textContent = card.dataset.phone;
      document.getElementById('modal-img').src = card.dataset.image;
      document.getElementById('modal-roomtypes').textContent = card.dataset.roomtypes;
      document.getElementById('modal-price').textContent = card.querySelector('.price-amount').textContent;
      document.getElementById('modal-gender').textContent = card.dataset.gender;
      document.getElementById('modal-rating').textContent = card.dataset.rating;

      // Amenities
      try {
        const amenities = JSON.parse(card.dataset.amenities);
        const container = document.getElementById('modal-amenities');
        container.innerHTML = '';
        amenities.forEach(a => {
          const span = document.createElement('span');
          span.className = 'modal-amenity-tag';
          span.innerHTML = `<i class="fas fa-check-circle"></i> ${a}`;
          container.appendChild(span);
        });
      } catch(e) {}

      document.getElementById('house-modal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeHouseModal() {
      document.getElementById('house-modal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    function initiateBooking(id) {
      <?php if (!$isLoggedIn): ?>
        window.location.href = 'login.php';
        return;
      <?php else: ?>
        const card = document.querySelector(`.house-card[data-id="${id}"]`);
        if (!card) return;
        activeHouseId = id;
        document.getElementById('reserve-bh-id').value = id;
        document.getElementById('reserve-bh-name').textContent = card.dataset.name;
        document.getElementById('reserve-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
      <?php endif; ?>
    }

    function proceedToReservation() {
      closeHouseModal();
      initiateBooking(activeHouseId);
    }

    function closeReserveModal() {
      document.getElementById('reserve-modal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    // Interactive Tab Filtering
    function filterByTab(category) {
      document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
      const activeBtn = event ? event.target.closest('.filter-tab') : null;
      if (activeBtn) activeBtn.classList.add('active');

      const cards = document.querySelectorAll('.house-card');
      cards.forEach(card => {
        const roomtypes = card.dataset.roomtypes.toLowerCase();
        const gender = card.dataset.gender.toLowerCase();
        const minPrice = parseFloat(card.dataset.minprice);

        if (category === 'all') {
          card.style.display = 'flex';
        } else if (category === 'budget') {
          card.style.display = minPrice <= 3500 ? 'flex' : 'none';
        } else if (category === 'Female Only') {
          card.style.display = gender.includes('female') ? 'flex' : 'none';
        } else {
          card.style.display = roomtypes.includes(category.toLowerCase()) ? 'flex' : 'none';
        }
      });

      // Scroll to browse section if triggered
      const section = document.getElementById('browse-section');
      if (section && event && event.type === 'click') {
        section.scrollIntoView({ behavior: 'smooth' });
      }
    }

    // Hero Search Handler
    function executeSearch() {
      const loc = document.getElementById('filter-location').value.toLowerCase().trim();
      const roomType = document.getElementById('filter-room-type').value.toLowerCase().trim();
      const maxBudget = parseFloat(document.getElementById('filter-budget').value) || 999999;

      const cards = document.querySelectorAll('.house-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const address = card.dataset.address.toLowerCase();
        const proximity = card.dataset.proximity.toLowerCase();
        const roomtypes = card.dataset.roomtypes.toLowerCase();
        const minPrice = parseFloat(card.dataset.minprice);

        const matchLoc = !loc || name.includes(loc) || address.includes(loc) || proximity.includes(loc);
        const matchRoom = !roomType || roomtypes.includes(roomType);
        const matchPrice = minPrice <= maxBudget;

        if (matchLoc && matchRoom && matchPrice) {
          card.style.display = 'flex';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      document.getElementById('browse-section').scrollIntoView({ behavior: 'smooth' });
    }

    // Quick tag filter
    function quickFilter(term) {
      const cards = document.querySelectorAll('.house-card');
      cards.forEach(card => {
        const text = (card.dataset.amenities + ' ' + card.dataset.gender + ' ' + card.dataset.roomtypes).toLowerCase();
        if (text.includes(term.toLowerCase())) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
      document.getElementById('browse-section').scrollIntoView({ behavior: 'smooth' });
    }

    // Submit Reservation
    async function submitReservationForm(e) {
      e.preventDefault();
      const houseId = document.getElementById('reserve-bh-id').value;
      const moveInDate = document.getElementById('reserve-date').value;
      const duration = document.getElementById('reserve-duration').value;
      const phone = document.getElementById('reserve-phone').value;
      const notes = document.getElementById('reserve-notes').value;
      const csrf = document.getElementById('reserve-csrf').value;
      const alertBox = document.getElementById('reserve-alert');
      const submitBtn = document.getElementById('btn-submit-reserve');

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

      try {
        const res = await fetch('user_actions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'reserve_boarding_house',
            boarding_house_id: houseId,
            target_move_in_date: moveInDate,
            stay_duration_months: duration,
            contact_phone: phone,
            special_requests: notes,
            csrf_token: csrf
          })
        });

        const data = await res.json();
        alertBox.style.display = 'block';

        if (data.success) {
          alertBox.style.backgroundColor = 'rgba(16, 185, 129, 0.15)';
          alertBox.style.border = '1px solid #10b981';
          alertBox.style.color = '#10b981';
          alertBox.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
          setTimeout(() => {
            closeReserveModal();
            window.location.href = 'dashboard.php#bookings';
          }, 1500);
        } else {
          alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
          alertBox.style.border = '1px solid #ef4444';
          alertBox.style.color = '#ef4444';
          alertBox.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
        }
      } catch (err) {
        alertBox.style.display = 'block';
        alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
        alertBox.style.border = '1px solid #ef4444';
        alertBox.style.color = '#ef4444';
        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network error. Please try again.';
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Reservation';
      }
    }

    // Close modal on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeHouseModal();
        closeReserveModal();
      }
    });

    // Close on clicking backdrop
    window.onclick = function(e) {
      const houseModal = document.getElementById('house-modal');
      const reserveModal = document.getElementById('reserve-modal');
      if (e.target === houseModal) closeHouseModal();
      if (e.target === reserveModal) closeReserveModal();
    };
  </script>
</body>

</html>