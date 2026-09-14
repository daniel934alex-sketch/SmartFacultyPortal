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

    <title>Attendance | Smart Faculty Portal</title>

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

        /* SUMMARY */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .summary-title {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 28px;
            font-weight: bold;
        }

        /* ATTENDANCE TABLE */

        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f7f8fa;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        .percentage {
            font-weight: bold;
        }

        .progress-bar {
            width: 140px;
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: #28a745;
            border-radius: 10px;
        }

        .good {
            color: #198754;
            font-weight: bold;
        }

        .warning {
            color: #dc3545;
            font-weight: bold;
        }

        .note {
            color: #777;
            font-size: 13px;
            margin-top: 15px;
        }

        /* MOBILE */

        @media (max-width: 850px) {

            .summary {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

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
                <a href="attendance.php" class="active">
                    📊 Attendance
                </a>
            </li>

            <li>
                <a href="profile.php">
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

            <h2>Attendance</h2>

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

                <h1>📊 My Attendance</h1>

                <p>
                    View your subject-wise attendance summary.
                </p>

            </div>


            <!-- SUMMARY CARDS -->

            <div class="summary">

                <div class="summary-card">

                    <div class="summary-title">
                        Overall Attendance
                    </div>

                    <div class="summary-value">
                        90%
                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-title">
                        Classes Attended
                    </div>

                    <div class="summary-value">
                        45
                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-title">
                        Total Classes
                    </div>

                    <div class="summary-value">
                        50
                    </div>

                </div>

            </div>


            <!-- SUBJECT ATTENDANCE -->

            <div class="section">

                <h2>
                    Subject-wise Attendance
                </h2>

                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>Subject</th>
                                <th>Total Classes</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Percentage</th>
                                <th>Progress</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td>DBMS</td>

                                <td>20</td>

                                <td>19</td>

                                <td>1</td>

                                <td class="percentage good">
                                    95%
                                </td>

                                <td>

                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: 95%;">
                                        </div>

                                    </div>

                                </td>

                            </tr>


                            <tr>

                                <td>Advanced DSA</td>

                                <td>18</td>

                                <td>16</td>

                                <td>2</td>

                                <td class="percentage good">
                                    89%
                                </td>

                                <td>

                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: 89%;">
                                        </div>

                                    </div>

                                </td>

                            </tr>


                            <tr>

                                <td>KVD</td>

                                <td>18</td>

                                <td>16</td>

                                <td>2</td>

                                <td class="percentage good">
                                    89%
                                </td>

                                <td>

                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: 89%;">
                                        </div>

                                    </div>

                                </td>

                            </tr>


                            <tr>

                                <td>Operating Systems</td>

                                <td>19</td>

                                <td>16</td>

                                <td>3</td>

                                <td class="percentage good">
                                    84%
                                </td>

                                <td>

                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: 84%;">
                                        </div>

                                    </div>

                                </td>

                            </tr>


                            <tr>

                                <td>Mathematics</td>

                                <td>20</td>

                                <td>15</td>

                                <td>5</td>

                                <td class="percentage warning">
                                    75%
                                </td>

                                <td>

                                    <div class="progress-bar">

                                        <div
                                            class="progress"
                                            style="width: 75%;">
                                        </div>

                                    </div>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

                <p class="note">
                    Attendance percentages shown above are sample values for UI testing.
                    They will be replaced with actual database values.
                </p>

            </div>

        </div>

    </main>

</body>

</html>