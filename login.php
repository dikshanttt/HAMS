<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}

$errors = [];
$identifier = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $errors[] = 'Please enter your login details and password.';
    } else {
        $db = getDB();

        if (preg_match('/^DOC-\d+$/i', $identifier)) {
            $stmt = $db->prepare('SELECT * FROM users WHERE doctor_login_id = ? AND role = ?');
            $stmt->execute([strtoupper($identifier), 'doctor']);
        } else {
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$identifier]);
        }

        $user = $stmt->fetch();

        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid credentials.';
        } elseif ($user['status'] !== 'active') {
            if ($user['role'] === 'doctor' && $user['status'] === 'pending') {
                $errors[] = 'Your account is still awaiting admin verification.';
            } else {
                $errors[] = 'Your account is not active. Please contact an administrator.';
            }
        } else {
            login_user((int) $user['id'], $user['role']);

            if ($user['force_password_change']) {
                redirect('/change-password.php');
            }

            redirect('/' . $user['role'] . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HAMS Hospital Appointment Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css'); ?>">
</head>

<body class="login-page">
    <div class="login-shell">
        <!-- Visual Brand Side -->
        <section class="login-visual" aria-label="Healthcare welcome illustration">
            <div class="login-visual-card">
                <a class="brand" href="index.php">
                    <span class="brand-icon">✚</span>
                    <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
                </a>
                <span class="eyebrow">Patient & Doctor Portal</span>
                <h1>Access Your Healthcare Dashboard</h1>
                <p class="hero-copy">Manage upcoming appointments, track patient queues, and access medical consultations securely.</p>

                <div class="illustration-wrap">
                    <div class="mini-appointment-card">
                        <div class="check-icon-circle">✓</div>
                        <div class="appointment-details">
                            <strong>Instant Queue Verification</strong>
                            <span>Direct hospital token access</span>
                        </div>
                    </div>
                </div>

                <div class="trust-list">
                    <span>🛡️ 256-Bit Encrypted Healthcare Records</span>
                    <span>⏱️ Instant Appointment Rescheduling</span>
                    <span>🏥 24/7 Verified Specialist Network</span>
                </div>
            </div>
        </section>

        <!-- Form Side -->
        <section class="login-form-panel">
            <div class="login-card">
                <span class="eyebrow">Secure Sign In</span>
                <h2>Welcome Back</h2>
                <p class="subtext">Enter your registered email address or Doctor ID (e.g. DOC-1001).</p>

                <?php if ($flash): ?>
                    <div class="<?= $flash['type'] === 'error' ? 'error-message' : 'success-message' ?>">
                        <?= clean($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= clean($error) ?></div>
                <?php endforeach; ?>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <label for="identifier">Email Address or Doctor ID</label>
                        <div class="input-wrap">
                            <input type="text" id="identifier" name="identifier" value="<?= clean($identifier) ?>" placeholder="e.g. name@example.com or DOC-1001" required autofocus>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="password">Password</label>
                        <div class="input-wrap password-wrap">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Toggle password visibility">👁️</button>
                        </div>
                    </div>

                    <button class="btn btn-primary login-btn" type="submit">Sign In</button>
                </form>

                <div class="alt-actions">
                    <p>New to HAMS? <a class="text-link" href="register/account-type.php">Create an Account</a></p>
                    <p><a class="back-link" href="index.php">← Back to Homepage</a></p>
                </div>
            </div>
        </section>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleBtn.textContent = isPassword ? '🙈' : '👁️';
            });
        }
    </script>
</body>

</html>