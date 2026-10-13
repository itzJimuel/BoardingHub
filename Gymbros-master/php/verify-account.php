<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/otp.php';

// Set by register.php (new account) or login.php (unverified account trying to sign in)
if (empty($_SESSION['verify_account'])) {
  header("Location: login.php");
  exit();
}

$pending = $_SESSION['verify_account'];
$errors = [];
$successMessage = '';
$db = new Database();
$conn = $db->getConnection();

function sendVerificationOtp($pending)
{
  $code = OtpService::generateOTP($pending['id_number'], $pending['email'], 'account_verification', 15);
  if (!$code) return false;
  OtpService::sendEmailOTP($pending['email'], $code, $pending['name'], 'account_verification');
  return true;
}

// First visit after registering / logging in: send the code once
if (!empty($_SESSION['verify_account_send'])) {
  unset($_SESSION['verify_account_send']);
  if (sendVerificationOtp($pending)) {
    $successMessage = "We sent a 6-digit One-Time PIN to your email. Enter it below to verify your account.";
  } else {
    $errors[] = "We couldn't send the verification code. Please tap Resend Code.";
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = "Invalid security token. Please try again.";
  } elseif (($_POST['action'] ?? '') === 'resend') {
    // Simple throttle: one resend every 60 seconds
    $last = $_SESSION['verify_last_sent'] ?? 0;
    if (time() - $last < 60) {
      $errors[] = "Please wait " . (60 - (time() - $last)) . " seconds before requesting another code.";
    } elseif (sendVerificationOtp($pending)) {
      $_SESSION['verify_last_sent'] = time();
      $successMessage = "A new One-Time PIN has been sent to your email.";
    } else {
      $errors[] = "Failed to send a new code. Please try again.";
    }
  } else {
    $code = trim($_POST['otp_code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) {
      $errors[] = "Please enter the 6-digit code from your email.";
    } else {
      $res = OtpService::verifyOTP($pending['email'], $code, 'account_verification');
      if ($res['success']) {
        $stmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id_number = ?");
        $stmt->bind_param("s", $pending['id_number']);
        $stmt->execute();
        $stmt->close();

        ActivityLogger::log('VERIFY_ACCOUNT', "User @{$pending['username']} verified their account email with OTP.", 'Account', [
          'id_number' => $pending['id_number'],
          'username' => $pending['username'],
          'full_name' => $pending['name'],
          'role' => 'user'
        ]);

        unset($_SESSION['verify_account'], $_SESSION['verify_last_sent']);
        $_SESSION['success_message'] = 'Email verified! Your account is now waiting for administrator approval before you can log in.';
        header("Location: login.php");
        exit();
      }
      $errors[] = $res['message'];
    }
  }
}
if (empty($_SESSION['verify_last_sent']) && $successMessage) {
  $_SESSION['verify_last_sent'] = time();
}

// Mask email, e.g. j***o@gmail.com
$maskedEmail = $pending['email'];
if (strpos($maskedEmail, '@') !== false) {
  [$local, $domain] = explode('@', $maskedEmail, 2);
  $maskedEmail = (strlen($local) <= 2 ? substr($local, 0, 1) . '*' : substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 2)) . substr($local, -1)) . '@' . $domain;
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Account | BoardingHub</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/forgot-password.css">
  <link rel="stylesheet" href="../css/theme.css?v=<?php echo filemtime(__DIR__ . '/../css/theme.css'); ?>">
</head>

<body class="bh-auth">
  <header>
    <div class="logo">
      <h1>Boarding<span>Hub</span></h1>
    </div>
    <div class="navBar">
      <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
      </ul>
    </div>
  </header>

  <div class="container">
    <div class="form-header">
      <div class="icon-circle"><i class="fas fa-envelope-open-text"></i></div>
      <h1>Verify Your <span>Account</span></h1>
      <p>Enter the 6-digit One-Time PIN we sent to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>. The code expires in 15 minutes.</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $e): ?>
          <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
      <div class="success"><p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></p></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      <input type="hidden" name="action" value="verify">
      <div class="form-group">
        <label class="form-label">One-Time PIN</label>
        <div class="otp-input-wrapper">
          <input type="text" name="otp_code" class="otp-box" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="------" autocomplete="one-time-code" required autofocus>
        </div>
      </div>
      <button type="submit" class="btn"><i class="fas fa-shield-alt"></i> Verify Account</button>
    </form>

    <form method="POST" action="" class="text-center" style="margin-top: 16px;">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="btn-secondary-link" style="background: none; border: none; cursor: pointer;">
        <i class="fas fa-redo"></i> Didn't get it? Resend Code
      </button>
    </form>
  </div>

  <footer style="margin-top:40px;padding:16px 0;text-align:center;">
    &copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved.
  </footer>
</body>

</html>
