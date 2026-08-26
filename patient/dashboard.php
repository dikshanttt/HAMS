<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(['patient']);
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
        <h1>Patient Dashboard</h1>
        <p>Dashboard content will be built here later.</p>
    </div>
</body>
</html>
