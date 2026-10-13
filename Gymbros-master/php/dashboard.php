<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect to login if not logged in
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
$isAdmin = Auth::isAdmin();

$db = new Database();
$conn = $db->getConnection();

$allUsers = [];
$stats = [
  'total_users' => 0,
  'total_admins' => 0,
  'total_superadmins' => 0,
  'pending_count' => 0,
  'blocked_count' => 0,
  'delete_requests_count' => 0
];

// Boarding system variables
$boardingHouses = [];
$myReservations = [];
$myMaintenance = [];
$allReservations = [];
$allMaintenance = [];
$pendingReservationsCount = 0;
$openMaintenanceCount = 0;

if ($isAdmin) {
  // Fetch user stats
  $res = $conn->query("SELECT role, status, COUNT(*) as cnt FROM users GROUP BY role, status");
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      if ($row['role'] === 'superadmin') $stats['total_superadmins'] += $row['cnt'];
      if ($row['role'] === 'admin') $stats['total_admins'] += $row['cnt'];
      if ($row['role'] === 'user') $stats['total_users'] += $row['cnt'];
      if ($row['status'] === 'pending') $stats['pending_count'] += $row['cnt'];
      if ($row['status'] === 'blocked') $stats['blocked_count'] += $row['cnt'];
    }
  }

  // Fetch delete requests count
  $resDel = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
  if ($resDel) {
    $stats['delete_requests_count'] = (int)$resDel->fetch_assoc()['cnt'];
  }

  // Fetch all boarding houses
  $bhRes = $conn->query("SELECT * FROM boarding_houses ORDER BY rating DESC");
  if ($bhRes) {
    while ($r = $bhRes->fetch_assoc()) {
      $boardingHouses[] = $r;
    }
  }

  // Fetch all reservations for admin view
  $resRes = $conn->query("SELECT r.*, u.username, u.first_name, u.last_name, bh.name as bh_name, bh.address as bh_address, bh.price_range 
    FROM room_reservations r 
    LEFT JOIN users u ON r.user_id = u.id_number 
    LEFT JOIN boarding_houses bh ON r.boarding_house_id = bh.id 
    ORDER BY r.created_at DESC LIMIT 50");
  if ($resRes) {
    while ($r = $resRes->fetch_assoc()) {
      $allReservations[] = $r;
      if ($r['status'] === 'pending') $pendingReservationsCount++;
    }
  }

  // Fetch all maintenance tickets
  $maintRes = $conn->query("SELECT m.*, u.username, u.first_name, u.last_name 
    FROM maintenance_requests m 
    LEFT JOIN users u ON m.user_id = u.id_number 
    ORDER BY m.created_at DESC LIMIT 50");
  if ($maintRes) {
    while ($m = $maintRes->fetch_assoc()) {
      $allMaintenance[] = $m;
      if (in_array($m['status'], ['pending', 'in_progress'])) $openMaintenanceCount++;
    }
  }

  // Fetch all users
  if ($isSuperAdmin) {
    $resUsers = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
  } else {
    $resUsers = $conn->query("SELECT * FROM users WHERE role != 'superadmin' ORDER BY created_at DESC");
  }
  if ($resUsers) {
    while ($r = $resUsers->fetch_assoc()) {
      $allUsers[] = $r;
    }
  }
} else {
  // Sync fresh user row from DB
  $stmtUser = $conn->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
  $stmtUser->bind_param("s", $user['id_number']);
  $stmtUser->execute();
  $uRes = $stmtUser->get_result();
  if ($uRes && $uRes->num_rows === 1) {
    $user = $uRes->fetch_assoc();
    $_SESSION['user'] = $user;
  }
  $stmtUser->close();

  // Fetch all boarding houses
  $bhRes = $conn->query("SELECT * FROM boarding_houses ORDER BY rating DESC");
  if ($bhRes) {
    while ($r = $bhRes->fetch_assoc()) {
      $boardingHouses[] = $r;
    }
  }

  // User's room reservations
  $stmt = $conn->prepare("SELECT r.*, bh.name as bh_name, bh.address as bh_address, bh.price_range, bh.image_path, bh.landlord_name, bh.landlord_phone 
    FROM room_reservations r 
    JOIN boarding_houses bh ON r.boarding_house_id = bh.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $rRes = $stmt->get_result();
  while ($row = $rRes->fetch_assoc()) {
    $myReservations[] = $row;
  }
  $stmt->close();

  // User's maintenance requests
  $stmt = $conn->prepare("SELECT * FROM maintenance_requests WHERE user_id = ? ORDER BY created_at DESC");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $mRes = $stmt->get_result();
  while ($row = $mRes->fetch_assoc()) {
    $myMaintenance[] = $row;
  }
  $stmt->close();
}

$pendingRequestsTotal = $stats['pending_count'] + $stats['delete_requests_count'];

// Calculate member since duration
$joinDate = new DateTime($user['created_at'] ?? date('Y-m-d H:i:s'));
$currentDate = new DateTime();
$membershipDuration = $currentDate->diff($joinDate);
$months = ($membershipDuration->y * 12) + $membershipDuration->m;
$memberSinceText = $months > 0 ? "$months month" . ($months > 1 ? 's' : '') : 'Less than a month';

// Find active room assignment for tenant
$activeAccommodation = null;
if (!empty($myReservations)) {
  foreach ($myReservations as $resv) {
    if ($resv['status'] === 'approved' || $resv['status'] === 'active') {
      $activeAccommodation = $resv;
      break;
    }
  }
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isSuperAdmin ? 'Super Admin Console' : ($isAdmin ? 'Property Admin Console' : 'Boarder Dashboard'); ?> | BoardingHub</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
  <?php else: ?>
    <link rel="stylesheet" href="../css/dashboard.css?v=<?php echo time(); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="../css/theme.css?v=<?php echo filemtime(__DIR__ . '/../css/theme.css'); ?>">
</head>

<body>
  <input type="hidden" id="csrf_token_val" value="<?php echo $csrfToken; ?>">
  <input type="hidden" id="current-user-id" value="<?php echo htmlspecialchars($user['id_number']); ?>">

  <!-- Loading Animation -->
  <div class="page-loader" id="mainPageLoader">
    <div class="loader">
      <div class="house-loader-icon">
        <i class="fas fa-home"></i>
        <div class="loader-pulse-ring"></div>
      </div>
      <p><?php echo $isAdmin ? 'Loading Management Portal...' : 'Loading BoardingHub...'; ?></p>
    </div>
  </div>
  <script>
    (function() {
      setTimeout(function() {
        var el = document.getElementById('mainPageLoader') || document.querySelector('.page-loader');
        if (el) {
          el.style.opacity = '0';
          el.style.pointerEvents = 'none';
          setTimeout(function() { el.style.display = 'none'; }, 200);
        }
      }, 300);
    })();
  </script>

  <?php if ($isAdmin): ?>
    <!-- ADMIN SIDEBAR LAYOUT WRAPPER -->
    <div class="admin-layout-wrapper">
      <!-- SIDEBAR -->
      <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
          <div class="logo">
            <h1 style="display:flex; align-items:center; gap:8px;"><i class="fas fa-building" style="color:#3b82f6; font-size:20px;"></i> Boarding<span>Hub</span></h1>
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
          <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
          </div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="user-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <ul>
            <li>
              <a href="dashboard.php" class="active">
                <i class="fas fa-users-cog"></i> <span>Accounts & Properties</span>
              </a>
            </li>
            <li>
              <a href="admin_requests.php">
                <i class="fas fa-clipboard-list"></i> <span>Requests Queue</span>
                <?php if ($pendingRequestsTotal > 0): ?>
                  <span class="nav-badge"><?php echo $pendingRequestsTotal; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="privileges.php">
                <i class="fas fa-user-shield"></i> <span>Privileges</span>
              </a>
            </li>
            <?php if ($isSuperAdmin): ?>
              <li>
                <a href="create_account.php">
                  <i class="fas fa-user-plus"></i> <span>Create Account</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (Auth::isSuperAdmin() || Auth::hasPrivilege('can_manage_classes')): ?>
            <li><a href="add_boarding_house.php"><i class="fas fa-house-medical"></i> <span>Post Boarding House</span></a></li>
            <?php endif; ?>
            <li>
              <a href="logs.php">
                <i class="fas fa-history"></i> <span>System Logs</span>
              </a>
            </li>
            <li class="nav-divider"></li>
            <li>
              <a href="change-password.php">
                <i class="fas fa-key"></i> <span>Change Password</span>
              </a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- MAIN CONTENT WRAPPER -->
      <div class="admin-main-wrapper">
        <header class="admin-topbar">
          <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar Navigation">
            <i class="fas fa-bars"></i>
          </button>
          <div class="topbar-title">
            <h3><i class="fas fa-building"></i> Boarding House & Accounts Management</h3>
          </div>
          <div class="topbar-right">
            <div class="topbar-user-chip">
              <i class="fas fa-user-shield"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <a href="logout.php" class="btn-topbar-logout" title="Logout">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a>
          </div>
        </header>

        <div class="admin-page-content">
          <!-- Header Banner -->
          <div class="admin-header">
            <div class="admin-header-title">
              <h2><i class="fas fa-shield-alt"></i> <?php echo $isSuperAdmin ? 'Super Administrator Console' : 'Property Administrator Control Panel'; ?></h2>
              <p>Oversee boarding house listings, approve tenant accounts, review room reservations, and manage property tickets.</p>
            </div>

            <div class="admin-stat-pills">
              <div class="stat-pill pill-users">
                <i class="fas fa-users"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo count($allUsers); ?></div>
                  <div class="lbl">Registered Accounts</div>
                </div>
              </div>

              <div class="stat-pill pill-pending">
                <i class="fas fa-user-clock"></i>
                <div class="stat-pill-info">
                  <div class="num"><?php echo $stats['pending_count']; ?></div>
                  <div class="lbl">Pending Users</div>
                </div>
              </div>

              <div class="stat-pill" style="border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.1);">
                <i class="fas fa-bookmark" style="color: #60a5fa;"></i>
                <div class="stat-pill-info">
                  <div class="num" style="color: #60a5fa;"><?php echo $pendingReservationsCount; ?></div>
                  <div class="lbl">Pending Reservations</div>
                </div>
              </div>

              <div class="stat-pill" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.1);">
                <i class="fas fa-tools" style="color: #f59e0b;"></i>
                <div class="stat-pill-info">
                  <div class="num" style="color: #f59e0b;"><?php echo $openMaintenanceCount; ?></div>
                  <div class="lbl">Open Maintenance</div>
                </div>
              </div>
            </div>
          </div>

          <!-- PENDING ROOM RESERVATIONS QUEUE (If Any) -->
          <?php if (!empty($allReservations)): ?>
            <div class="admin-table-card" style="margin-bottom: 25px;">
              <div class="admin-table-card-header">
                <h3><i class="fas fa-bookmark" style="color: #3b82f6;"></i> Recent Boarder Room Reservations</h3>
                <span style="font-size: 13px; color: #94a3b8;"><?php echo count($allReservations); ?> total submissions</span>
              </div>
              <div class="table-responsive">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Resident</th>
                      <th>Boarding House</th>
                      <th>Target Move-In</th>
                      <th>Duration</th>
                      <th>Contact</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($allReservations, 0, 10) as $resv): ?>
                      <tr id="resv-row-<?php echo $resv['id']; ?>">
                        <td>
                          <strong><?php echo htmlspecialchars(($resv['first_name'] ?? '') . ' ' . ($resv['last_name'] ?? '')); ?></strong>
                          <div style="font-size: 11px; color: #94a3b8;">@<?php echo htmlspecialchars($resv['username'] ?? $resv['user_id']); ?></div>
                        </td>
                        <td>
                          <strong><?php echo htmlspecialchars($resv['bh_name'] ?? 'Boarding House'); ?></strong>
                          <div style="font-size: 11px; color: #60a5fa;"><?php echo htmlspecialchars($resv['price_range'] ?? ''); ?></div>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($resv['target_move_in_date'])); ?></td>
                        <td><?php echo (int)$resv['stay_duration_months']; ?> months</td>
                        <td><?php echo htmlspecialchars($resv['contact_phone'] ?? 'N/A'); ?></td>
                        <td>
                          <?php if ($resv['status'] === 'approved' || $resv['status'] === 'active'): ?>
                            <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>
                          <?php elseif ($resv['status'] === 'pending'): ?>
                            <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending Review</span>
                          <?php else: ?>
                            <span class="status-badge status-blocked"><?php echo ucfirst($resv['status']); ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($resv['status'] === 'pending'): ?>
                            <button class="action-btn btn-approve" onclick="updateReservationStatus(<?php echo $resv['id']; ?>, 'approved')" title="Approve Reservation">
                              <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="action-btn btn-block" onclick="updateReservationStatus(<?php echo $resv['id']; ?>, 'rejected')" title="Reject">
                              <i class="fas fa-times"></i>
                            </button>
                          <?php else: ?>
                            <span style="font-size: 12px; color: #94a3b8;"><i class="fas fa-check-double"></i> Processed</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

          <!-- BOARDING HOUSES QUICK DIRECTORY -->
          <div class="admin-table-card" id="properties" style="margin-bottom: 25px;">
            <div class="admin-table-card-header">
              <h3><i class="fas fa-building" style="color: #10b981;"></i> Verified Boarding Houses on Record</h3>
              <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <a href="index.php#browse-section" target="_blank" style="color: #60a5fa; font-size: 13px; text-decoration: none; font-weight: 600;">
                  <i class="fas fa-external-link-alt"></i> View Public Catalog
                </a>
                <?php if ($isSuperAdmin || Auth::hasPrivilege('can_manage_classes')): ?>
                  <a href="add_boarding_house.php" class="btn-primary-action" style="text-decoration: none;">
                    <i class="fas fa-plus"></i> Post Boarding House
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Property Name</th>
                    <th>Address</th>
                    <th>Room Types</th>
                    <th>Price Range</th>
                    <th>Available Slots</th>
                    <th>Rating</th>
                    <th>Landlord</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($boardingHouses as $bh): ?>
                    <tr>
                      <td>
                        <strong style="color: #fff;"><?php echo htmlspecialchars($bh['name']); ?></strong>
                        <div style="font-size: 11px; color: #93c5fd;"><i class="fas fa-walking"></i> <?php echo htmlspecialchars($bh['proximity']); ?></div>
                      </td>
                      <td><?php echo htmlspecialchars($bh['address']); ?>, <?php echo htmlspecialchars($bh['city']); ?></td>
                      <td><span style="font-size: 12px; color: #cbd5e1;"><?php echo htmlspecialchars($bh['room_types']); ?></span></td>
                      <td><strong style="color: #60a5fa;"><?php echo htmlspecialchars($bh['price_range']); ?></strong></td>
                      <td><span style="color: #34d399; font-weight: 700;"><?php echo (int)$bh['available_rooms']; ?> / <?php echo (int)$bh['total_rooms']; ?></span> rooms</td>
                      <td>
                        <?php if ((int)$bh['reviews_count'] === 0): ?>
                          <span class="badge-new"><i class="fas fa-certificate"></i> New</span>
                        <?php else: ?>
                          <span style="color: #fbbf24;"><i class="fas fa-star"></i> <?php echo $bh['rating']; ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($bh['landlord_name']); ?>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($bh['landlord_phone']); ?></div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- USER ACCOUNTS MANAGEMENT TABLE -->
          <div class="admin-table-card">
            <div class="admin-table-card-header">
              <h3><i class="fas fa-users-cog"></i> Resident & Staff Accounts Directory</h3>
              <div class="table-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="adminUserSearch" placeholder="Filter by Employee ID, name, username, or email..." oninput="filterUsersTable()">
              </div>
            </div>
            <div class="table-responsive">
              <table class="admin-table" id="usersAdminTable">
                <thead>
                  <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Contact</th>
                    <th>Registered</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allUsers as $u): ?>
                    <tr id="user-row-<?php echo htmlspecialchars($u['id_number']); ?>">
                      <td><code><?php echo htmlspecialchars($u['id_number']); ?></code></td>
                      <td><strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong></td>
                      <td>@<?php echo htmlspecialchars($u['username']); ?></td>
                      <td>
                        <?php if ($u['role'] === 'superadmin'): ?>
                          <span class="role-badge role-superadmin">SUPER ADMIN</span>
                        <?php elseif ($u['role'] === 'admin'): ?>
                          <span class="role-badge role-admin">ADMIN</span>
                        <?php else: ?>
                          <span class="role-badge role-user">RESIDENT</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($u['status'] === 'approved'): ?>
                          <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Active</span>
                        <?php elseif ($u['status'] === 'pending'): ?>
                          <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                        <?php else: ?>
                          <span class="status-badge status-blocked"><i class="fas fa-ban"></i> Blocked</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="font-size: 12px;"><?php echo htmlspecialchars($u['email']); ?></div>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($u['phone_number'] ?? 'No phone'); ?></div>
                      </td>
                      <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                      <td>
                        <div class="table-actions-cell">
                          <?php if ($u['status'] === 'pending'): ?>
                            <button class="action-btn btn-approve" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'approved')" title="Approve Account">
                              <i class="fas fa-check"></i>
                            </button>
                          <?php endif; ?>

                          <?php if ($u['status'] === 'blocked'): ?>
                            <button class="action-btn btn-approve" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'approved')" title="Unblock Account">
                              <i class="fas fa-unlock"></i>
                            </button>
                          <?php elseif ($u['status'] === 'approved' && $u['role'] !== 'superadmin'): ?>
                            <button class="action-btn btn-block" onclick="updateUserStatus('<?php echo $u['id_number']; ?>', 'blocked')" title="Block Account">
                              <i class="fas fa-ban"></i>
                            </button>
                          <?php endif; ?>

                          <button class="action-btn btn-edit" onclick="openEditUserModal('<?php echo htmlspecialchars($u['id_number']); ?>')" title="Edit User">
                            <i class="fas fa-edit"></i>
                          </button>

                          <?php if ($isSuperAdmin && $u['id_number'] !== $user['id_number']): ?>
                            <button class="action-btn btn-delete" onclick="openDirectDeleteModal('<?php echo $u['id_number']; ?>', '<?php echo htmlspecialchars(addslashes($u['username'])); ?>')" title="Delete Account">
                              <i class="fas fa-trash"></i>
                            </button>
                          <?php elseif (!$isSuperAdmin && $u['role'] === 'user'): ?>
                            <button class="action-btn btn-delete" onclick="openDeleteRequestModal('<?php echo $u['id_number']; ?>', '<?php echo htmlspecialchars(addslashes($u['username'])); ?>')" title="Request Deletion">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <footer class="admin-footer">
          <p class="admin-footer-copyright">&copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved.</p>
        </footer>
      </div>
    </div>

    <!-- MODAL 1: EDIT USER INFO MODAL -->
    <div class="modal-overlay" id="modal-edit-user">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-user-edit"></i> Update Account Details</h3>
          <button class="modal-close" onclick="closeAdminModal('modal-edit-user')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitEditUserForm(event)">
          <div class="modal-body">
            <input type="hidden" id="edit-id-number">
            <div class="form-grid">
              <div class="form-field"><label>Username</label><input type="text" id="edit-username" required></div>
              <div class="form-field"><label>First Name</label><input type="text" id="edit-firstname" required></div>
              <div class="form-field"><label>Middle Name</label><input type="text" id="edit-middlename"></div>
              <div class="form-field"><label>Last Name</label><input type="text" id="edit-lastname" required></div>
              <div class="form-field"><label>Email</label><input type="email" id="edit-email" required></div>
              <div class="form-field"><label>Birthdate</label><input type="date" id="edit-birthdate"></div>
              <div class="form-field">
                <label>Sex</label>
                <select id="edit-sex">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <?php if ($isSuperAdmin): ?>
                <div class="form-field">
                  <label>Role</label>
                  <select id="edit-role">
                    <option value="user">Resident</option>
                    <option value="admin">Administrator</option>
                    <option value="superadmin">Super Administrator</option>
                  </select>
                </div>
              <?php endif; ?>
              <div class="form-field">
                <label>Status</label>
                <select id="edit-status">
                  <option value="approved">Approved</option>
                  <option value="pending">Pending</option>
                  <option value="blocked">Blocked</option>
                </select>
              </div>
              <div class="form-field"><label>Purok / Street</label><input type="text" id="edit-purok"></div>
              <div class="form-field"><label>Barangay</label><input type="text" id="edit-barangay"></div>
              <div class="form-field"><label>City / Municipality</label><input type="text" id="edit-city"></div>
              <div class="form-field"><label>Province</label><input type="text" id="edit-province"></div>
              <div class="form-field"><label>Zip Code</label><input type="text" id="edit-zip"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary-action" onclick="closeAdminModal('modal-edit-user')">Cancel</button>
            <button type="submit" class="btn-primary-action"><i class="fas fa-save"></i> Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: DIRECT DELETE (Super Admin) -->
    <?php if ($isSuperAdmin): ?>
      <div class="modal-overlay" id="modal-direct-delete">
        <div class="modal-card" style="max-width: 480px;">
          <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Permanent Deletion</h3>
            <button class="modal-close" onclick="closeAdminModal('modal-direct-delete')"><i class="fas fa-times"></i></button>
          </div>
          <form onsubmit="submitDirectDeleteForm(event)">
            <div class="modal-body">
              <input type="hidden" id="direct-del-user-id">
              <p style="color: #cbd5e1; margin-bottom: 12px;">Are you sure you want to permanently delete user <strong id="direct-del-username" style="color: #ef4444;"></strong>?</p>
              <p style="font-size: 13px; color: #94a3b8;">This action cannot be undone and deletes all linked boarding records.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary-action" onclick="closeAdminModal('modal-direct-delete')">Cancel</button>
              <button type="submit" class="btn-primary-action" style="background: #dc2626;"><i class="fas fa-trash"></i> Permanently Delete</button>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <!-- MODAL 2B: DELETE REQUEST (Admin -> forwarded to Super Admin) -->
      <div class="modal-overlay" id="modal-delete-request">
        <div class="modal-card" style="max-width: 520px;">
          <div class="modal-header">
            <h3><i class="fas fa-user-slash" style="color: #f87171;"></i> Request Account Deletion</h3>
            <button type="button" class="modal-close" onclick="closeAdminModal('modal-delete-request')"><i class="fas fa-times"></i></button>
          </div>
          <form onsubmit="submitDeleteRequestForm(event)">
            <div class="modal-body">
              <input type="hidden" id="delreq-user-id">

              <div class="superadmin-only-notice" role="alert">
                <i class="fas fa-crown"></i>
                <div>
                  <strong>Only the Super Administrator can delete accounts.</strong>
                  <p>As an Administrator you can't delete this account directly. Your request and reason will be sent to the Super Admin, who will review the user's complete information before approving or rejecting it.</p>
                </div>
              </div>

              <p style="color: #cbd5e1; margin: 16px 0 14px;">Account to delete: <strong id="delreq-username" style="color: #f87171;"></strong></p>

              <div class="form-field">
                <label for="delreq-reason">Reason for deletion *</label>
                <textarea id="delreq-reason" rows="4" maxlength="500" required placeholder="Explain why this account should be deleted (e.g. duplicate account, moved out, violated house rules)..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary-action" onclick="closeAdminModal('modal-delete-request')">Cancel</button>
              <button type="submit" class="btn-primary-action" style="background: #dc2626;"><i class="fas fa-paper-plane"></i> Send Request to Super Admin</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- ========================================================
         TENANT / BOARDER DASHBOARD (Member Experience)
         ======================================================== -->
    <header>
      <div class="logo">
        <h1 style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-building" style="color: #3b82f6; font-size: 22px;"></i> Boarding<span>Hub</span></h1>
      </div>
      <div class="navBar">
        <ul>
          <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
          <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="logs.php"><i class="fas fa-history"></i> Logs</a></li>
          <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </header>

    <main>
      <div class="user-dashboard-wrapper">
        <!-- Hero Welcome Banner -->
        <div class="member-hero-banner">
          <div class="member-hero-left">
            <div class="member-avatar-lg">
              <i class="fas fa-user-graduate"></i>
            </div>
            <div class="member-greeting">
              <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
              <p>
                <span><i class="fas fa-id-badge" style="color: #60a5fa;"></i> ID: <?php echo htmlspecialchars($user['id_number']); ?></span>
                <span>•</span>
                <span><i class="fas fa-home" style="color: #34d399;"></i> <?php echo !empty($activeAccommodation) ? htmlspecialchars($activeAccommodation['bh_name']) : 'Looking for accommodation'; ?></span>
                <span class="member-tier-badge"><i class="fas fa-check-circle"></i> Verified Resident</span>
              </p>
            </div>
          </div>
          <div class="member-hero-actions">
            <button class="btn-hero-primary" onclick="switchDashboardTab('tab-explore')">
              <i class="fas fa-search"></i> Browse Houses
            </button>
            <button class="btn-hero-secondary" onclick="switchDashboardTab('tab-pass')">
              <i class="fas fa-id-card"></i> Digital Boarder ID
            </button>
            <button class="btn-hero-secondary" onclick="openModal('modal-submit-maintenance')">
              <i class="fas fa-tools"></i> Report Issue
            </button>
          </div>
        </div>

        <!-- Live Resident Stat Cards -->
        <div class="user-stat-grid">
          <div class="user-stat-card">
            <div class="user-stat-icon icon-workouts" style="background: rgba(37, 99, 235, 0.15); color: #60a5fa;">
              <i class="fas fa-home"></i>
            </div>
            <div class="user-stat-info">
              <h3><?php echo !empty($activeAccommodation) ? 'Room Reserved' : 'None Active'; ?></h3>
              <p><?php echo !empty($activeAccommodation) ? htmlspecialchars($activeAccommodation['bh_name']) : 'Find a room below'; ?></p>
            </div>
          </div>

          <div class="user-stat-card">
            <div class="user-stat-icon icon-calories" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
              <i class="fas fa-receipt"></i>
            </div>
            <div class="user-stat-info">
              <h3><?php echo !empty($activeAccommodation) ? htmlspecialchars($activeAccommodation['price_range']) : 'Flexible'; ?></h3>
              <p>Monthly Rent Estimate</p>
            </div>
          </div>

          <div class="user-stat-card" style="cursor: pointer;" onclick="switchDashboardTab('tab-bookings')">
            <div class="user-stat-icon icon-streak" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
              <i class="fas fa-calendar-check"></i>
            </div>
            <div class="user-stat-info">
              <h3><?php echo count($myReservations); ?> Requests</h3>
              <p>Room Bookings On File</p>
            </div>
          </div>

          <div class="user-stat-card" style="cursor: pointer;" onclick="switchDashboardTab('tab-maintenance')">
            <div class="user-stat-icon icon-bmi" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
              <i class="fas fa-wrench"></i>
            </div>
            <div class="user-stat-info">
              <h3><?php echo count($myMaintenance); ?> Tickets</h3>
              <p>Maintenance Reports</p>
            </div>
          </div>
        </div>

        <!-- Dashboard Feature Navigation Tabs -->
        <div class="dashboard-nav-tabs">
          <button class="dashboard-tab-btn active" data-tab="tab-explore">
            <i class="fas fa-compass"></i> <span>Explore Boarding Houses</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-bookings">
            <i class="fas fa-calendar-check"></i> <span>My Reservations</span>
            <?php if (count($myReservations) > 0): ?>
              <span style="background: #2563eb; color:#fff; font-size:11px; padding:2px 7px; border-radius:10px;"><?php echo count($myReservations); ?></span>
            <?php endif; ?>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-pass">
            <i class="fas fa-id-card"></i> <span>Digital Boarder ID</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-maintenance">
            <i class="fas fa-tools"></i> <span>Maintenance & Repairs</span>
          </button>
          <button class="dashboard-tab-btn" data-tab="tab-profile">
            <i class="fas fa-user-circle"></i> <span>Resident Profile</span>
          </button>
        </div>

        <!-- TAB 1: EXPLORE BOARDING HOUSES -->
        <div class="tab-pane active" id="tab-explore">
          <div class="dash-card">
            <div class="dash-card-header">
              <div>
                <h3><i class="fas fa-building" style="color: #60a5fa;"></i> Verified Boarding Houses & Dorms</h3>
                <p style="color: var(--text-muted); font-size: 13px;">Browse rooms, compare amenities, and reserve your space directly with landlords.</p>
              </div>
              <div class="table-search-box" style="margin: 0;">
                <i class="fas fa-search"></i>
                <input type="text" id="dash-bh-search" placeholder="Filter by house name or address..." onkeyup="filterDashBoardingHouses()">
              </div>
            </div>

            <!-- Boarding House Cards Grid -->
            <div class="houses-grid" style="margin-top: 20px;">
              <?php foreach ($boardingHouses as $house): 
                $amenitiesList = is_string($house['amenities']) ? json_decode($house['amenities'], true) : (array)$house['amenities'];
                if (!is_array($amenitiesList)) $amenitiesList = ["Free WiFi", "CCTV Security", "Study Area"];
              ?>
                <div class="house-card dash-house-card" data-name="<?php echo htmlspecialchars(strtolower($house['name'] . ' ' . $house['address'])); ?>">
                  <div class="house-image-wrapper">
                    <img src="<?php echo htmlspecialchars($house['image_path']); ?>" alt="<?php echo htmlspecialchars($house['name']); ?>" loading="lazy">
                    <div class="house-badges">
                      <span class="badge-status"><i class="fas fa-check-circle"></i> Verified</span>
                      <span class="badge-gender coed"><?php echo htmlspecialchars($house['gender_policy']); ?></span>
                    </div>
                    <div class="house-price-tag">
                      <span class="price-amount"><?php echo htmlspecialchars($house['price_range']); ?></span>
                    </div>
                  </div>

                  <div class="house-details">
                    <div class="house-rating-row">
                      <div class="stars">
                        <?php if ((int)$house['reviews_count'] === 0): ?>
                          <span class="badge-new"><i class="fas fa-certificate"></i> New listing</span>
                        <?php else: ?>
                          <i class="fas fa-star"></i>
                          <span><?php echo number_format((float)$house['rating'], 1); ?></span>
                          <small>(<?php echo (int)$house['reviews_count']; ?> reviews)</small>
                        <?php endif; ?>
                      </div>
                      <div class="slots-available">
                        <i class="fas fa-door-open"></i> <?php echo (int)$house['available_rooms']; ?> slots left
                      </div>
                    </div>

                    <h3 class="house-title"><?php echo htmlspecialchars($house['name']); ?></h3>
                    <p class="house-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($house['address']); ?></p>
                    <div class="house-proximity"><i class="fas fa-walking"></i> <?php echo htmlspecialchars($house['proximity']); ?></div>

                    <div class="house-amenities-pills">
                      <?php foreach (array_slice($amenitiesList, 0, 3) as $am): ?>
                        <span class="amenity-pill"><i class="fas fa-check"></i> <?php echo htmlspecialchars($am); ?></span>
                      <?php endforeach; ?>
                    </div>

                    <div class="house-card-footer">
                      <button type="button" class="btn-card-details" onclick="openReserveModalFromCard(<?php echo $house['id']; ?>, '<?php echo htmlspecialchars(addslashes($house['name'])); ?>')">
                        <i class="fas fa-bookmark"></i> Quick Reserve
                      </button>
                      <a href="tel:<?php echo htmlspecialchars($house['landlord_phone']); ?>" class="btn-card-reserve" style="text-decoration:none;">
                        <i class="fas fa-phone-alt"></i> Call Landlord
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- TAB 2: MY ROOM RESERVATIONS -->
        <div class="tab-pane" id="tab-bookings">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-bookmark" style="color: #60a5fa;"></i> My Room Reservations & Applications</h3>
              <button class="btn-hero-primary" style="padding: 8px 16px; font-size: 13px;" onclick="switchDashboardTab('tab-explore')">
                <i class="fas fa-plus"></i> New Reservation
              </button>
            </div>

            <?php if (empty($myReservations)): ?>
              <div style="text-align: center; color: #94a3b8; padding: 45px 20px;">
                <i class="fas fa-calendar-times" style="font-size: 38px; color: #475569; margin-bottom: 14px; display: block;"></i>
                <h4 style="color: #fff; margin-bottom: 6px;">No Active Reservations</h4>
                <p style="font-size: 14px; margin-bottom: 20px;">You haven't submitted any boarding house reservations yet.</p>
                <button class="btn-hero-primary" onclick="switchDashboardTab('tab-explore')">Explore Available Houses</button>
              </div>
            <?php else: ?>
              <div class="table-responsive" style="margin-top: 15px;">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Property Name</th>
                      <th>Target Move-In</th>
                      <th>Duration</th>
                      <th>Landlord Contact</th>
                      <th>Status</th>
                      <th>Submitted</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($myReservations as $r): ?>
                      <tr id="my-resv-row-<?php echo $r['id']; ?>">
                        <td>
                          <strong style="color: #fff;"><?php echo htmlspecialchars($r['bh_name']); ?></strong>
                          <div style="font-size: 11px; color: #60a5fa;"><?php echo htmlspecialchars($r['price_range']); ?></div>
                        </td>
                        <td><strong><?php echo date('M d, Y', strtotime($r['target_move_in_date'])); ?></strong></td>
                        <td><?php echo (int)$r['stay_duration_months']; ?> Months</td>
                        <td>
                          <?php echo htmlspecialchars($r['landlord_name']); ?>
                          <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($r['landlord_phone']); ?></div>
                        </td>
                        <td>
                          <?php if ($r['status'] === 'approved' || $r['status'] === 'active'): ?>
                            <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Confirmed</span>
                          <?php elseif ($r['status'] === 'pending'): ?>
                            <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending Review</span>
                          <?php else: ?>
                            <span class="status-badge status-blocked"><?php echo ucfirst($r['status']); ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                        <td>
                          <?php if ($r['status'] === 'pending'): ?>
                            <button class="action-btn btn-block" onclick="cancelReservation(<?php echo $r['id']; ?>)" title="Cancel Reservation">
                              <i class="fas fa-times"></i> Cancel
                            </button>
                          <?php else: ?>
                            <span style="font-size: 12px; color: #34d399;"><i class="fas fa-check"></i> Active</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TAB 3: DIGITAL BOARDER ID PASS -->
        <div class="tab-pane" id="tab-pass">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-id-card"></i> Official Digital Resident ID Pass</h3>
              <p style="color: var(--text-muted); font-size: 13px;">Present this digital badge or QR code for dorm gate entry and curfew logging.</p>
            </div>

            <div class="pass-container" style="display: flex; justify-content: center; padding: 20px 0;">
              <div class="gym-pass-card" style="background: linear-gradient(135deg, #111c38 0%, #070b18 100%); border: 1px solid rgba(37, 99, 235, 0.4); max-width: 480px; width: 100%; border-radius: 20px; padding: 26px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
                <div class="pass-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; margin-bottom: 18px;">
                  <div class="pass-logo">
                    <h2 style="font-family:'Outfit',sans-serif; font-size: 22px; color:#fff;"><i class="fas fa-building" style="color:#3b82f6;"></i> Boarding<span style="color:#60a5fa;">Hub</span></h2>
                  </div>
                  <span style="background: rgba(16, 185, 129, 0.2); color:#34d399; font-size: 11px; font-weight:700; padding: 4px 10px; border-radius: 9999px;">
                    <i class="fas fa-check-circle"></i> VERIFIED RESIDENT
                  </span>
                </div>

                <div class="pass-body" style="display: flex; justify-content: space-between; gap: 20px;">
                  <div>
                    <div style="font-family:'Outfit',sans-serif; font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 4px;">
                      <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                    <div style="font-size: 12px; color: #60a5fa; margin-bottom: 14px; font-weight:600;">
                      Resident ID: <?php echo htmlspecialchars($user['id_number']); ?>
                    </div>
                    <div style="font-size: 12px; color: #cbd5e1; margin-bottom: 6px;">
                      <strong>Residence:</strong> <?php echo !empty($activeAccommodation) ? htmlspecialchars($activeAccommodation['bh_name']) : 'Oakwood Residences'; ?>
                    </div>
                    <div style="font-size: 12px; color: #cbd5e1; margin-bottom: 6px;">
                      <strong>Emergency Contact:</strong> <?php echo htmlspecialchars($user['phone_number'] ?? 'Not specified'); ?>
                    </div>
                    <div style="font-size: 12px; color: #cbd5e1;">
                      <strong>Registered:</strong> <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </div>
                  </div>
                  <div class="pass-qr-box" style="background:#fff; padding: 8px; border-radius: 12px; align-self: center;" title="Resident QR Verification">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=BOARDINGHUB-TENANT-<?php echo urlencode($user['id_number']); ?>-<?php echo urlencode($user['username']); ?>" alt="Resident QR Code" style="display:block; width: 110px; height: 110px;">
                  </div>
                </div>

                <div class="pass-footer" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top: 18px; padding-top: 12px; display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8;">
                  <span><i class="fas fa-shield-alt" style="color: #3b82f6;"></i> Official Tenant Pass</span>
                  <span>Curfew & Gate Access Active</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: MAINTENANCE & REPAIR TICKETS -->
        <div class="tab-pane" id="tab-maintenance">
          <div class="dash-card">
            <div class="dash-card-header">
              <div>
                <h3><i class="fas fa-tools" style="color: #f59e0b;"></i> Maintenance & Property Repair Tickets</h3>
                <p style="color: var(--text-muted); font-size: 13px;">Report water leaks, electrical, air conditioning, or WiFi issues to landlord staff.</p>
              </div>
              <button class="btn-hero-primary" style="padding: 9px 18px; font-size: 13px;" onclick="openModal('modal-submit-maintenance')">
                <i class="fas fa-plus"></i> Submit Repair Ticket
              </button>
            </div>

            <?php if (empty($myMaintenance)): ?>
              <div style="text-align: center; color: #94a3b8; padding: 40px 20px;">
                <i class="fas fa-check-circle" style="font-size: 36px; color: #10b981; margin-bottom: 12px; display: block;"></i>
                <h4 style="color: #fff; margin-bottom: 4px;">No Maintenance Issues Reported</h4>
                <p style="font-size: 13.5px;">All property amenities and appliances are in working order.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive" style="margin-top: 15px;">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Ticket #</th>
                      <th>Boarding House / Room</th>
                      <th>Category</th>
                      <th>Urgency</th>
                      <th>Description</th>
                      <th>Status</th>
                      <th>Reported</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($myMaintenance as $ticket): ?>
                      <tr>
                        <td><code>#<?php echo $ticket['id']; ?></code></td>
                        <td>
                          <strong><?php echo htmlspecialchars($ticket['boarding_house_name']); ?></strong>
                          <div style="font-size: 11px; color: #94a3b8;">Room: <?php echo htmlspecialchars($ticket['room_number'] ?: 'Unit'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                        <td>
                          <?php if ($ticket['urgency'] === 'Emergency'): ?>
                            <span class="status-badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444;">Emergency</span>
                          <?php elseif ($ticket['urgency'] === 'High'): ?>
                            <span class="status-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">High</span>
                          <?php else: ?>
                            <span class="status-badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;"><?php echo htmlspecialchars($ticket['urgency']); ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($ticket['description']); ?></td>
                        <td>
                          <?php if ($ticket['status'] === 'resolved'): ?>
                            <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Resolved</span>
                          <?php elseif ($ticket['status'] === 'in_progress'): ?>
                            <span class="status-badge" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa;"><i class="fas fa-spinner fa-spin"></i> In Progress</span>
                          <?php else: ?>
                            <span class="status-badge status-pending"><i class="fas fa-clock"></i> Queued</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TAB 5: BOARDER PROFILE & PREFERENCES -->
        <div class="tab-pane" id="tab-profile">
          <div class="dash-card">
            <div class="dash-card-header">
              <h3><i class="fas fa-user-circle"></i> Personal Boarder Profile & Contact</h3>
              <button class="btn-hero-primary" style="padding: 9px 18px; font-size: 13px;" onclick="openModal('modal-edit-profile')">
                <i class="fas fa-user-edit"></i> Edit Profile
              </button>
            </div>

            <div class="profile-details-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 15px;">
              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Full Legal Name</label>
                <div style="font-size: 16px; font-weight: 700; color: #fff; margin-top: 4px;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'] . ' ' . $user['extension_name']); ?></div>
              </div>

              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Student / National ID</label>
                <div style="font-size: 16px; font-weight: 700; color: #60a5fa; margin-top: 4px;"><?php echo htmlspecialchars($user['id_number']); ?></div>
              </div>

              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Email Address</label>
                <div style="font-size: 15px; color: #fff; margin-top: 4px;"><?php echo htmlspecialchars($user['email']); ?></div>
              </div>

              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Contact Phone</label>
                <div style="font-size: 15px; color: #fff; margin-top: 4px;"><?php echo htmlspecialchars($user['phone_number'] ?: 'Not provided'); ?></div>
              </div>

              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Permanent Hometown Address</label>
                <div style="font-size: 14px; color: #fff; margin-top: 4px;">
                  <?php echo htmlspecialchars($user['purok_street'] . ', ' . $user['barangay'] . ', ' . $user['city_municipality'] . ', ' . $user['province']); ?>
                </div>
              </div>

              <div class="profile-field-box" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
                <label style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Bio / Notes</label>
                <div style="font-size: 14px; color: #cbd5e1; margin-top: 4px;"><?php echo htmlspecialchars($user['bio'] ?: 'No bio entered yet.'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL 1: QUICK RESERVATION -->
    <div class="modal-overlay" id="modal-reserve-house">
      <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
          <h3><i class="fas fa-bookmark" style="color: #3b82f6;"></i> Reserve Boarding House Slot</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-reserve-house')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitDashReservationForm(event)">
          <div class="modal-body">
            <input type="hidden" id="dash-resv-house-id">
            <div style="background: rgba(37, 99, 235, 0.12); border: 1px solid rgba(37, 99, 235, 0.3); border-radius: 12px; padding: 14px; margin-bottom: 16px;">
              <h4 id="dash-resv-house-name" style="color: #fff; font-size: 16px; margin-bottom: 4px;"></h4>
              <p style="color: #93c5fd; font-size: 13px;"><i class="fas fa-info-circle"></i> Submission will notify landlord for approval.</p>
            </div>

            <div class="form-field-dash">
              <label>Target Move-in Date *</label>
              <input type="date" id="dash-resv-date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
            </div>

            <div class="form-field-dash">
              <label>Stay Duration</label>
              <select id="dash-resv-duration">
                <option value="1">1 Month (Trial / Transient)</option>
                <option value="5" selected>1 Semester (5 Months)</option>
                <option value="10">Academic Year (10 Months)</option>
                <option value="12">12 Months (Full Year)</option>
              </select>
            </div>

            <div class="form-field-dash">
              <label>Contact Phone Number *</label>
              <input type="text" id="dash-resv-phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="0917-000-0000" required>
            </div>

            <div class="form-field-dash">
              <label>Special Requests or Questions (optional)</label>
              <textarea id="dash-resv-notes" placeholder="e.g. Prefer lower bunk, bringing laptop, motorcycle parking..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-paper-plane"></i> Submit Reservation</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 2: SUBMIT MAINTENANCE TICKET -->
    <div class="modal-overlay" id="modal-submit-maintenance">
      <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
          <h3><i class="fas fa-tools" style="color: #f59e0b;"></i> Report Maintenance / Repair</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-submit-maintenance')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitDashMaintenanceForm(event)">
          <div class="modal-body">
            <div class="form-field-dash">
              <label>Boarding House *</label>
              <select id="maint-bh-name" required>
                <?php foreach ($boardingHouses as $bh): ?>
                  <option value="<?php echo htmlspecialchars($bh['name']); ?>"><?php echo htmlspecialchars($bh['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Room / Bed Number *</label>
                <input type="text" id="maint-room-num" placeholder="e.g. Room 204 or Bed A-1" required>
              </div>
              <div class="form-field-dash">
                <label>Issue Category</label>
                <select id="maint-category">
                  <option value="Plumbing/Water">Plumbing / Water Leak</option>
                  <option value="Electrical/Lighting">Electrical / Lighting</option>
                  <option value="Air Conditioning">Air Conditioning / Fan</option>
                  <option value="WiFi/Internet">WiFi / Internet Connection</option>
                  <option value="Door/Lock">Door / Window / Lock</option>
                  <option value="Cleanliness">Common Area Sanitation</option>
                  <option value="Other">Other Repairs</option>
                </select>
              </div>
            </div>

            <div class="form-field-dash">
              <label>Urgency Level</label>
              <select id="maint-urgency">
                <option value="Low">Low - Minor convenience</option>
                <option value="Medium" selected>Medium - Normal repair</option>
                <option value="High">High - Affecting study/sleep</option>
                <option value="Emergency">Emergency - Water leak or power outage</option>
              </select>
            </div>

            <div class="form-field-dash">
              <label>Describe the Problem *</label>
              <textarea id="maint-desc" rows="3" placeholder="Please provide specific details so repair staff can bring the right tools..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-wrench"></i> Submit Ticket</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL 3: EDIT PROFILE -->
    <div class="modal-overlay" id="modal-edit-profile">
      <div class="modal-card">
        <div class="modal-header">
          <h3><i class="fas fa-user-edit" style="color: #3b82f6;"></i> Update Resident Profile</h3>
          <button class="modal-close-btn" onclick="closeModal('modal-edit-profile')"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitProfileForm(event)">
          <div class="modal-body">
            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Contact Phone Number</label>
                <input type="text" id="prof-phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="0917-000-0000">
              </div>
              <div class="form-field-dash">
                <label>Course / Occupation / School</label>
                <input type="text" id="prof-fitness-goal" value="<?php echo htmlspecialchars($user['fitness_goal'] ?? ''); ?>" placeholder="e.g. BS Nursing, 3rd Year">
              </div>
            </div>

            <div class="form-field-dash">
              <label>Personal Bio / About Me</label>
              <textarea id="prof-bio" placeholder="Tell us a little about your studies or lifestyle..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>

            <h4 style="color: #fff; font-size: 14px; margin: 15px 0 10px;"><i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Hometown Address</h4>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>Purok / Street</label>
                <input type="text" id="prof-purok" value="<?php echo htmlspecialchars($user['purok_street'] ?? ''); ?>">
              </div>
              <div class="form-field-dash">
                <label>Barangay</label>
                <input type="text" id="prof-barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>">
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-field-dash">
                <label>City / Municipality</label>
                <input type="text" id="prof-city" value="<?php echo htmlspecialchars($user['city_municipality'] ?? ''); ?>">
              </div>
              <div class="form-field-dash">
                <label>Province</label>
                <input type="text" id="prof-province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
              </div>
            </div>

            <div class="form-field-dash">
              <label>Zip Code</label>
              <input type="text" id="prof-zip" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-hero-secondary btn-modal-cancel">Cancel</button>
            <button type="submit" class="btn-hero-primary"><i class="fas fa-save"></i> Save Profile</button>
          </div>
        </form>
      </div>
    </div>

    <footer>
      <div class="footer-content">
        <div class="footer-section">
          <div class="logo">
            <h1 style="display:flex; align-items:center; gap:8px;"><i class="fas fa-building" style="color:#3b82f6; font-size:20px;"></i> Boarding<span>Hub</span></h1>
          </div>
          <p>Connecting students and young professionals to verified, comfortable boarding homes.</p>
        </div>
        <div class="footer-section">
          <p class="copyright">&copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved.</p>
        </div>
      </div>
    </footer>
  <?php endif; ?>

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <?php if ($isAdmin): ?>
    <script src="../js/admin.js?v=<?php echo time(); ?>"></script>
    <script>
      // Accounts directory search: Employee ID, name, username, or email
      function filterUsersTable() {
        const input = document.getElementById('adminUserSearch');
        const table = document.getElementById('usersAdminTable');
        if (!input || !table) return;
        const q = input.value.trim().toLowerCase();
        const rows = table.querySelectorAll('tbody tr:not(.no-results-row)');
        let shown = 0;
        rows.forEach(row => {
          const match = !q || row.textContent.toLowerCase().includes(q);
          row.style.display = match ? '' : 'none';
          if (match) shown++;
        });
        let empty = table.querySelector('.no-results-row');
        if (!shown) {
          if (!empty) {
            empty = document.createElement('tr');
            empty.className = 'no-results-row';
            empty.innerHTML = '<td colspan="8" style="text-align:center; padding:28px; color:#94a3b8;"><i class="fas fa-search"></i> No account matches "<span></span>"</td>';
            table.querySelector('tbody').appendChild(empty);
          }
          empty.querySelector('span').textContent = input.value.trim();
          empty.style.display = '';
        } else if (empty) {
          empty.style.display = 'none';
        }
      }

      async function updateReservationStatus(resId, status) {
        if (!confirm(`Are you sure you want to change reservation #${resId} status to ${status}?`)) return;
        try {
          const res = await fetch('admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update_reservation_status',
              csrf_token: document.getElementById('csrf_token_val').value,
              reservation_id: resId,
              status: status
            })
          });
          const data = await res.json();
          alert(data.message);
          if (data.success) location.reload();
        } catch (e) {
          alert('Network error updating reservation');
        }
      }
    </script>
  <?php else: ?>
    <script src="../js/user_dashboard.js?v=<?php echo time(); ?>"></script>
  <?php endif; ?>
</body>

</html>