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
<<<<<<< HEAD
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.0">
</head>
<body class="login-page">
    <div class="login-shell">
        <section class="login-visual" aria-label="Healthcare welcome illustration">
            <div class="login-visual-card">
                <a class="brand" href="index.php">
                    <span class="brand-mark">✚</span>
                    <span>HAMS</span>
                </a>
                <p class="eyebrow">Patient Portal</p>
                <h1>Your Healthcare Journey Starts Here</h1>
                <p class="hero-copy">Book appointments, manage visits, and connect with trusted hospitals easily.</p>

                <div class="illustration-wrap" aria-hidden="true">
                    <svg viewBox="0 0 420 320" role="img" aria-label="Doctor and patient using healthcare technology">
                        <rect x="38" y="32" width="344" height="252" rx="28" fill="#f3fef6" />
                        <rect x="72" y="74" width="142" height="112" rx="20" fill="#ffffff" stroke="#d8f5e0" stroke-width="2" />
                        <rect x="92" y="96" width="48" height="48" rx="14" fill="#16a34a" />
                        <rect x="148" y="100" width="52" height="12" rx="6" fill="#d9fbe7" />
                        <rect x="148" y="118" width="42" height="10" rx="5" fill="#e9f9ef" />
                        <rect x="238" y="82" width="112" height="96" rx="18" fill="#ffffff" stroke="#d8f5e0" stroke-width="2" />
                        <rect x="254" y="104" width="44" height="36" rx="10" fill="#15803d" />
                        <rect x="306" y="104" width="20" height="36" rx="8" fill="#16a34a" />
                        <circle cx="154" cy="220" r="46" fill="#fef3c7" />
                        <circle cx="153" cy="218" r="32" fill="#1f2937" />
                        <path d="M132 219c6-24 30-34 42-18 6 8 8 17 3 27-8 15-26 24-40 18-10-5-13-16-5-27Z" fill="#16a34a" />
                        <rect x="126" y="252" width="58" height="20" rx="10" fill="#16a34a" />
                        <circle cx="282" cy="216" r="38" fill="#dcfce7" />
                        <circle cx="282" cy="214" r="24" fill="#1f2937" />
                        <rect x="264" y="246" width="40" height="18" rx="9" fill="#15803d" />
                        <rect x="170" y="176" width="64" height="18" rx="9" fill="#16a34a" />
                        <rect x="170" y="202" width="48" height="13" rx="7" fill="#d8f5e0" />
                        <rect x="88" y="172" width="70" height="58" rx="14" fill="#ffffff" stroke="#d8f5e0" stroke-width="2" />
                        <rect x="102" y="186" width="22" height="24" rx="6" fill="#16a34a" />
                        <rect x="132" y="186" width="18" height="24" rx="6" fill="#15803d" />
                    </svg>
                </div>

                <div class="trust-list">
                    <span>✓ Secure Login</span>
                    <span>✓ Trusted Healthcare Platform</span>
                    <span>✓ Easy Appointment Management</span>
                </div>
            </div>
        </section>

        <section class="login-form-panel">
            <div class="login-card">
                <p class="eyebrow">Patient Access</p>
                <h2>Welcome Back</h2>
                <p class="subtext">Login to manage your hospital appointments.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= clean($error) ?></div>
                <?php endforeach; ?>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>
                    <label for="identifier">Email Address</label>
                    <div class="input-wrap">
                        <input type="email" id="identifier" name="identifier" value="<?= clean($identifier) ?>" placeholder="Enter your email" required>
                    </div>

                    <label for="password">Password</label>
                    <div class="input-wrap password-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" aria-label="Show password">👁</button>
                    </div>

                    <div class="form-row">
                        <label class="checkbox-row">
                            <input type="checkbox" checked>
                            <span>Remember me</span>
                        </label>
                        <a class="text-link" href="#">Forgot Password?</a>
                    </div>

                    <button class="btn btn-primary login-btn" type="submit">Login</button>
                </form>

                <div class="alt-actions">
                    <p>Don't have an account? <a class="text-link" href="register/patient.php">Create Patient Account</a></p>
                    <a class="guest-link" href="#">Continue as Guest</a>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.querySelector('.toggle-password')?.addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            this.textContent = isHidden ? '🙈' : '👁';
        });
    </script>
</body>
=======
   <meta charset="UTF-8">
   <title>Hospital Appointment Management System</title>
   <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/auth_header.php'; ?>

<div class="auth-shell">
   <div class="auth-card">
       <div class="auth-intro">
           <span class="auth-badge">Hospital Appointment Management System</span>
           <h2>Welcome</h2>
           <p>Secure access to the appointment management system for patients and doctors</p>
           <ul class="auth-features">
               <li>Secure sign-in experience</li>
               <li>Quick access to appointments</li>
               <li>Simple onboarding process</li>
           </ul>
       </div>

       <div class="auth-form-panel">
           <div class="container">
               <h1>Login</h1>

                <?php foreach ($errors as $error): ?>
                   <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>

               <form method="POST" novalidate>
                    <?= csrf_field() ?>

                   <label for="identifier">Email or Unique Login ID</label>
                   <input type="text" id="identifier" name="identifier" 
                           maxlength="100" required value="<?= htmlspecialchars($identifier) ?>">
                    
                   <label for="password">Password</label>
                   <input type="password" id="password" name="password" 
                           maxlength="72" required>
                    
                   <button type="submit">Access Appointment System</button>
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
<body>
>>>>>>> 20f687558e2e16b57fad6ec44ec65fad6794a145
</html>
