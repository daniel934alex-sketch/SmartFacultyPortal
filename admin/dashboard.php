<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

?>

<!DOCTYPE html>

<html>

<head>

    <title>HOD Dashboard</title>

</head>

<body>

    <h1>HOD / Admin Dashboard</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION['username']) ?>
    </p>

    <p>
        You are logged in as HOD.
    </p>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>