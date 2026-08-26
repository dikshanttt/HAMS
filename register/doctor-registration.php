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

    if ($name === '' || $email === '' || $phone === '' || $licenseNo === '' || $specialization === '' || $qualification === '' || $experience === '') {
        $errors[] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!is_numeric($experience) || (int) $experience < 0) {
        $errors[] = 'Please enter a valid number of years.';
    } else {
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
        verification_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $profileStmt->execute([
                    $userId,
                    $name,
                    $specialization,
                    $licenseNo,
                    $phone,
                    $qualification,
                    (int) $experience,
                    'pending'
                ]);

                $db->commit();
                set_flash('success', 'Your doctor application was submitted successfully. An administrator will review it shortly.');
                redirect('../login.php');
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
    <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body>
    <main class="page-shell">
        <section class="form-panel">
            <div class="form-card">
                <div class="form-header">
                    <p class="eyebrow">Doctor Application</p>
                    <h1>Apply for a doctor account</h1>
                    <p>Provide your personal and professional details for admin review.</p>
                </div>

                <div class="info-banner">
                    <p>Doctor accounts require admin verification. After approval, login credentials will be provided.</p>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= clean($error) ?></div>
                <?php endforeach; ?>

                <form id="doctorForm" class="register-form" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <div class="section-label">Personal Information</div>
                    <div class="form-row">
                        <label for="doctorName">Full Name</label>
                        <input id="doctorName" name="doctorName" type="text" value="<?= clean($name) ?>" placeholder="Dr. Alex Morgan" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorEmail">Email Address</label>
                        <input id="doctorEmail" name="doctorEmail" type="email" value="<?= clean($email) ?>" placeholder="alex@example.com" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorPhone">Phone Number</label>
                        <input id="doctorPhone" name="doctorPhone" type="tel" value="<?= clean($phone) ?>" placeholder="(123) 456-7890" required>
                    </div>

                    <div class="section-label">Professional Information</div>
                    <div class="form-row">
                        <label for="doctorLicense">Medical License Number</label>
                        <input id="doctorLicense" name="doctorLicense" type="text" value="<?= clean($licenseNo) ?>" placeholder="LIC-123456" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorSpecialization">Specialization</label>
                        <input id="doctorSpecialization" name="doctorSpecialization" type="text" value="<?= clean($specialization) ?>" placeholder="Cardiology" required>
                    </div>
                    <div class="form-row form-grid-2">
                        <div>
                            <label for="doctorQualification">Qualification</label>
                            <input id="doctorQualification" name="doctorQualification" type="text" value="<?= clean($qualification) ?>" placeholder="MD, MBBS" required>
                        </div>
                        <div>
                            <label for="doctorExperience">Years of Experience</label>
                            <input id="doctorExperience" name="doctorExperience" type="number" min="0" value="<?= clean($experience) ?>" placeholder="10" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Submit Application</button>
                </form>
            </div>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>

</html>