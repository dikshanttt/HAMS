<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

require_login(['doctor']);

$db = getDB();
$stmt = $db->prepare(
    'SELECT dp.name, u.doctor_login_id FROM doctor_profiles dp
     JOIN users u ON u.id = dp.user_id WHERE dp.user_id = ?'
);
$stmt->execute([current_user_id()]);
$doctor = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <strong>HMS Doctor</strong>
        <div><a href="../logout.php">Logout</a></div>
    </div>
    <div class="container wide">
        <h1>Welcome, Dr. <?= htmlspecialchars($doctor['name'] ?? '') ?></h1>
        <p>Login ID: <?= htmlspecialchars($doctor['doctor_login_id'] ?? '') ?></p>
        <p>This is a placeholder — appointment schedule, patient records, etc. come next.</p>
    </div>
</body>
</html>
