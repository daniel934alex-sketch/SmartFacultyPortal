<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('FACULTY');
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Dashboard</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        .dashboard {
            max-width: 900px;
            margin: 40px auto;
            text-align: center;
        }

        .welcome {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .welcome h1 {
            margin-bottom: 10px;
        }

        .welcome p {
            color: #555;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .menu a {
            display: block;
            padding: 25px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            transition: 0.2s;
        }

        .menu a:hover {
            background: #084298;
            transform: translateY(-2px);
        }

        .logout {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .logout:hover {
            background: #bb2d3b;
        }

        @media (max-width: 600px) {

            .menu {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="dashboard">

    <div class="welcome">

        <h1>Faculty Dashboard</h1>

        <h2>
            Welcome,
            <?= htmlspecialchars($_SESSION['username']) ?>
        </h2>

        <p>
            Smart Faculty Assistant Portal
        </p>

        <p>
            Faculty login successful.
        </p>

    </div>


    <div class="menu">

        <!-- Timetable -->

        <a href="timetable.php">
            📅 My Timetable
        </a>


        <!-- Notifications -->

        <a href="notifications.php">
            🔔 Notifications
        </a>


        <!-- Profile -->

        <a href="profile.php">
            👤 My Profile
        </a>


        <!-- Availability -->

        <a href="availability.php">
            📢 Faculty Availability
        </a>

    </div>


    <!-- Logout -->

    <a class="logout" href="../auth/logout.php">
        🚪 Logout
    </a>

</div>

</body>

</html>