<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$errors = [];
$name = '';
$email = '';
$phone = '';
$dob = '';
$gender = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['patientName'] ?? '');
    $email = trim($_POST['patientEmail'] ?? '');
    $phone = trim($_POST['patientPhone'] ?? '');
    $dob = trim($_POST['patientDob'] ?? '');
    $gender = trim($_POST['patientGender'] ?? '');
    $password = $_POST['patientPassword'] ?? '';
    $confirmPassword = $_POST['patientConfirmPassword'] ?? '';

    $date = DateTime::createFromFormat('Y-m-d', $dob);

    if ($name === '' || $email === '' || $phone === '' || $dob === '' || $gender === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'All fields are required.';
    } elseif (strlen($name) > 150 || strlen($phone) > 20) {
        $errors[] = 'Please keep your name and phone number within the allowed length.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+() .-]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    } elseif (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTime('today')) {
        $errors[] = 'Please enter a valid date of birth.';
    } elseif (!in_array($gender, ['female', 'male', 'other', 'prefer_not'], true)) {
        $errors[] = 'Please select a valid gender.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password cannot exceed 72 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $db = getDB();
        $check = $db->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'A user with this email already exists.';
        } else {
            try {
                $db->beginTransaction();
                $userStmt = $db->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, ?, ?) RETURNING id');
                $userStmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'patient', 'active']);
                $userId = (int) $userStmt->fetchColumn();

                $profileStmt = $db->prepare('INSERT INTO patient_profiles (user_id, name, phone, date_of_birth, gender) VALUES (?, ?, ?, ?, ?)');
                $profileStmt->execute([$userId, $name, $phone, $dob, $gender]);

                $db->commit();
                set_flash('success', 'Your patient account was created successfully. Please log in.');
                redirect('/login.php');
            } catch (Throwable $e) {
                $db->rollBack();
                $errors[] = 'Unable to create the account right now. Please try again.';
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
    <title>Patient Registration | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register.css?v=<?= filemtime('../assets/css/register.css'); ?>">
</head>
<body>
    <header class="auth-header">
        <a class="brand" href="../index.php">
            <span class="brand-icon">✚</span>
            <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
        </a>
        <a class="back-link" href="account-type.php">← Back to Selection</a>
    </header>

    <main class="page-shell">
        <section class="form-panel">
            <div class="form-header">
                <span class="eyebrow">Patient Account</span>
                <h1>Create your patient profile</h1>
                <p>Enter your details to instantly book hospital appointments, avoid waiting room lines, and track your visits.</p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?= clean($error) ?></div>
            <?php endforeach; ?>

            <form id="patientForm" class="register-form" method="POST" novalidate>
                <?= csrf_field() ?>
                
                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="patientName">Full Legal Name</label>
                        <input id="patientName" name="patientName" type="text" value="<?= clean($name) ?>" placeholder="e.g. Jane Doe" required autofocus>
                    </div>

                    <div class="form-row">
                        <label for="patientEmail">Email Address</label>
                        <input id="patientEmail" name="patientEmail" type="email" value="<?= clean($email) ?>" placeholder="e.g. jane@example.com" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="patientPhone">Phone Number</label>
                        <input id="patientPhone" name="patientPhone" type="tel" value="<?= clean($phone) ?>" placeholder="e.g. +977 9800000000" required>
                    </div>

                    <div class="form-row">
                        <label for="patientDob">Date of Birth</label>
                        <input id="patientDob" name="patientDob" type="date" value="<?= clean($dob) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <label for="patientGender">Gender</label>
                    <select id="patientGender" name="patientGender" required>
                        <option value="">Select gender identity</option>
                        <option value="female" <?= ($gender === 'female') ? 'selected' : '' ?>>Female</option>
                        <option value="male" <?= ($gender === 'male') ? 'selected' : '' ?>>Male</option>
                        <option value="other" <?= ($gender === 'other') ? 'selected' : '' ?>>Other</option>
                        <option value="prefer_not" <?= ($gender === 'prefer_not') ? 'selected' : '' ?>>Prefer not to say</option>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="patientPassword">Password (min 8 characters)</label>
                        <div class="input-container password-container">
                            <input id="patientPassword" name="patientPassword" type="password" placeholder="Create a secure password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="patientConfirmPassword">Confirm Password</label>
                        <div class="input-container password-container">
                            <input id="patientConfirmPassword" name="patientConfirmPassword" type="password" placeholder="Re-enter your password" required>
                        </div>
                    </div>
                </div>

                <div class="info-banner">
                    <span>🔒 Your personal healthcare data is protected and kept strictly confidential.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">Complete Registration & Book</button>
            </form>

            <p class="form-footer">Already registered? <a href="../login.php">Sign In to Dashboard</a></p>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>
</html>
