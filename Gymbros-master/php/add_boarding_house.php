<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';

if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

if (Auth::needsFirstLoginSetup()) {
  header("Location: first-login-setup.php");
  exit();
}

$user = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();

// Super Admin, or an Admin holding the "Manage Boarding Houses & Rooms" privilege
if (!Auth::isAdmin() || (!$isSuperAdmin && !Auth::hasPrivilege('can_manage_classes'))) {
  header("Location: dashboard.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

$pendingRequestsTotal = 0;
$resP = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending'");
if ($resP) $pendingRequestsTotal += (int)$resP->fetch_assoc()['cnt'];
$resD = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
if ($resD) $pendingRequestsTotal += (int)$resD->fetch_assoc()['cnt'];

$csrfToken = Security::generateCSRFToken();

$amenityPresets = [
  'High-Speed WiFi', '24/7 CCTV & Security', 'Air Conditioning', 'Private Bathroom (CR)',
  'Kitchen Access', 'Laundry Area', 'Study Area', 'Free Drinking Water',
  'Motorcycle Parking', 'Backup Generator', 'Curfew & Night Guard', 'Lockers & Storage'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Post a Boarding House | BoardingHub</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/admin.css">
  <link rel="stylesheet" href="../css/theme.css?v=<?php echo filemtime(__DIR__ . '/../css/theme.css'); ?>">
</head>

<body>
  <input type="hidden" id="csrf_token_val" value="<?php echo $csrfToken; ?>">

  <div class="page-loader">
    <div class="loader">
      <div class="house-loader-icon">
        <i class="fas fa-home"></i>
        <div class="loader-pulse-ring"></div>
      </div>
      <p>Loading Form...</p>
    </div>
  </div>

  <div class="admin-layout-wrapper">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-brand">
        <div class="logo">
          <h1>Boarding<span>Hub</span></h1>
        </div>
        <div class="role-badge-pill">
          <?php if ($isSuperAdmin): ?>
            <span class="badge-superadmin"><i class="fas fa-crown"></i> SUPER ADMIN</span>
          <?php else: ?>
            <span class="badge-admin"><i class="fas fa-shield-alt"></i> PROPERTY ADMIN</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="sidebar-user">
        <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
        <div class="user-info">
          <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
          <div class="user-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-users-cog"></i> <span>Accounts & Properties</span></a></li>
          <li>
            <a href="admin_requests.php">
              <i class="fas fa-clipboard-list"></i> <span>Requests Queue</span>
              <?php if ($pendingRequestsTotal > 0): ?>
                <span class="nav-badge"><?php echo $pendingRequestsTotal; ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li><a href="privileges.php"><i class="fas fa-user-shield"></i> <span>Privileges</span></a></li>
          <?php if ($isSuperAdmin): ?>
            <li><a href="create_account.php"><i class="fas fa-user-plus"></i> <span>Create Account</span></a></li>
          <?php endif; ?>
          <li><a href="add_boarding_house.php" class="active"><i class="fas fa-house-medical"></i> <span>Post Boarding House</span></a></li>
          <li><a href="logs.php"><i class="fas fa-history"></i> <span>System Logs</span></a></li>
          <li class="nav-divider"></li>
          <li><a href="change-password.php"><i class="fas fa-key"></i> <span>Change Password</span></a></li>
        </ul>
      </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="admin-main-wrapper">
      <header class="admin-topbar">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Navigation"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
          <h3><i class="fas fa-house-medical"></i> Post a Boarding House</h3>
        </div>
        <div class="topbar-right">
          <div class="topbar-user-chip"><i class="fas fa-user-shield"></i> <span>@<?php echo htmlspecialchars($user['username']); ?></span></div>
          <a href="logout.php" class="btn-topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </header>

      <div class="admin-page-content">
        <div class="admin-header">
          <div class="admin-header-title">
            <h2><i class="fas fa-house-medical"></i> Post a New Boarding House</h2>
            <p>Fill in the property details below. The listing goes live on the public catalog as soon as you publish it.</p>
          </div>
          <a href="dashboard.php" class="btn-secondary-action"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="bh-post-layout">
          <form id="bhPostForm" class="bh-post-form" novalidate>

            <!-- 1. Property -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">1</span><h3>Property Details</h3></div>
              <div class="form-grid">
                <div class="form-field bh-span-2">
                  <label for="bh-name">Property Name *</label>
                  <input type="text" id="bh-name" name="name" maxlength="150" placeholder="e.g. Sunrise Student Residences" required>
                </div>
                <div class="form-field bh-span-2">
                  <label for="bh-description">Description *</label>
                  <textarea id="bh-description" name="description" rows="4" maxlength="1500" placeholder="Describe the place: building, environment, house rules, what makes it a good stay..." required></textarea>
                  <small class="bh-hint"><span id="bh-desc-count">0</span> / 1500 &middot; at least 20 characters</small>
                </div>
                <div class="form-field">
                  <label for="bh-gender">Gender Policy *</label>
                  <select id="bh-gender" name="gender_policy">
                    <option value="All">All / Anyone</option>
                    <option value="Co-ed">Co-ed</option>
                    <option value="Female Only">Female Only</option>
                    <option value="Male Only">Male Only</option>
                  </select>
                </div>
                <div class="form-field">
                  <label>Room Types *</label>
                  <div class="bh-chip-group" id="bh-room-types">
                    <?php foreach (['Single Room', 'Bedspace', 'Studio Pad', 'Deluxe'] as $i => $rt): ?>
                      <label class="bh-chip"><input type="checkbox" name="room_types[]" value="<?php echo $rt; ?>" <?php echo $i === 0 ? 'checked' : ''; ?>><span><?php echo $rt; ?></span></label>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </section>

            <!-- 2. Location -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">2</span><h3>Location</h3></div>
              <div class="form-grid">
                <div class="form-field">
                  <label for="bh-address">Street Address & Barangay *</label>
                  <input type="text" id="bh-address" name="address" maxlength="255" placeholder="e.g. 24 Acacia Street, Poblacion" required>
                </div>
                <div class="form-field">
                  <label for="bh-city">City / Municipality *</label>
                  <input type="text" id="bh-city" name="city" maxlength="100" value="Bayugan City" required>
                </div>
                <div class="form-field bh-span-2">
                  <label for="bh-proximity">Nearby Landmark</label>
                  <input type="text" id="bh-proximity" name="proximity" maxlength="150" placeholder="e.g. 3 mins walk to the University Main Gate">
                </div>
              </div>
            </section>

            <!-- 3. Rooms & pricing -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">3</span><h3>Rooms & Monthly Rent</h3></div>
              <div class="form-grid bh-grid-4">
                <div class="form-field">
                  <label for="bh-min">Minimum Rent (₱) *</label>
                  <input type="number" id="bh-min" name="min_price" min="1" step="50" placeholder="2500" required>
                </div>
                <div class="form-field">
                  <label for="bh-max">Maximum Rent (₱) *</label>
                  <input type="number" id="bh-max" name="max_price" min="1" step="50" placeholder="6500" required>
                </div>
                <div class="form-field">
                  <label for="bh-total">Total Rooms *</label>
                  <input type="number" id="bh-total" name="total_rooms" min="1" max="1000" placeholder="12" required>
                </div>
                <div class="form-field">
                  <label for="bh-available">Available Now *</label>
                  <input type="number" id="bh-available" name="available_rooms" min="0" max="1000" placeholder="4" required>
                </div>
              </div>
            </section>

            <!-- 4. Amenities -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">4</span><h3>Amenities</h3></div>
              <div class="bh-chip-group" id="bh-amenities">
                <?php foreach ($amenityPresets as $i => $am): ?>
                  <label class="bh-chip"><input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($am); ?>" <?php echo $i < 2 ? 'checked' : ''; ?>><span><?php echo htmlspecialchars($am); ?></span></label>
                <?php endforeach; ?>
              </div>
              <div class="bh-add-amenity">
                <input type="text" id="bh-custom-amenity" maxlength="60" placeholder="Add another amenity, e.g. Rooftop Deck">
                <button type="button" class="btn-secondary-action" id="bh-add-amenity-btn"><i class="fas fa-plus"></i> Add</button>
              </div>
            </section>

            <!-- 5. Contact -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">5</span><h3>Landlord / Contact Person</h3></div>
              <div class="form-grid">
                <div class="form-field">
                  <label for="bh-landlord">Contact Name *</label>
                  <input type="text" id="bh-landlord" name="landlord_name" maxlength="100" placeholder="e.g. Maria Santos" required>
                </div>
                <div class="form-field">
                  <label for="bh-phone">Contact Number *</label>
                  <input type="tel" id="bh-phone" name="landlord_phone" maxlength="20" placeholder="e.g. 0917-555-0192" required>
                </div>
                <div class="form-field bh-span-2">
                  <label for="bh-email">Contact Email</label>
                  <input type="email" id="bh-email" name="landlord_email" maxlength="100" placeholder="optional">
                </div>
              </div>
            </section>

            <!-- 6. Photo -->
            <section class="admin-table-card bh-section">
              <div class="bh-section-head"><span class="bh-step">6</span><h3>Cover Photo</h3></div>
              <label class="bh-dropzone" id="bh-dropzone" for="bh-image">
                <input type="file" id="bh-image" name="image" accept="image/jpeg,image/png,image/webp">
                <i class="fas fa-cloud-upload-alt"></i>
                <strong>Click to upload or drag a photo here</strong>
                <span>JPG, PNG or WebP &middot; up to 5 MB. A default photo is used if you skip this.</span>
              </label>
              <div class="bh-file-row" id="bh-file-row" hidden>
                <i class="fas fa-image"></i> <span id="bh-file-name"></span>
                <button type="button" class="bh-file-remove" id="bh-file-remove" title="Remove photo"><i class="fas fa-times"></i></button>
              </div>
            </section>

            <div class="bh-submit-bar">
              <a href="dashboard.php" class="btn-secondary-action">Cancel</a>
              <button type="submit" class="btn-primary-action" id="bh-submit"><i class="fas fa-paper-plane"></i> Publish Listing</button>
            </div>
          </form>

          <!-- Live preview -->
          <aside class="bh-preview">
            <div class="bh-preview-label"><i class="fas fa-eye"></i> Live preview</div>
            <div class="house-card">
              <div class="house-image-wrapper">
                <img id="pv-img" src="../images/boarding_house_exterior.jpg" alt="Listing preview">
                <div class="house-badges">
                  <span class="badge-status"><i class="fas fa-check-circle"></i> Verified</span>
                  <span class="badge-gender coed" id="pv-gender"><i class="fas fa-users"></i> All</span>
                </div>
                <div class="house-price-tag"><span class="price-amount" id="pv-price">₱— /mo</span></div>
              </div>
              <div class="house-details">
                <div class="house-rating-row">
                  <div class="stars"><span class="badge-new"><i class="fas fa-certificate"></i> New listing</span></div>
                  <div class="slots-available"><i class="fas fa-door-open"></i> <strong id="pv-slots">0</strong> rooms left</div>
                </div>
                <h3 class="house-title" id="pv-name">Your boarding house name</h3>
                <p class="house-location"><i class="fas fa-map-marker-alt"></i> <span id="pv-address">Street address, Bayugan City</span></p>
                <p class="house-proximity" id="pv-proximity-wrap" hidden><i class="fas fa-walking"></i> <span id="pv-proximity"></span></p>
                <div class="house-amenities-pills" id="pv-amenities"></div>
              </div>
            </div>
            <p class="bh-preview-note"><i class="fas fa-user-check"></i> Posted by <strong>@<?php echo htmlspecialchars($user['username']); ?></strong></p>
          </aside>
        </div>
      </div>

      <footer class="admin-footer">
        <p class="admin-footer-copyright">&copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved.</p>
      </footer>
    </div>
  </div>

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
  <script>
  (function () {
    const form = document.getElementById('bhPostForm');
    const $ = (id) => document.getElementById(id);
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH');
    const defaultImg = '../images/boarding_house_exterior.jpg';
    let previewUrl = null;

    function selectedAmenities() {
      return [...form.querySelectorAll('input[name="amenities[]"]:checked')].map(i => i.value);
    }

    function updatePreview() {
      $('pv-name').textContent = $('bh-name').value.trim() || 'Your boarding house name';
      const addr = [$('bh-address').value.trim(), $('bh-city').value.trim()].filter(Boolean).join(', ');
      $('pv-address').textContent = addr || 'Street address, Bayugan City';
      const prox = $('bh-proximity').value.trim();
      $('pv-proximity').textContent = prox;
      $('pv-proximity-wrap').hidden = !prox;

      const min = parseFloat($('bh-min').value), max = parseFloat($('bh-max').value);
      $('pv-price').textContent = (min > 0 && max > 0) ? peso(min) + ' - ' + peso(max) + '/mo' : '₱— /mo';
      $('pv-slots').textContent = parseInt($('bh-available').value, 10) || 0;

      const g = $('bh-gender').value;
      const gEl = $('pv-gender');
      gEl.className = 'badge-gender ' + (g === 'Female Only' ? 'female' : g === 'Male Only' ? 'male' : 'coed');
      gEl.innerHTML = '<i class="fas ' + (g === 'Female Only' ? 'fa-female' : g === 'Male Only' ? 'fa-male' : 'fa-users') + '"></i> ' + escapeHtml(g);

      const ams = selectedAmenities();
      $('pv-amenities').innerHTML = ams.slice(0, 3).map(a => '<span class="amenity-pill">' + escapeHtml(a) + '</span>').join('') +
        (ams.length > 3 ? '<span class="amenity-pill more">+' + (ams.length - 3) + ' more</span>' : '');

      $('bh-desc-count').textContent = $('bh-description').value.length;
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);

    // Custom amenities
    function addAmenity() {
      const input = $('bh-custom-amenity');
      const val = input.value.trim().replace(/\s+/g, ' ');
      if (!val) return;
      const exists = [...form.querySelectorAll('input[name="amenities[]"]')].find(i => i.value.toLowerCase() === val.toLowerCase());
      if (exists) { exists.checked = true; }
      else {
        const label = document.createElement('label');
        label.className = 'bh-chip';
        label.innerHTML = '<input type="checkbox" name="amenities[]" checked><span></span>';
        label.querySelector('input').value = val;
        label.querySelector('span').textContent = val;
        $('bh-amenities').appendChild(label);
      }
      input.value = '';
      updatePreview();
    }
    $('bh-add-amenity-btn').addEventListener('click', addAmenity);
    $('bh-custom-amenity').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addAmenity(); } });

    // Photo upload + preview
    const fileInput = $('bh-image');
    const dropzone = $('bh-dropzone');

    function setFile(file) {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = null;
      if (!file) {
        fileInput.value = '';
        $('pv-img').src = defaultImg;
        $('bh-file-row').hidden = true;
        dropzone.hidden = false;
        return;
      }
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        showToast('Photo must be a JPG, PNG, or WebP image', 'error');
        return setFile(null);
      }
      if (file.size > 5 * 1024 * 1024) {
        showToast('Photo must be 5 MB or smaller', 'error');
        return setFile(null);
      }
      previewUrl = URL.createObjectURL(file);
      $('pv-img').src = previewUrl;
      $('bh-file-name').textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
      $('bh-file-row').hidden = false;
      dropzone.hidden = true;
    }

    fileInput.addEventListener('change', () => setFile(fileInput.files[0] || null));
    $('bh-file-remove').addEventListener('click', () => setFile(null));
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('dragging'); }));
    dropzone.addEventListener('drop', (e) => {
      const file = e.dataTransfer.files[0];
      if (!file) return;
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      setFile(file);
    });

    // Client-side checks mirror the server rules
    function validate() {
      const v = (id) => $(id).value.trim();
      const num = (id) => parseFloat($(id).value);
      if (v('bh-name').length < 3) return ['bh-name', 'Property name must be at least 3 characters'];
      if (v('bh-description').length < 20) return ['bh-description', 'Description must be at least 20 characters'];
      if (!form.querySelector('input[name="room_types[]"]:checked')) return [null, 'Select at least one room type'];
      if (v('bh-address').length < 5) return ['bh-address', 'Please enter the full street address'];
      if (!v('bh-city')) return ['bh-city', 'City / Municipality is required'];
      if (!(num('bh-min') > 0)) return ['bh-min', 'Enter the minimum monthly rent'];
      if (!(num('bh-max') > 0)) return ['bh-max', 'Enter the maximum monthly rent'];
      if (num('bh-min') > num('bh-max')) return ['bh-min', 'Minimum rent cannot be higher than maximum rent'];
      if (!(num('bh-total') >= 1)) return ['bh-total', 'Enter the total number of rooms'];
      if (!(num('bh-available') >= 0) || num('bh-available') > num('bh-total')) return ['bh-available', 'Available rooms must be between 0 and the total rooms'];
      if (!selectedAmenities().length) return [null, 'Add at least one amenity'];
      if (!v('bh-landlord')) return ['bh-landlord', 'Contact name is required'];
      if (!/^[0-9+\-\s()]{7,20}$/.test(v('bh-phone'))) return ['bh-phone', 'Enter a valid contact number'];
      if (v('bh-email') && !$('bh-email').checkValidity()) return ['bh-email', 'Enter a valid contact email'];
      return null;
    }

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const problem = validate();
      if (problem) {
        showToast(problem[1], 'error');
        if (problem[0]) { $(problem[0]).focus(); $(problem[0]).scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        return;
      }

      const btn = $('bh-submit');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';

      const data = new FormData(form);
      data.append('action', 'add_boarding_house');
      data.append('csrf_token', $('csrf_token_val').value);

      fetch(getAdminApiUrl(), { method: 'POST', body: data })
        .then(res => res.json())
        .then(res => {
          if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => { window.location.href = 'dashboard.php#properties'; }, 1200);
          } else {
            showToast(res.message || 'Could not publish the listing', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Publish Listing';
          }
        })
        .catch(() => {
          showToast('Network error. Please try again.', 'error');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Publish Listing';
        });
    });

    updatePreview();
  })();
  </script>
</body>

</html>
