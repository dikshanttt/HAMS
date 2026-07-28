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
</html>
