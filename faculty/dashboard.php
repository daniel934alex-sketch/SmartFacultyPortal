<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('FACULTY');

?>

<!DOCTYPE html>

<html>

<head>

    <title>Faculty Dashboard</title>

</head>

<body>

    <h1>Faculty Dashboard</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION['username']) ?>
    </p>

    <p>
        Faculty login successful.
    </p>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>