<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}

$errors = [];
$success = false;
$old = [
    'name' => '', 'email' => '', 'specialization' => '',
    'license_no' => '', 'qualification' => '', 'experience' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    if ($old['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($old['specialization'] === '') $errors[] = 'Specialization is required.';
    if ($old['license_no'] === '') $errors[] = 'License number is required.';
    if ($old['qualification'] === '') $errors[] = 'Qualification is required.';
    if (!ctype_digit($old['experience'])) $errors[] = 'Experience must be a whole number of years.';
    if (empty($_FILES['image']['name'])) $errors[] = 'A profile/ID image is required.';

    $imagePath = null;
    if (empty($errors)) {
        try {
            $imagePath = handle_doctor_image_upload($_FILES['image']);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $db = getDB();

        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            try {
                $db->beginTransaction();

                // No password yet — password_hash stays NULL until an admin
                // verifies this doctor and the system generates credentials.
                $stmt = $db->prepare(
                    'INSERT INTO users (email, role, status) VALUES (?, ?, ?) RETURNING id'
                );
                $stmt->execute([$old['email'], 'doctor', 'pending']);
                $userId = $stmt->fetchColumn();

                $stmt = $db->prepare(
                    'INSERT INTO doctor_profiles
                        (user_id, name, specialization, license_no, qualification, experience_years, image_path, verification_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userId, $old['name'], $old['specialization'], $old['license_no'],
                    $old['qualification'], (int)$old['experience'], $imagePath, 'pending',
                ]);

                $db->commit();
                $success = true;
                $old = array_fill_keys(array_keys($old), '');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/auth_header.php'; ?>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-intro">
                <span class="auth-badge">Doctor Onboarding</span>
                <h2>Join the medical team</h2>
                <p>Submit your credentials for review and become part of the hospital network.</p>
                <ul class="auth-features">
                    <li>Secure verification workflow</li>
                    <li>Profile and license upload</li>
                    <li>Admin-reviewed onboarding</li>
                </ul>
            </div>
            <div class="auth-form-panel">
                <div class="container">
                    <h1>Doctor Registration</h1>

                    <?php if ($success): ?>
                        <div class="success">
                            Registration submitted! An admin will review your license and qualifications.
                            You'll receive your login ID and password by email once verified.
                        </div>
                        <a class="btn" href="../login.php">Back to Login</a>
                    <?php else: ?>

                        <?php foreach ($errors as $error): ?>
                            <div class="error"><?= clean($error) ?></div>
                        <?php endforeach; ?>

                        <form method="POST" enctype="multipart/form-data" novalidate>
                            <?= csrf_field() ?>
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?= clean($old['name']) ?>" required>

                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= clean($old['email']) ?>" required>

                            <label for="specialization">Specialization</label>
                            <input type="text" id="specialization" name="specialization" value="<?= clean($old['specialization']) ?>" required>

                            <label for="license_no">License Number</label>
                            <input type="text" id="license_no" name="license_no" value="<?= clean($old['license_no']) ?>" required>

                            <label for="qualification">Qualification</label>
                            <input type="text" id="qualification" name="qualification" value="<?= clean($old['qualification']) ?>" required>

                            <label for="experience">Experience (years)</label>
                            <input type="number" id="experience" name="experience" min="0" value="<?= clean($old['experience']) ?>" required>

                            <label for="image">Profile / ID Image</label>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>

                            <p class="helper-text">
                                No password is needed here — you'll receive a login ID and password by
                                email once an admin verifies your license and qualifications.
                            </p>

                            <button type="submit">Submit for Verification</button>
                        </form>
                        <div class="links">
                            Already have an account? <a href="../login.php">Log in</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
