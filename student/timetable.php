<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole('STUDENT');

$username = $_SESSION['username'] ?? '';

if ($username === '') {
    die("Student session not found.");
}


/* =========================================================
   GET STUDENT DETAILS
========================================================= */

$studentSql = "
    SELECT
        u.username,
        s.student_id,
        s.student_name,
        s.register_number,
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

$studentName    = $student['student_name'];
$registerNumber = $student['register_number'];
$sectionId      = (int)$student['section_id'];
$sectionName    = $student['section_name'];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);


/* =========================================================
   DAYS
========================================================= */

$days = [
    'MONDAY',
    'TUESDAY',
    'WEDNESDAY',
    'THURSDAY',
    'FRIDAY',
    'SATURDAY'
];


/* =========================================================
   GET ALL PERIODS
========================================================= */

$periodSql = "
    SELECT
        period_id,
        period_number,
        start_time,
        end_time
    FROM periods
    ORDER BY period_number
";

$periodStmt = $conn->prepare($periodSql);

if (!$periodStmt) {
    die("Period query preparation failed.");
}

$periodStmt->execute();

$periodResult = $periodStmt->get_result();

$periods = [];

while ($periodRow = $periodResult->fetch_assoc()) {

    $periodNumber = (int)$periodRow['period_number'];

    $periods[$periodNumber] = $periodRow;
}

$periodStmt->close();


/* =========================================================
   GET PUBLISHED TIMETABLE
========================================================= */

$timetableSql = "
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
      AND t.status = 'PUBLISHED'

    ORDER BY
        FIELD(
            t.day,
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),
        p.period_number
";

$timetableStmt = $conn->prepare($timetableSql);

if (!$timetableStmt) {
    die("Timetable query preparation failed.");
}

$timetableStmt->bind_param("i", $sectionId);

$timetableStmt->execute();

$timetableResult = $timetableStmt->get_result();


/* =========================================================
   STORE TIMETABLE BY DAY + PERIOD
========================================================= */

$timetable = [];

foreach ($days as $day) {
    $timetable[$day] = [];
}

while ($row = $timetableResult->fetch_assoc()) {

    $day = strtoupper($row['day']);

    $periodNumber = (int)$row['period_number'];

    if (isset($timetable[$day])) {

        $timetable[$day][$periodNumber] = $row;
    }
}

$timetableStmt->close();


/* =========================================================
   CHECK TIMETABLE
========================================================= */

$hasTimetable = false;

foreach ($timetable as $dayRows) {

    if (count($dayRows) > 0) {

        $hasTimetable = true;

        break;
    }
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
    Student Timetable | Smart Faculty Portal
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


/* =========================================================
   BODY
========================================================= */

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


/* =========================================================
   LOGOUT
========================================================= */

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


/* =========================================================
   STUDENT SUMMARY
========================================================= */

.student-summary {

    display: inline-flex;

    align-items: center;

    padding: 9px 15px;

    background: white;

    border-radius: 20px;

    margin-bottom: 25px;

    color: #555;

    font-size: 13px;

    box-shadow:
        0 2px 6px rgba(0,0,0,0.04);
}


/* =========================================================
   DAY CARD
========================================================= */

.day-card {

    background: white;

    border-radius: 12px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
}


.day-title {

    font-size: 20px;

    font-weight: bold;

    margin-bottom: 20px;
}


/* =========================================================
   TABLE CONTAINER
========================================================= */

.table-container {

    width: 100%;

    overflow-x: auto;
}


/* =========================================================
   TABLE
========================================================= */

table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

    min-width: 900px;
}


/* =========================================================
   COLUMN WIDTHS
========================================================= */

th:nth-child(1),
td:nth-child(1) {

    width: 9%;
}


th:nth-child(2),
td:nth-child(2) {

    width: 18%;
}


th:nth-child(3),
td:nth-child(3) {

    width: 30%;
}


th:nth-child(4),
td:nth-child(4) {

    width: 25%;
}


th:nth-child(5),
td:nth-child(5) {

    width: 18%;
}


/* =========================================================
   TABLE CELLS
========================================================= */

th,
td {

    padding: 14px 12px;

    text-align: left;

    border-bottom: 1px solid #eee;

    vertical-align: middle;
}


th {

    background: #f7f8fa;

    font-size: 14px;

    font-weight: 600;

    color: #222;
}


td {

    font-size: 14px;

    color: #222;
}


/* =========================================================
   PERIOD
========================================================= */

.period {

    font-weight: bold;

    white-space: nowrap;
}


/* =========================================================
   TIME
========================================================= */

.time {

    white-space: nowrap;

    font-size: 13px;
}


/* =========================================================
   SUBJECT
========================================================= */

.subject-code {

    font-weight: bold;

    font-size: 14px;

    line-height: 1.3;
}


.subject-name {

    color: #666;

    font-size: 12px;

    margin-top: 4px;

    line-height: 1.3;
}


/* =========================================================
   FACULTY
========================================================= */

.faculty {

    white-space: nowrap;
}


/* =========================================================
   ROOM
========================================================= */

.room {

    white-space: nowrap;

    font-weight: 500;
}


.room-type {

    color: #777;

    font-size: 11px;

    margin-top: 4px;

    text-transform: uppercase;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    background: white;

    padding: 60px 20px;

    border-radius: 12px;

    text-align: center;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.06);
}


.empty-icon {

    font-size: 45px;

    margin-bottom: 15px;
}


.empty-state h3 {

    margin-bottom: 8px;

    font-size: 19px;
}


.empty-state p {

    color: #777;

    font-size: 14px;

    line-height: 1.5;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 800px) {

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


    .day-card {

        padding: 15px;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

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

            <a
                href="timetable.php"
                class="active"
            >

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



<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">


    <!-- TOP BAR -->

    <header class="topbar">


        <h2>

            Student Timetable

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



    <!-- =================================================
         CONTENT
    ================================================== -->

    <div class="content">


        <div class="page-heading">


            <h1>

                📅 Weekly Timetable

            </h1>


            <p>

                View the published timetable for your section.

            </p>


        </div>



        <!-- STUDENT INFO -->

        <div class="student-summary">

            👤

            <?= htmlspecialchars($studentName) ?>

            &nbsp;·&nbsp;

            <?= htmlspecialchars($sectionName) ?>

            &nbsp;·&nbsp;

            Register No:

            <?= htmlspecialchars($registerNumber) ?>

        </div>



        <?php if (!$hasTimetable): ?>


            <!-- EMPTY STATE -->

            <div class="empty-state">


                <div class="empty-icon">

                    📅

                </div>


                <h3>

                    Timetable not published yet

                </h3>


                <p>

                    The timetable for

                    <strong>

                        <?= htmlspecialchars($sectionName) ?>

                    </strong>

                    will appear here once it is published
                    by the administrator.

                </p>


            </div>


        <?php else: ?>


            <!-- =================================================
                 ALL DAYS
            ================================================== -->

            <?php foreach ($days as $day): ?>


                <div class="day-card">


                    <div class="day-title">

                        <?= htmlspecialchars($day) ?>

                    </div>


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
                                        Regular Faculty
                                    </th>

                                    <th>
                                        Room
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php

                                /*
                                -----------------------------------------
                                ALWAYS SHOW P1 TO P9
                                -----------------------------------------
                                */

                                for (
                                    $periodNumber = 1;
                                    $periodNumber <= 9;
                                    $periodNumber++
                                ):

                                    $row =
                                        $timetable[$day][$periodNumber]
                                        ?? null;


                                    $periodInfo =
                                        $periods[$periodNumber]
                                        ?? null;


                                    $startTime =
                                        $row['start_time']
                                        ?? ($periodInfo['start_time'] ?? '-');


                                    $endTime =
                                        $row['end_time']
                                        ?? ($periodInfo['end_time'] ?? '-');

                                ?>


                                    <tr>


                                        <!-- PERIOD -->

                                        <td class="period">

                                            P<?= $periodNumber ?>

                                        </td>


                                        <!-- TIME -->

                                        <td class="time">

                                            <?= htmlspecialchars($startTime) ?>

                                            -

                                            <?= htmlspecialchars($endTime) ?>

                                        </td>



                                        <?php if ($row): ?>


                                            <!-- SUBJECT -->

                                            <td>

                                                <div class="subject-code">

                                                    <?= htmlspecialchars(
                                                        $row['subject_code']
                                                    ) ?>

                                                </div>


                                                <div class="subject-name">

                                                    <?= htmlspecialchars(
                                                        $row['subject_name']
                                                    ) ?>

                                                </div>

                                            </td>


                                            <!-- FACULTY -->

                                            <td class="faculty">

                                                <?= htmlspecialchars(
                                                    $row['faculty_name']
                                                ) ?>

                                            </td>


                                            <!-- ROOM -->

                                            <td class="room">

                                                <?= htmlspecialchars(
                                                    $row['room_number']
                                                ) ?>


                                                <div class="room-type">

                                                    <?= htmlspecialchars(
                                                        $row['room_type']
                                                    ) ?>

                                                </div>

                                            </td>


                                        <?php else: ?>


                                            <!--
                                            Normally this should never happen
                                            because our database has 270 rows.
                                            -->

                                            <td>
                                                —
                                            </td>

                                            <td>
                                                —
                                            </td>

                                            <td>
                                                —
                                            </td>


                                        <?php endif; ?>


                                    </tr>


                                <?php endfor; ?>


                            </tbody>


                        </table>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>


</main>


</body>

</html>