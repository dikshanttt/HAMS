<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

require_login(['doctor']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password cannot exceed 72 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare(
            'UPDATE users
             SET password_hash = ?, force_password_change = FALSE
             WHERE id = ? AND role = ? AND status = ?'
        );
        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            current_user_id(),
            'doctor',
            'active'
        ]);

        $_SESSION['force_password_change'] = false;
        set_flash('success', 'Your password has been updated successfully.');
        redirect('/doctor/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/register.css?v=<?= filemtime('assets/css/register.css'); ?>">
</head>
<body>
    <header class="auth-header">
        <a class="brand" href="index.php">
            <span class="brand-icon">✚</span>
            <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
        </a>
    </header>

    <main class="page-shell narrow">
        <section class="form-panel">
            <div class="form-header">
                <span class="eyebrow">First Sign In Security</span>
                <h1>Create Permanent Password</h1>
                <p>Your temporary Doctor ID credentials have been verified. Please create a strong permanent password to secure your account.</p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?= clean($error) ?></div>
            <?php endforeach; ?>

            <form class="register-form" method="POST" novalidate>
                <?= csrf_field() ?>
                <div class="form-row">
                    <label for="password">New Password (8–72 characters)</label>
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" placeholder="Enter new password" required autofocus>
                </div>
                <div class="form-row">
                    <label for="confirm_password">Confirm New Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" maxlength="72" placeholder="Re-enter new password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full btn-lg">Save Password & Continue to Dashboard</button>
            </form>
        </section>
    </main>
</body>
</html>
