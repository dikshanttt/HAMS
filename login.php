<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $errors[] = 'Please enter your login details and password.';
    } else {
        $db = getDB();

        // Doctors log in with a doctor_login_id (e.g. DOC-1001), everyone
        // else logs in with their email. Detect which one we were given.
        if (preg_match('/^DOC-\d+$/i', $identifier)) {
            $stmt = $db->prepare('SELECT * FROM users WHERE doctor_login_id = ? AND role = ?');
            $stmt->execute([strtoupper($identifier), 'doctor']);
        } else {
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$identifier]);
        }

        $user = $stmt->fetch();

        // Doctors whose password_hash is still NULL (not yet verified) will
        // simply fail verification here — no separate check needed, since
        // password_verify() against a NULL hash always returns false.
        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid credentials.';
        } elseif ($user['status'] !== 'active') {
            if ($user['role'] === 'doctor' && $user['status'] === 'pending') {
                $errors[] = 'Your account is still awaiting admin verification.';
            } else {
                $errors[] = 'Your account is not active. Please contact an administrator.';
            }
        } else {
            login_user((int)$user['id'], $user['role']);
            redirect('/' . $user['role'] . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/auth_header.php'; ?>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-intro">
                <span class="auth-badge">Hospital Management System</span>
                <h2>Welcome back</h2>
                <p>Sign in to access your role-based dashboard for patients, doctors, and administrators.</p>
                <ul class="auth-features">
                    <li>Secure sign-in experience</li>
                    <li>Quick access to appointments and records</li>
                    <li>Simple onboarding for new users</li>
                </ul>
            </div>
            <div class="auth-form-panel">
                <div class="container">
                    <h1>Login</h1>

                    <?php foreach ($errors as $error): ?>
                        <div class="error"><?= clean($error) ?></div>
                    <?php endforeach; ?>

                    <form method="POST" novalidate>
                        <?= csrf_field() ?>
                        <label for="identifier">Email (Patients/Admins) or Doctor Login ID</label>
                        <input type="text" id="identifier" name="identifier" value="<?= clean($identifier) ?>" required>

                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>

                        <button type="submit">Log In</button>
                    </form>
                    <div class="links">
                        New here?
                        <a href="register/patient.php">Register as Patient</a> ·
                        <a href="register/doctor.php">Register as Doctor</a> 
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
