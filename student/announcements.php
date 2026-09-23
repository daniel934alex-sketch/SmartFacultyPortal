<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole('STUDENT');

$username = $_SESSION['username'] ?? '';

if ($username === '') {
    die("Student session not found.");
}

/*
|--------------------------------------------------------------------------
| Get logged-in student's details
|--------------------------------------------------------------------------
*/

$studentSql = "
    SELECT
        s.student_name,
        s.section_id,
        sec.section_name
    FROM users u
    INNER JOIN students s
        ON s.user_id = u.user_id
    INNER JOIN sections sec
        ON sec.section_id = s.section_id
    WHERE u.username = ?
      AND u.role = 'STUDENT'
      AND u.status = 'ACTIVE'
    LIMIT 1
";

$studentStmt = $conn->prepare($studentSql);

if (!$studentStmt) {
    die("Student query preparation failed.");
}

$studentStmt->bind_param("s", $username);
$studentStmt->execute();

$studentResult = $studentStmt->get_result();

if ($studentResult->num_rows !== 1) {
    $studentStmt->close();
    die("Student record not found.");
}

$student = $studentResult->fetch_assoc();

$studentStmt->close();

$studentName = $student['student_name'];
$sectionName = $student['section_name'];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

/*
|--------------------------------------------------------------------------
| Get announcements
|--------------------------------------------------------------------------
*/

$announcementSql = "
    SELECT
        announcement_id,
        title,
        message,
        created_at
    FROM announcements
    ORDER BY created_at DESC
";

$announcementStmt = $conn->prepare($announcementSql);

if (!$announcementStmt) {
    die("Announcement query preparation failed.");
}

$announcementStmt->execute();

$announcementResult = $announcementStmt->get_result();

$announcements = [];

while ($row = $announcementResult->fetch_assoc()) {
    $announcements[] = $row;
}

$announcementStmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Announcements | Smart Faculty Portal
    </title>

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

            transition: 0.2s;
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

            transition: 0.2s;
        }

        .logout a:hover {
            background: #c82333;
        }

        /* MAIN */

        .main {
            margin-left: 240px;
            min-height: 100vh;
        }

        /* TOPBAR */

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

        /* CONTENT */

        .content {
            padding: 30px;
        }

        .page-heading {
            margin-bottom: 20px;
        }

        .page-heading h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #666;
        }

        .student-summary {
            display: inline-flex;

            align-items: center;

            padding: 9px 15px;

            background: white;

            border-radius: 20px;

            margin-bottom: 25px;

            color: #555;

            font-size: 13px;
        }

        /* ANNOUNCEMENTS */

        .announcement-list {
            display: flex;

            flex-direction: column;

            gap: 18px;
        }

        .announcement-card {
            background: white;

            border-radius: 12px;

            padding: 24px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);

            border-left: 4px solid #252d4d;
        }

        .announcement-title {
            font-size: 19px;

            font-weight: bold;

            margin-bottom: 10px;
        }

        .announcement-message {
            color: #555;

            font-size: 14px;

            line-height: 1.6;

            white-space: pre-line;

            margin-bottom: 15px;
        }

        .announcement-date {
            font-size: 12px;

            color: #888;
        }

        /* EMPTY STATE */

        .empty-state {
            background: white;

            padding: 60px 20px;

            border-radius: 12px;

            text-align: center;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .empty-icon {
            font-size: 45px;

            margin-bottom: 15px;
        }

        .empty-state h3 {
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #777;

            font-size: 14px;

            line-height: 1.5;
        }

        /* MOBILE */

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
                <a href="attendance.php">
                    📊 Attendance
                </a>
            </li>

            <li>
                <a
                    href="announcements.php"
                    class="active"
                >
                    📢 Announcements
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

            <h2>
                Announcements
            </h2>

            <div class="student-info">

                <div class="avatar">
                    <?= htmlspecialchars($firstLetter) ?>
                </div>

                <span>
                    <?= htmlspecialchars($username) ?>
                </span>

            </div>

        </header>


        <div class="content">

            <div class="page-heading">

                <h1>
                    📢 Announcements
                </h1>

                <p>
                    View the latest announcements from the portal.
                </p>

            </div>


            <div class="student-summary">

                👤 <?= htmlspecialchars($studentName) ?>

                &nbsp;·&nbsp;

                <?= htmlspecialchars($sectionName) ?>

            </div>


            <?php if (count($announcements) === 0): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📢
                    </div>

                    <h3>
                        No announcements yet
                    </h3>

                    <p>
                        New announcements will appear here
                        when they are published.
                    </p>

                </div>

            <?php else: ?>

                <div class="announcement-list">

                    <?php foreach ($announcements as $announcement): ?>

                        <div class="announcement-card">

                            <div class="announcement-title">

                                <?= htmlspecialchars(
                                    $announcement['title']
                                ) ?>

                            </div>

                            <div class="announcement-message">

                                <?= htmlspecialchars(
                                    $announcement['message']
                                ) ?>

                            </div>

                            <div class="announcement-date">

                                Posted:

                                <?= htmlspecialchars(
                                    date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            $announcement['created_at']
                                        )
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>