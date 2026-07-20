<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

require_login(['patient']);

$db = getDB();
$stmt = $db->prepare('SELECT name FROM patient_profiles WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$patient = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <strong>HMS Patient</strong>
        <div><a href="../logout.php">Logout</a></div>
    </div>
    <div class="container wide">
        <h1>Welcome, <?= htmlspecialchars($patient['name'] ?? '') ?></h1>
        <p>This is a placeholder — appointment booking, medical history, etc. come next.</p>
    </div>
</body>
</html>
