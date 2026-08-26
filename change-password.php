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
        redirect('/doctor/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | HAMS</title>
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>
    <main class="page-shell">
        <section class="form-panel">
            <div class="form-card">
                <div class="form-header">
                    <p class="eyebrow">First login</p>
                    <h1>Set your new password</h1>
                    <p>Your temporary password has been accepted. Choose a new password to continue.</p>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= clean($error) ?></div>
                <?php endforeach; ?>

                <form class="register-form" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" minlength="8" required>
                    </div>
                    <div class="form-row">
                        <label for="confirm_password">Confirm new password</label>
                        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Update password</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
