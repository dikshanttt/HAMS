<?php
require_once __DIR__ . '/../includes/auth.php';
require_login(['doctor']);
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
        <h1>Doctor Dashboard</h1>
        <p>Dashboard content will be built here later.</p>
    </div>
</body>
</html>
