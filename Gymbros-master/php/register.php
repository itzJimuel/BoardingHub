<?php

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/validation.php';
require_once '../includes/account_id.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token first
    if (!Security::verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Security token invalid. Please try again.";
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        // Collect and sanitize all form data with proper null checks
        $username = $db->sanitize($_POST['username'] ?? '');
        $first_name = $db->sanitize($_POST['first_name'] ?? '');
        $middle_name = $db->sanitize($_POST['middle_name'] ?? '');
        $last_name = $db->sanitize($_POST['last_name'] ?? '');
        $extension_name = $db->sanitize($_POST['extension_name'] ?? '');
        $extension_other = $db->sanitize($_POST['extension_other'] ?? '');
        $birthdate = $db->sanitize($_POST['birthdate'] ?? '');
        $email = $db->sanitize($_POST['email'] ?? '');
        $sex = $db->sanitize($_POST['sex'] ?? '');
        $purok_street = $db->sanitize($_POST['purok_street'] ?? '');
        $barangay = $db->sanitize($_POST['barangay'] ?? '');
        $city_municipality = $db->sanitize($_POST['city_municipality'] ?? '');
        $province = $db->sanitize($_POST['province'] ?? '');
        $country = $db->sanitize($_POST['country'] ?? '');
        $zip_code = $db->sanitize($_POST['zip_code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Security Questions
        // Answers are hashed, so they are kept raw (escaping would change the hash)
        $security_question1 = trim($_POST['security_question1'] ?? '');
        $security_answer1 = trim($_POST['security_answer1'] ?? '');
        $security_question2 = trim($_POST['security_question2'] ?? '');
        $security_answer2 = trim($_POST['security_answer2'] ?? '');
        $security_question3 = trim($_POST['security_question3'] ?? '');
        $security_answer3 = trim($_POST['security_answer3'] ?? '');
        $allowedQuestions = [
            'Who is your best friend in Elementary?',
            'What is the name of your favorite pet?',
            'Who is your favorite teacher in high school?'
        ];


        // Map 'Other' extension to the custom input value
        if (strtolower($extension_name) === 'other') {
            $extension_name = $extension_other;
        }

        // Basic validation
        if (empty($username))
            $errors[] = "Username is required";
        if (empty($first_name))
            $errors[] = "First Name is required";
        if (empty($last_name))
            $errors[] = "Last Name is required";
        if (empty($birthdate))
            $errors[] = "Birthdate is required";
        if (empty($email))
            $errors[] = "Email is required";
        if (empty($password))
            $errors[] = "Password is required";
        if (empty($purok_street))
            $errors[] = "Purok/Street is required";
        if (empty($barangay))
            $errors[] = "Barangay is required";
        if (empty($city_municipality))
            $errors[] = "City/Municipality is required";
        if (empty($province))
            $errors[] = "Province is required";
        if (empty($country))
            $errors[] = "Country is required";
        if (empty($zip_code))
            $errors[] = "Zip Code is required";

        // Three different secret questions, each answered
        $chosenQuestions = [$security_question1, $security_question2, $security_question3];
        if (in_array('', $chosenQuestions, true) || $security_answer1 === '' || $security_answer2 === '' || $security_answer3 === '') {
            $errors[] = "Please select and answer all 3 security questions";
        } elseif (array_diff($chosenQuestions, $allowedQuestions)) {
            $errors[] = "Invalid security question selected";
        } elseif (count(array_unique($chosenQuestions)) !== 3) {
            $errors[] = "Please select 3 different security questions";
        }

        if (empty($errors)) {
            // Check if username exists
            $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $errors[] = "Username '$username' already exists in the database";
                }
                $stmt->close();
            }

            // Validate username format
            $usernameErrors = Validation::validateUsername($username);
            $errors = array_merge($errors, $usernameErrors);
            // Disallow three consecutive identical letters in username
            if (preg_match('/([a-zA-Z])\1\1/', $username)) {
                $errors[] = "Username cannot contain three consecutive identical letters";
            }

            // Validate names (with strict cleaning)
            $first_name = preg_replace('/\s+/', ' ', trim($first_name));
            $last_name = preg_replace('/\s+/', ' ', trim($last_name));
            $middle_name = preg_replace('/\s+/', ' ', trim($middle_name));

            $nameErrors = array_merge(
                Validation::validateName($first_name, 'First Name'),
                Validation::validateName($last_name, 'Last Name')
            );
            $errors = array_merge($errors, $nameErrors);
            // Disallow three consecutive identical letters in first and last name
            if (preg_match('/([a-zA-Z])\1\1/', $first_name)) {
                $errors[] = "First Name cannot contain three consecutive identical letters";
            }
            if (preg_match('/([a-zA-Z])\1\1/', $last_name)) {
                $errors[] = "Last Name cannot contain three consecutive identical letters";
            }

            // Validate middle name if provided
            if (!empty($middle_name)) {
                $middleErrors = Validation::validateName($middle_name, 'Middle Name');
                $errors = array_merge($errors, $middleErrors);
                if (preg_match('/([a-zA-Z])\1\1/', $middle_name)) {
                    $errors[] = "Middle Name cannot contain three consecutive identical letters";
                }
            }

            // Validate extension name if provided
            if (!empty($extension_name)) {
                $extensionErrors = Validation::validateExtensionName($extension_name);
                $errors = array_merge($errors, $extensionErrors);
            }

            // Validate age
            $ageErrors = Validation::validateAge($birthdate);
            $errors = array_merge($errors, $ageErrors);


            // Validate email
            $emailErrors = Validation::validateEmail($email);
            $errors = array_merge($errors, $emailErrors);

            // Validate address fields
            $addressErrors = array_merge(
                Validation::validateAddressField($purok_street, 'Purok/Street'),
                Validation::validateAddressField($barangay, 'Barangay'),
                Validation::validateAddressField($city_municipality, 'City/Municipality'),
                Validation::validateAddressField($province, 'Province'),
                Validation::validateAddressField($country, 'Country')
            );
            $errors = array_merge($errors, $addressErrors);

            // Validate zip code
            $zipErrors = Validation::validateZipCode($zip_code);
            $errors = array_merge($errors, $zipErrors);

            // Validate password
            $passwordStrength = Validation::validatePasswordStrength($password);
            if ($passwordStrength['strength'] === 'weak') {
                $errors[] = "Password is too weak. " . implode(', ', $passwordStrength['feedback']);
            }

            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }

            // Removed re-enter answer fields; no match validation needed
        }
        if (empty($errors)) {
            // Calculate age
            $birthdate_obj = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($birthdate_obj)->y;

            // Hash password
            $password_hash = Security::hashPassword($password);

            // Account ID is assigned automatically (YYYY-NNNN), only once everything is valid
            $id_number = AccountId::generate($conn);

            // Insert user with role 'user' and status 'pending'
            $role = 'user';
            $status = 'pending';
            $stmt = $conn->prepare("INSERT INTO users (id_number, username, password_hash, first_name, middle_name, last_name, extension_name, birthdate, age, email, sex, purok_street, barangay, city_municipality, province, country, zip_code, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param(
                    "ssssssssissssssssss",
                    $id_number,
                    $username,
                    $password_hash,
                    $first_name,
                    $middle_name,
                    $last_name,
                    $extension_name,
                    $birthdate,
                    $age,
                    $email,
                    $sex,
                    $purok_street,
                    $barangay,
                    $city_municipality,
                    $province,
                    $country,
                    $zip_code,
                    $role,
                    $status
                );

                if ($stmt->execute()) {
                    // Hash security answers and insert
                    $answer1_hash = Security::hashAnswer($security_answer1);
                    $answer2_hash = Security::hashAnswer($security_answer2);
                    $answer3_hash = Security::hashAnswer($security_answer3);

                    $stmt2 = $conn->prepare("INSERT INTO security_questions (user_id, question1, answer1_hash, question2, answer2_hash, question3, answer3_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");

                    if ($stmt2) {
                        $stmt2->bind_param("sssssss", $id_number, $security_question1, $answer1_hash, $security_question2, $answer2_hash, $security_question3, $answer3_hash);

                        if ($stmt2->execute()) {
                            // Log registration activity
                            $regFullName = trim($first_name . ' ' . $middle_name . ' ' . $last_name . ' ' . $extension_name);
                            ActivityLogger::log('REGISTER', "New user registered account: @{$username} ({$regFullName}, ID: {$id_number}). Pending admin approval.", 'Account', [
                                'id_number' => $id_number,
                                'username' => $username,
                                'full_name' => $regFullName,
                                'role' => 'user'
                            ]);

                            // Account must be confirmed with an emailed OTP before admins can approve it
                            $stmtV = $conn->prepare("UPDATE users SET email_verified = 0 WHERE id_number = ?");
                            $stmtV->bind_param("s", $id_number);
                            $stmtV->execute();
                            $stmtV->close();

                            $_SESSION['verify_account'] = [
                                'id_number' => $id_number,
                                'username' => $username,
                                'email' => $email,
                                'name' => trim($first_name . ' ' . $last_name)
                            ];
                            $_SESSION['verify_account_send'] = true;
                            $_SESSION['last_login_attempt'] = $username;
                            header("Location: verify-account.php");
                            exit();
                        } else {
                            $errors[] = "Failed to save security questions: " . $stmt2->error;
                        }
                        $stmt2->close();
                    } else {
                        $errors[] = "Database error: " . $conn->error;
                    }
                } else {
                    $errors[] = "Registration failed: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
        }

        $db->close();
    }
}

// Shown in the form only; the real ID is reserved when the registration is saved
$previewDb = new Database();
$nextAccountId = AccountId::preview($previewDb->getConnection());
$previewDb->close();

$csrf_token = Security::generateCSRFToken();
$birthdate_max = date('Y-m-d', strtotime('-18 years'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | BoardingHub - Boarding House Finder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/regstration.css">
  <link rel="stylesheet" href="../css/theme.css?v=<?php echo filemtime(__DIR__ . '/../css/theme.css'); ?>">
</head>

<body class="bh-auth">
    <header>
        <div class="logo">
            <h1 style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-building" style="font-size: 20px; color: #3b82f6;"></i> Boarding<span>Hub</span></h1>
        </div>
        <div class="navBar">
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="form-logo">
            <h1 style="display: flex; align-items: center; justify-content: center; gap: 8px;"><i class="fas fa-home" style="color: #3b82f6;"></i> Boarding<span>Hub</span></h1>
            <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Join the #1 Boarding House & Dormitory Network</p>
        </div>

        <h2 class="form-title">Register Boarder / Tenant Account</h2>

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="progress-line">
                <div class="progress-fill"></div>
            </div>
            <div class="step active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">Personal Info</div>
            </div>
            <div class="step" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">Address & Account</div>
            </div>
            <div class="step" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">Security</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message" id="server-errors">
                <?php foreach ($errors as $error): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="register-form" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <!-- Step 1: Personal Information -->
            <div class="form-step active" data-step="1">
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>

                    <div class="form-group">
                        <label class="form-label">Account ID</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card input-icon"></i>
                            <div class="form-input" style="display: flex; align-items: center; gap: 6px; color: #94a3b8; cursor: default;" title="Assigned automatically when you register">
                                <strong style="color: #f8fafc; letter-spacing: 0.5px;"><?php echo htmlspecialchars($nextAccountId); ?></strong>
                                <span>· assigned automatically</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">First Name <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-signature input-icon"></i>
                                    <input type="text" name="first_name" class="form-input"
                                        placeholder="Enter first name" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                                        onblur="validateName(this, 'First Name')"
                                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Middle Name <span class="optional">optional</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-signature input-icon"></i>
                                    <input type="text" name="middle_name" class="form-input"
                                        placeholder="Enter middle name" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" onblur="validateName(this, 'Middle Name')"
                                        value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Last Name <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-signature input-icon"></i>
                                    <input type="text" name="last_name" class="form-input" placeholder="Enter last name"
                                        required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" onblur="validateName(this, 'Last Name')"
                                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Extension Name <span class="optional">optional</span></label>
                                <select name="extension_name" class="form-input">
                                    <optgroup label="Standard">
                                        <option value="" <?php echo (isset($_POST['extension_name']) && $_POST['extension_name'] === '') ? 'selected' : ''; ?>>None</option>
                                        <option value="Jr." <?php echo (isset($_POST['extension_name']) && $_POST['extension_name'] === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                        <option value="Sr." <?php echo (isset($_POST['extension_name']) && $_POST['extension_name'] === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    </optgroup>
                                    <optgroup label="Others">
                                        <?php $romans = ['I','II','IX','X'];
                                        foreach ($romans as $r) {
                                            $sel = (isset($_POST['extension_name']) && $_POST['extension_name'] === $r) ? 'selected' : '';
                                            echo '<option value="'.htmlspecialchars($r).'" '.$sel.'>'.htmlspecialchars($r).'</option>';
                                        } ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Birthdate <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-calendar input-icon"></i>
                                    <input type="date" name="birthdate" class="form-input" id="birthdate" required max="<?php echo htmlspecialchars($birthdate_max); ?>"
                                        onchange="calculateAge()"
                                        value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Age <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-birthday-cake input-icon"></i>
                                    <div class="age-display" id="age-display">Enter birthdate</div>
                                    <div id="age-error" class="field-error" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Email Address <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" name="email" class="form-input" placeholder="Enter your email"
                                        required
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Sex <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-venus-mars input-icon"></i>
                                    <select name="sex" class="form-input" required>
                                        <option value="">Select sex</option>
                                        <option value="male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-next" data-next="2">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Address Information -->
            <div class="form-step" data-step="2">
                <div class="form-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>

                    <p class="ac-help"><i class="fas fa-lightbulb"></i> Start typing and pick from the list. Choose your Province first, then City/Municipality, then Barangay.</p>

                    <!-- Row: Province and City/Municipality (suggestions cascade from here) -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Province <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-globe input-icon"></i>
                                    <input type="text" name="province" class="form-input" placeholder="Search province, e.g. Agusan del Sur"
                                        required autocomplete="off" onblur="validateAddress(this, 'Province')"
                                        value="<?php echo isset($_POST['province']) ? htmlspecialchars($_POST['province']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">City/Municipality <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-city input-icon"></i>
                                    <input type="text" name="city_municipality" class="form-input"
                                        placeholder="Search city or municipality" required autocomplete="off"
                                        onblur="validateAddress(this, 'City/Municipality')"
                                        value="<?php echo isset($_POST['city_municipality']) ? htmlspecialchars($_POST['city_municipality']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row: Barangay and Purok/Street -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Barangay <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-marked input-icon"></i>
                                    <input type="text" name="barangay" class="form-input" placeholder="Search barangay"
                                        required autocomplete="off" onblur="validateAddress(this, 'Barangay')"
                                        value="<?php echo isset($_POST['barangay']) ? htmlspecialchars($_POST['barangay']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Purok/Street <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-road input-icon"></i>
                                    <input type="text" name="purok_street" class="form-input"
                                        placeholder="e.g. Purok 3, Rizal St." required
                                        onblur="validateAddress(this, 'Purok/Street')"
                                        value="<?php echo isset($_POST['purok_street']) ? htmlspecialchars($_POST['purok_street']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row: Country and Zip Code -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Country <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-globe-americas input-icon"></i>
                                    <input type="text" name="country" class="form-input" placeholder="Enter country"
                                        required onblur="validateAddress(this, 'Country')"
                                        value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Zip Code <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-pin input-icon"></i>
                                    <input type="text" name="zip_code" class="form-input"
                                        placeholder="Enter zip code (numbers only)" pattern="\d{4,10}" title="4-10 digits only"
                                        required onblur="validateZipCode(this)"
                                        value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information (moved into Step 2) -->
                    <div class="form-section">

                        <div class="form-group">
                            <label class="form-label">Username <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" name="username" class="form-input" placeholder="Choose a username"
                                    pattern="[a-zA-Z0-9._@!#$*\-]{3,20}" title="3-20 characters: letters, numbers, and . _ - @ ! # $ * (no spaces)"
                                    required
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <div id="username-feedback"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Password <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-key input-icon"></i>
                                        <input type="password" name="password" id="password" class="form-input"
                                            placeholder="Create a password" required oninput="validatePasswordStrength()">
                                        <span class="password-toggle" onclick="togglePassword('password')">
                                            <i class="fas fa-eye" id="password-icon"></i>
                                        </span>
                                    </div>
                                    <div class="password-strength" id="password-strength"></div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-key input-icon"></i>
                                        <input type="password" name="confirm_password" class="form-input" id="confirm_password"
                                            placeholder="Re-enter password" required onblur="validatePasswordMatch()">
                                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye" id="confirm_password-icon"></i>
                                        </span>
                                    </div>
                                    <div class="password-match" id="password-match" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-prev" data-prev="1">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="btn btn-next" data-next="3">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Security Questions -->
            <div class="form-step" data-step="3">
                <div class="form-section">
                    <h3><i class="fas fa-shield-alt"></i> Security Questions</h3>

                    <!-- Question 1 -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Security Question 1 <span class="required">*</span></label>
                                <select name="security_question1" class="form-input" required>
                                    <option value="">Select a question</option>
                                    <option value="Who is your best friend in Elementary?" <?php echo (isset($_POST['security_question1']) && $_POST['security_question1'] == 'Who is your best friend in Elementary?') ? 'selected' : ''; ?>>Who is your best friend in Elementary?</option>
                                    <option value="What is the name of your favorite pet?" <?php echo (isset($_POST['security_question1']) && $_POST['security_question1'] == 'What is the name of your favorite pet?') ? 'selected' : ''; ?>>What is the name of your favorite pet?</option>
                                    <option value="Who is your favorite teacher in high school?" <?php echo (isset($_POST['security_question1']) && $_POST['security_question1'] == 'Who is your favorite teacher in high school?') ? 'selected' : ''; ?>>Who is your favorite teacher in high school?</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Your Answer <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="security_answer1" id="security_answer1" class="form-input" placeholder="Enter your answer" required value="<?php echo isset($_POST['security_answer1']) ? htmlspecialchars($_POST['security_answer1']) : ''; ?>">
                                    <span class="password-toggle" onclick="togglePassword('security_answer1')">
                                        <i class="fas fa-eye" id="security_answer1-icon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Question 2 -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Security Question 2 <span class="required">*</span></label>
                                <select name="security_question2" class="form-input" required>
                                    <option value="">Select a question</option>
                                    <option value="Who is your best friend in Elementary?" <?php echo (isset($_POST['security_question2']) && $_POST['security_question2'] == 'Who is your best friend in Elementary?') ? 'selected' : ''; ?>>Who is your best friend in Elementary?</option>
                                    <option value="What is the name of your favorite pet?" <?php echo (isset($_POST['security_question2']) && $_POST['security_question2'] == 'What is the name of your favorite pet?') ? 'selected' : ''; ?>>What is the name of your favorite pet?</option>
                                    <option value="Who is your favorite teacher in high school?" <?php echo (isset($_POST['security_question2']) && $_POST['security_question2'] == 'Who is your favorite teacher in high school?') ? 'selected' : ''; ?>>Who is your favorite teacher in high school?</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Your Answer <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="security_answer2" id="security_answer2" class="form-input" placeholder="Enter your answer" required value="<?php echo isset($_POST['security_answer2']) ? htmlspecialchars($_POST['security_answer2']) : ''; ?>">
                                    <span class="password-toggle" onclick="togglePassword('security_answer2')">
                                        <i class="fas fa-eye" id="security_answer2-icon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Question 3 -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Security Question 3 <span class="required">*</span></label>
                                <select name="security_question3" class="form-input" required>
                                    <option value="">Select a question</option>
                                    <option value="Who is your best friend in Elementary?" <?php echo (isset($_POST['security_question3']) && $_POST['security_question3'] == 'Who is your best friend in Elementary?') ? 'selected' : ''; ?>>Who is your best friend in Elementary?</option>
                                    <option value="What is the name of your favorite pet?" <?php echo (isset($_POST['security_question3']) && $_POST['security_question3'] == 'What is the name of your favorite pet?') ? 'selected' : ''; ?>>What is the name of your favorite pet?</option>
                                    <option value="Who is your favorite teacher in high school?" <?php echo (isset($_POST['security_question3']) && $_POST['security_question3'] == 'Who is your favorite teacher in high school?') ? 'selected' : ''; ?>>Who is your favorite teacher in high school?</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label class="form-label">Your Answer <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="security_answer3" id="security_answer3" class="form-input" placeholder="Enter your answer" required value="<?php echo isset($_POST['security_answer3']) ? htmlspecialchars($_POST['security_answer3']) : ''; ?>">
                                    <span class="password-toggle" onclick="togglePassword('security_answer3')">
                                        <i class="fas fa-eye" id="security_answer3-icon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-prev" data-prev="2">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </div>
            </div>

        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Log-in here</a></p>
        </div>
    </div>

    <script src="../js/auth.js?v=<?php echo filemtime(__DIR__ . '/../js/auth.js'); ?>"></script>
    <script src="../js/validation.js?v=<?php echo filemtime(__DIR__ . '/../js/validation.js'); ?>"></script>
    <script src="../js/multi_step.js?v=<?php echo filemtime(__DIR__ . '/../js/multi_step.js'); ?>"></script>
    <script>
    // Each secret question can only be picked once
    (function () {
        const selects = [1, 2, 3].map(i => document.querySelector('select[name="security_question' + i + '"]')).filter(Boolean);
        function sync() {
            const taken = selects.map(s => s.value);
            selects.forEach(s => {
                [...s.options].forEach(o => {
                    o.disabled = o.value !== '' && o.value !== s.value && taken.includes(o.value);
                });
            });
        }
        selects.forEach(s => s.addEventListener('change', sync));
        sync();
    })();
    </script>
    <script src="../js/address_autocomplete.js?v=<?php echo filemtime(__DIR__ . '/../js/address_autocomplete.js'); ?>"></script>
    <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#94a3b8;font-family:'Plus Jakarta Sans',sans-serif;border-top:1px solid rgba(255,255,255,0.1);">
        &copy; <?php echo date('Y'); ?> BoardingHub. All rights reserved.
    </footer>
</body>

</html>