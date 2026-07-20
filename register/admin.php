<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

// NOTE FOR YOUR TEAM: leaving admin self-registration open to the public is
// usually a bad idea in a real deployment (anyone could become an admin).
// Common alternatives: (1) restrict this page to already-logged-in admins,
// or (2) remove it entirely and seed admins directly via schema.sql.
// It's left open here since the spec calls for "multiple admins" and this
// is still an early school-project stage — revisit before going live.

if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db = getDB();

        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            try {
                $db->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare(
                    'INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, ?, ?) RETURNING id'
                );
                $stmt->execute([$old['email'], $hash, 'admin', 'active']);
                $userId = $stmt->fetchColumn();

                $stmt = $db->prepare('INSERT INTO admin_profiles (user_id, name) VALUES (?, ?)');
                $stmt->execute([$userId, $old['name']]);

                $db->commit();

                login_user((int)$userId, 'admin');
                redirect('/admin/dashboard.php');
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
    <title>Admin Registration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/auth_header.php'; ?>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-intro">
                <span class="auth-badge">Administrator Access</span>
                <h2>Set up an admin account</h2>
                <p>Create an administrative account to oversee the hospital management system.</p>
                <ul class="auth-features">
                    <li>Secure admin onboarding</li>
                    <li>Protected account setup</li>
                    <li>Immediate access to management tools</li>
                </ul>
            </div>
            <div class="auth-form-panel">
                <div class="container">
                    <h1>Admin Registration</h1>

                    <?php foreach ($errors as $error): ?>
                        <div class="error"><?= clean($error) ?></div>
                    <?php endforeach; ?>

                    <form method="POST" novalidate>
                        <?= csrf_field() ?>
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= clean($old['name']) ?>" required>

                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= clean($old['email']) ?>" required>

                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="8">

                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

                        <button type="submit">Register</button>
                    </form>
                    <div class="links">
                        Already have an account? <a href="../login.php">Log in</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
