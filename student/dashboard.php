<?php
date_default_timezone_set('Asia/Kolkata');

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
        u.user_id,
        u.username,
        u.status,
        s.student_id,
        s.register_number,
        s.student_name,
        s.email,
        s.phone,
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
    die("Student details not found.");
}

$student = $studentResult->fetch_assoc();

$studentStmt->close();


/*
|--------------------------------------------------------------------------
| Student variables
|--------------------------------------------------------------------------
*/

$studentId = (int) $student['student_id'];

$studentName = $student['student_name'];

$registerNumber = $student['register_number'];

$sectionId = (int) $student['section_id'];

$sectionName = $student['section_name'];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);


/*
|--------------------------------------------------------------------------
| Attendance summary
|--------------------------------------------------------------------------
*/

$attendanceSql = "
    SELECT
        COUNT(*) AS total_classes,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'PRESENT' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS present_classes
    FROM attendance
    WHERE student_id = ?
";

$attendanceStmt = $conn->prepare($attendanceSql);

if (!$attendanceStmt) {
    die("Attendance query preparation failed.");
}

$attendanceStmt->bind_param("i", $studentId);

$attendanceStmt->execute();

$attendanceResult = $attendanceStmt->get_result();

$attendanceData = $attendanceResult->fetch_assoc();

$attendanceStmt->close();


$totalAttendanceClasses =
    (int) ($attendanceData['total_classes'] ?? 0);

$presentAttendanceClasses =
    (int) ($attendanceData['present_classes'] ?? 0);


/*
|--------------------------------------------------------------------------
| Calculate attendance percentage
|--------------------------------------------------------------------------
*/

if ($totalAttendanceClasses > 0) {

    $attendancePercentage = round(
        ($presentAttendanceClasses / $totalAttendanceClasses) * 100,
        1
    );

    $attendanceDisplay =
        $attendancePercentage . '%';

} else {

    $attendancePercentage = null;

    $attendanceDisplay = 'N/A';
}


/*
|--------------------------------------------------------------------------
| Get today's day
|--------------------------------------------------------------------------
*/

$todayDay = strtoupper(
    date('l')
);


/*
|--------------------------------------------------------------------------
| Get today's timetable
|--------------------------------------------------------------------------
*/

$todaySql = "
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.status,

        p.period_number,
        p.start_time,
        p.end_time,

        sub.subject_code,
        sub.subject_name,

        f.faculty_name,

        c.room_number,
        c.room_type

    FROM timetable t

    INNER JOIN periods p
        ON p.period_id = t.period_id

    INNER JOIN subjects sub
        ON sub.subject_id = t.subject_id

    INNER JOIN faculty f
        ON f.faculty_id = t.faculty_id

    INNER JOIN classrooms c
        ON c.classroom_id = t.classroom_id

    WHERE t.section_id = ?
      AND t.day = ?
      AND t.status = 'PUBLISHED'

    ORDER BY p.period_number
";

$todayStmt = $conn->prepare($todaySql);

if (!$todayStmt) {
    die("Today's timetable query preparation failed.");
}

$todayStmt->bind_param(
    "is",
    $sectionId,
    $todayDay
);

$todayStmt->execute();

$todayResult = $todayStmt->get_result();


/*
|--------------------------------------------------------------------------
| Store today's classes
|--------------------------------------------------------------------------
*/

$todayClasses = [];

while ($row = $todayResult->fetch_assoc()) {

    $todayClasses[] = $row;
}

$todayStmt->close();


/*
|--------------------------------------------------------------------------
| Today's class count
|--------------------------------------------------------------------------
*/

$todayClassCount = count($todayClasses);


/*
|--------------------------------------------------------------------------
| Find next class
|--------------------------------------------------------------------------
|
| Compare current time with period start/end time.
|--------------------------------------------------------------------------
*/

$currentTime = date('H:i:s');

$nextClass = null;

foreach ($todayClasses as $class) {

    $startTime = $class['start_time'];

    $endTime = $class['end_time'];

    if ($currentTime < $endTime) {

        $nextClass = $class;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| Next class display
|--------------------------------------------------------------------------
*/

if ($nextClass !== null) {

    $nextClassPeriod =
        'P' . $nextClass['period_number'];

    $nextClassSubject =
        $nextClass['subject_code'];

} else {

    $nextClassPeriod = '—';

    $nextClassSubject = 'No more classes';

}


/*
|--------------------------------------------------------------------------
| Helper function for class status
|--------------------------------------------------------------------------
*/

function getClassStatus($startTime, $endTime)
{
    $currentTime = date('H:i:s');

    if ($currentTime >= $endTime) {

        return 'Completed';
    }

    if ($currentTime >= $startTime) {

        return 'Ongoing';
    }

    return 'Upcoming';
}

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
        Student Dashboard | Smart Faculty Portal
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        body {
            background: #f4f6f9;
            color: #222;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

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


        /* =========================================================
           MAIN
        ========================================================= */

        .main {
            margin-left: 240px;

            min-height: 100vh;
        }


        /* =========================================================
           TOP BAR
        ========================================================= */

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

            font-size: 14px;
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


        /* =========================================================
           CONTENT
        ========================================================= */

        .content {
            padding: 30px;
        }


        .welcome {
            margin-bottom: 25px;
        }


        .welcome h1 {
            font-size: 28px;

            margin-bottom: 8px;
        }


        .welcome p {
            color: #666;

            font-size: 15px;
        }


        /* =========================================================
           CARDS
        ========================================================= */

        .cards {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .card {
            background: white;

            padding: 22px;

            border-radius: 12px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
        }


        .card-title {
            color: #777;

            font-size: 14px;

            margin-bottom: 10px;
        }


        .card-value {
            font-size: 28px;

            font-weight: bold;
        }


        .next-subject {
            color: #666;

            font-size: 12px;

            margin-top: 6px;
        }


        /* =========================================================
           SECTION
        ========================================================= */

        .section {
            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);

            margin-bottom: 25px;
        }


        .section h2 {
            font-size: 20px;

            margin-bottom: 20px;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .table-container {
            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 800px;
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


        .period {
            font-weight: bold;
        }


        .subject-code {
            font-weight: bold;
        }


        .subject-name {
            color: #666;

            font-size: 12px;

            margin-top: 4px;
        }


        .room-type {
            color: #777;

            font-size: 11px;

            margin-top: 4px;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;
        }


        .status.completed {
            background: #e8f5e9;

            color: #2e7d32;
        }


        .status.ongoing {
            background: #fff3cd;

            color: #856404;
        }


        .status.upcoming {
            background: #e3f2fd;

            color: #1565c0;
        }


        /* =========================================================
           NO CLASSES
        ========================================================= */

        .no-classes {
            color: #777;

            font-size: 14px;

            padding: 10px 0;
        }


        /* =========================================================
           QUICK LINKS
        ========================================================= */

        .quick-links {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;
        }


        .quick-link {
            padding: 18px;

            border: 1px solid #eee;

            border-radius: 10px;

            text-decoration: none;

            color: #222;

            transition: 0.2s;
        }


        .quick-link:hover {
            background: #f7f8fa;

            transform: translateY(-2px);
        }


        .quick-link strong {
            display: block;

            margin-bottom: 6px;
        }


        .quick-link span {
            color: #777;

            font-size: 13px;
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 1100px) {

            .cards {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 900px) {

            .sidebar {
                width: 200px;
            }


            .main {
                margin-left: 200px;
            }


            .quick-links {
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


            .cards {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


    <!-- =========================================================
         SIDEBAR
    ========================================================= -->

    <aside class="sidebar">


        <div class="logo">

            Smart Faculty Portal

        </div>


        <ul class="menu">


            <li>

                <a
                    href="dashboard.php"
                    class="active"
                >

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

                <a href="announcements.php">

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



    <!-- =========================================================
         MAIN
    ========================================================= -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">


            <h2>

                Student Dashboard

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



        <!-- =====================================================
             CONTENT
        ====================================================== -->

        <div class="content">


            <!-- WELCOME -->

            <div class="welcome">


                <h1>

                    Welcome,

                    <?= htmlspecialchars($studentName) ?>

                    👋

                </h1>


                <p>

                    <?= htmlspecialchars($sectionName) ?>

                    &nbsp;·&nbsp;

                    Register No:

                    <?= htmlspecialchars($registerNumber) ?>

                </p>


            </div>



            <!-- =================================================
                 SUMMARY CARDS
            ================================================== -->

            <div class="cards">


                <!-- SECTION -->

                <div class="card">

                    <div class="card-title">

                        My Section

                    </div>


                    <div class="card-value">

                        <?= htmlspecialchars($sectionName) ?>

                    </div>

                </div>



                <!-- TODAY'S CLASSES -->

                <div class="card">

                    <div class="card-title">

                        Today's Classes

                    </div>


                    <div class="card-value">

                        <?= $todayClassCount ?>

                    </div>

                </div>



                <!-- ATTENDANCE -->

                <div class="card">

                    <div class="card-title">

                        Attendance

                    </div>


                    <div class="card-value">

                        <?= htmlspecialchars($attendanceDisplay) ?>

                    </div>

                </div>



                <!-- NEXT CLASS -->

                <div class="card">

                    <div class="card-title">

                        Next Class

                    </div>


                    <div class="card-value">

                        <?= htmlspecialchars($nextClassPeriod) ?>

                    </div>


                    <div class="next-subject">

                        <?= htmlspecialchars($nextClassSubject) ?>

                    </div>

                </div>


            </div>



            <!-- =================================================
                 TODAY'S CLASSES
            ================================================== -->

            <div class="section">


                <h2>

                    📅 Today's Classes

                </h2>


                <?php if (count($todayClasses) > 0): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

                                    <th>

                                        Period

                                    </th>

                                    <th>

                                        Time

                                    </th>

                                    <th>

                                        Subject

                                    </th>

                                    <th>

                                        Faculty

                                    </th>

                                    <th>

                                        Room

                                    </th>

                                    <th>

                                        Status

                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($todayClasses as $class): ?>


                                    <?php

                                    $classStatus = getClassStatus(
                                        $class['start_time'],
                                        $class['end_time']
                                    );

                                    $statusClass =
                                        strtolower($classStatus);

                                    ?>


                                    <tr>


                                        <!-- PERIOD -->

                                        <td class="period">

                                            P<?= htmlspecialchars(
                                                $class['period_number']
                                            ) ?>

                                        </td>



                                        <!-- TIME -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $class['start_time']
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                $class['end_time']
                                            ) ?>

                                        </td>



                                        <!-- SUBJECT -->

                                        <td>

                                            <div class="subject-code">

                                                <?= htmlspecialchars(
                                                    $class['subject_code']
                                                ) ?>

                                            </div>


                                            <div class="subject-name">

                                                <?= htmlspecialchars(
                                                    $class['subject_name']
                                                ) ?>

                                            </div>

                                        </td>



                                        <!-- FACULTY -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $class['faculty_name']
                                            ) ?>

                                        </td>



                                        <!-- ROOM -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $class['room_number']
                                            ) ?>


                                            <div class="room-type">

                                                <?= htmlspecialchars(
                                                    $class['room_type']
                                                ) ?>

                                            </div>

                                        </td>



                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status <?= htmlspecialchars(
                                                    $statusClass
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $classStatus
                                                ) ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <p class="no-classes">

                        No classes scheduled for today.

                    </p>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 QUICK ACCESS
            ================================================== -->

            <div class="section">


                <h2>

                    Quick Access

                </h2>


                <div class="quick-links">


                    <a
                        href="timetable.php"
                        class="quick-link"
                    >

                        <strong>

                            📅 View Timetable

                        </strong>


                        <span>

                            Check your daily and weekly timetable.

                        </span>

                    </a>



                    <a
                        href="attendance.php"
                        class="quick-link"
                    >

                        <strong>

                            📊 View Attendance

                        </strong>


                        <span>

                            Check your subject-wise attendance.

                        </span>

                    </a>



                    <a
                        href="announcements.php"
                        class="quick-link"
                    >

                        <strong>

                            📢 Announcements

                        </strong>


                        <span>

                            View the latest portal announcements.

                        </span>

                    </a>


                </div>


            </div>


        </div>


    </main>


</body>

</html><script>
    // Automatically refresh dashboard every 60 seconds
    setInterval(function () {
        location.reload();
    }, 60000);
</script>