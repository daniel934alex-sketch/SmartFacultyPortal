<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('STUDENT');

$username = $_SESSION['username'] ?? 'Student';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Profile | Smart Faculty Portal</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #222;
        }

        /* SIDEBAR */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: #151a2d;
            padding: 25px 15px;
        }

        .logo {
            color: white;
            font-size: 21px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 35px;
        }

        .menu {
            list-style: none;
        }

        .menu li {
            margin-bottom: 8px;
        }

        .menu a {
            display: block;
            padding: 13px 15px;
            color: #d7d9e0;
            text-decoration: none;
            border-radius: 8px;
        }

        .menu a:hover,
        .menu a.active {
            background: #252d4d;
            color: white;
        }

        .logout {
            position: absolute;
            bottom: 25px;
            left: 15px;
            right: 15px;
        }

        .logout a {
            display: block;
            text-align: center;
            padding: 12px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        /* MAIN */

        .main {
            margin-left: 240px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            border-bottom: 1px solid #ddd;
        }

        .topbar h2 {
            font-size: 22px;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #151a2d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .content {
            padding: 30px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #666;
        }

        /* PROFILE */

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            max-width: 900px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }

        .large-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #151a2d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
        }

        .profile-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .profile-header p {
            color: #777;
        }

        /* DETAILS */

        .details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-box {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 18px;
        }

        .detail-label {
            font-size: 13px;
            color: #777;
            margin-bottom: 7px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
        }

        .privacy-note {
            margin-top: 25px;
            padding: 15px;
            background: #f7f8fa;
            border-radius: 8px;
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        /* MOBILE */

        @media (max-width: 700px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
            }

            .logout {
                position: static;
                margin-top: 20px;
            }

            .topbar {
                padding: 0 15px;
            }

            .content {
                padding: 15px;
            }

            .details {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">
            Smart Faculty Portal
        </div>

        <ul class="menu">

            <li>
                <a href="dashboard.php">
                    🏠 Dashboard
                </a>
            </li>

            <li>
                <a href="timetable.php">
                    📅 Timetable
                </a>
            </li>

            <li>
                <a href="attendance.php">
                    📊 Attendance
                </a>
            </li>

            <li>
                <a href="profile.php" class="active">
                    👤 Profile
                </a>
            </li>

        </ul>

        <div class="logout">

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <h2>Student Profile</h2>

            <div class="student-info">

                <div class="avatar">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>

                <span>
                    <?= htmlspecialchars($username) ?>
                </span>

            </div>

        </header>


        <div class="content">

            <div class="page-heading">

                <h1>👤 My Profile</h1>

                <p>
                    View your student information.
                </p>

            </div>


            <div class="profile-card">

                <div class="profile-header">

                    <div class="large-avatar">
                        <?= strtoupper(substr($username, 0, 1)) ?>
                    </div>

                    <div>

                        <h2>
                            Aravind S
                        </h2>

                        <p>
                            Student · CSE-A
                        </p>

                    </div>

                </div>


                <div class="details">

                    <div class="detail-box">

                        <div class="detail-label">
                            Username
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($username) ?>
                        </div>

                    </div>


                    <div class="detail-box">

                        <div class="detail-label">
                            Register Number
                        </div>

                        <div class="detail-value">
                            714025104022
                        </div>

                    </div>


                    <div class="detail-box">

                        <div class="detail-label">
                            Student Name
                        </div>

                        <div class="detail-value">
                            Aravind S
                        </div>

                    </div>


                    <div class="detail-box">

                        <div class="detail-label">
                            Section
                        </div>

                        <div class="detail-value">
                            CSE-A
                        </div>

                    </div>


                    <div class="detail-box">

                        <div class="detail-label">
                            Email
                        </div>

                        <div class="detail-value">
                            aravind@example.com
                        </div>

                    </div>


                    <div class="detail-box">

                        <div class="detail-label">
                            Phone
                        </div>

                        <div class="detail-value">
                            9876543210
                        </div>

                    </div>

                </div>


                <div class="privacy-note">

                    🔒 <strong>Privacy:</strong>
                    Your portal displays your own academic information.
                    Faculty absence details, handover requests, alternate faculty
                    information and other internal faculty information are not
                    displayed to students.

                </div>

            </div>

        </div>

    </main>

</body>

</html>