<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('STUDENT');

?>

<!DOCTYPE html>

<html>

<head>

    <title>Student Dashboard</title>

</head>

<body>

    <h1>Student Dashboard</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION['username']) ?>
    </p>

    <p>
        Student login successful.
    </p>

    <a href="../auth/logout.php">
        Logout
    </a>

</body>

</html>