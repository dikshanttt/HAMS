<?php
$auth_home = strpos($_SERVER['PHP_SELF'] ?? '', '/register/') === false ? 'index.php' : '../index.php';
$auth_login = strpos($_SERVER['PHP_SELF'] ?? '', '/register/') === false ? 'login.php' : '../login.php';
?>
<header class="site-header">
    <a class="brand" href="<?= $auth_home ?>">NovaCare Hospital</a>
    <nav class="site-nav" aria-label="Auth navigation">
        <a href="<?= $auth_home ?>">Home</a>
        <a href="<?= $auth_login ?>">Login</a>
        <a href="#">About</a>
        <a href="#">Contact</a>
    </nav>
</header>
