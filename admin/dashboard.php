<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login(['admin']);

$db = getDB();
$stmt = $db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'");
$pendingCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <strong>HMS Admin</strong>
        <div><a href="../logout.php">Logout</a></div>
    </div>
    <div class="container wide">
        <h1>Admin Dashboard</h1>
        <p>This is a placeholder — the full dashboard (patient/doctor management, reports, etc.) comes next.</p>
        <p><strong><?= (int)$pendingCount ?></strong> doctor registration(s) awaiting verification.</p>
        <a class="btn" href="verify_doctors.php">Review Pending Doctors</a>
    </div>
</body>
</html>
