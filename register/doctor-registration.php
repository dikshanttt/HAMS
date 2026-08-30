<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$errors = [];
$name = '';
$email = '';
$phone = '';
$licenseNo = '';
$specialization = '';
$qualification = '';
$experience = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['doctorName'] ?? '');
    $email = trim($_POST['doctorEmail'] ?? '');
    $phone = trim($_POST['doctorPhone'] ?? '');
    $licenseNo = trim($_POST['doctorLicense'] ?? '');
    $specialization = trim($_POST['doctorSpecialization'] ?? '');
    $qualification = trim($_POST['doctorQualification'] ?? '');
    $experience = trim($_POST['doctorExperience'] ?? '');

    $imagePath = null;
    if (isset($_FILES['doctorImage']) && $_FILES['doctorImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $imagePath = handle_doctor_image_upload($_FILES['doctorImage']);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($name === '' || $email === '' || $phone === '' || $licenseNo === '' || $specialization === '' || $qualification === '' || $experience === '') {
        $errors[] = 'All fields are required.';
    } elseif (strlen($name) > 150 || strlen($phone) > 20 || strlen($licenseNo) > 100 || strlen($specialization) > 150 || strlen($qualification) > 150) {
        $errors[] = 'Please keep your details within the allowed length.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+() .-]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    } elseif (!preg_match('/^\d{1,2}$/', $experience) || (int) $experience < 0 || (int) $experience > 80) {
        $errors[] = 'Please enter a valid number of years.';
    } elseif (empty($errors)) {
        $db = getDB();
        $check = $db->prepare('SELECT 1 FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'A user with this email already exists.';
        } else {
            try {
                $db->beginTransaction();
                $userStmt = $db->prepare('INSERT INTO users (email, role, status) VALUES (?, ?, ?) RETURNING id');
                $userStmt->execute([$email, 'doctor', 'pending']);
                $userId = (int) $userStmt->fetchColumn();

                $profileStmt = $db->prepare(
                    'INSERT INTO doctor_profiles
                    (
                        user_id,
                        name,
                        specialization,
                        license_no,
                        phone,
                        qualification,
                        experience_years,
                        image_path,
                        verification_status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $profileStmt->execute([
                    $userId,
                    $name,
                    $specialization,
                    $licenseNo,
                    $phone,
                    $qualification,
                    (int) $experience,
                    $imagePath,
                    'pending'
                ]);

                $db->commit();
                set_flash('success', 'Your doctor application was submitted successfully. An administrator will review it shortly.');
                redirect('/login.php');
            } catch (Throwable $e) {
                $db->rollBack();
                $errors[] = 'Unable to submit the application right now. Please try again.';
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
    <title>Doctor Application | HAMS</title>
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
                <span class="eyebrow">Doctor Accreditation</span>
                <h1>Apply as a Specialist Doctor</h1>
                <p>Join HAMS to manage patient appointments, digital consultation tokens, and hospital schedules.</p>
            </div>

            <div class="info-banner">
                <span>🛡️ Doctor profiles undergo administrative license verification. Upon approval, your unique Doctor ID and initial credentials will be issued.</span>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?= clean($error) ?></div>
            <?php endforeach; ?>

            <form id="doctorForm" class="register-form" method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <div class="section-label">1. Personal & Contact Information</div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="doctorName">Full Name & Title</label>
                        <input id="doctorName" name="doctorName" type="text" value="<?= clean($name) ?>" placeholder="e.g. Dr. Alex Morgan" required autofocus>
                    </div>

                    <div class="form-row">
                        <label for="doctorEmail">Professional Email Address</label>
                        <input id="doctorEmail" name="doctorEmail" type="email" value="<?= clean($email) ?>" placeholder="e.g. alex.morgan@hospital.org" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="doctorPhone">Direct Phone Number</label>
                        <input id="doctorPhone" name="doctorPhone" type="tel" value="<?= clean($phone) ?>" placeholder="e.g. +977 9800000000" required>
                    </div>

                    <div class="form-row">
                        <label for="doctorImage">Profile Photo <small style="color: var(--text-muted); font-weight: normal;">(JPG/PNG/WEBP, max 2MB)</small></label>
                        <input id="doctorImage" name="doctorImage" type="file" accept="image/jpeg, image/png, image/webp">
                    </div>
                </div>

                <div class="section-label">2. Medical Credentials & Practice Details</div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="doctorLicense">Medical License Number</label>
                        <input id="doctorLicense" name="doctorLicense" type="text" value="<?= clean($licenseNo) ?>" placeholder="e.g. NMC-14529" required>
                    </div>

                    <div class="form-row">
                        <label for="doctorSpecialization">Department / Specialty</label>
                        <input id="doctorSpecialization" name="doctorSpecialization" type="text" value="<?= clean($specialization) ?>" placeholder="e.g. Cardiology, Neurology" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-row">
                        <label for="doctorQualification">Degrees & Qualifications</label>
                        <input id="doctorQualification" name="doctorQualification" type="text" value="<?= clean($qualification) ?>" placeholder="e.g. MBBS, MD (Cardiology), Fellowship" required>
                    </div>

                    <div class="form-row">
                        <label for="doctorExperience">Years of Clinical Experience</label>
                        <input id="doctorExperience" name="doctorExperience" type="number" min="0" max="80" value="<?= clean($experience) ?>" placeholder="e.g. 10" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">Submit Accreditation Application</button>
            </form>

            <p class="form-footer">Already verified? <a href="../login.php">Sign In with Doctor ID</a></p>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>

</html>