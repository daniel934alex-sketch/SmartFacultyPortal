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
| Get logged-in student's ID
|--------------------------------------------------------------------------
*/

$studentSql = "
    SELECT
        s.student_id,
        s.student_name,
        s.register_number,
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

$studentId = (int) $student['student_id'];
$studentName = $student['student_name'];
$sectionName = $student['section_name'];

/*
|--------------------------------------------------------------------------
| Get subject-wise attendance
|--------------------------------------------------------------------------
|
| attendance
|     ↓
| timetable
|     ↓
| subjects
|
|--------------------------------------------------------------------------
*/

$attendanceSql = "
    SELECT
        sub.subject_id,
        sub.subject_code,
        sub.subject_name,

        COUNT(a.attendance_id) AS total_classes,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'PRESENT' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS present_classes,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'ABSENT' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS absent_classes

    FROM attendance a

    INNER JOIN timetable t
        ON t.timetable_id = a.timetable_id

    INNER JOIN subjects sub
        ON sub.subject_id = t.subject_id

    WHERE a.student_id = ?

    GROUP BY
        sub.subject_id,
        sub.subject_code,
        sub.subject_name

    ORDER BY
        sub.subject_name ASC
";

$attendanceStmt = $conn->prepare($attendanceSql);

if (!$attendanceStmt) {
    die("Attendance query preparation failed.");
}

$attendanceStmt->bind_param("i", $studentId);
$attendanceStmt->execute();

$attendanceResult = $attendanceStmt->get_result();

$attendanceRows = [];

$totalClasses = 0;
$totalPresent = 0;
$totalAbsent = 0;

while ($row = $attendanceResult->fetch_assoc()) {

    $rowTotal = (int) $row['total_classes'];
    $rowPresent = (int) $row['present_classes'];
    $rowAbsent = (int) $row['absent_classes'];

    $rowPercentage = $rowTotal > 0
        ? round(($rowPresent / $rowTotal) * 100, 1)
        : 0;

    $row['percentage'] = $rowPercentage;

    $attendanceRows[] = $row;

    $totalClasses += $rowTotal;
    $totalPresent += $rowPresent;
    $totalAbsent += $rowAbsent;
}

$attendanceStmt->close();

/*
|--------------------------------------------------------------------------
| Overall attendance
|--------------------------------------------------------------------------
*/

$overallPercentage = $totalClasses > 0
    ? round(($totalPresent / $totalClasses) * 100, 1)
    : 0;

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

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
        Attendance | Smart Faculty Portal
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
           TOPBAR
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
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 28px;

            margin-bottom: 8px;
        }

        .page-heading p {
            color: #666;
        }

        /* =========================================================
           SUMMARY
        ========================================================= */

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

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
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

        /* =========================================================
           TABLE SECTION
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

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 700px;
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

        .good {
            color: #198754;
        }

        .warning {
            color: #dc3545;
        }

        /* =========================================================
           PROGRESS BAR
        ========================================================= */

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

            transition: width 0.3s ease;
        }

        /* =========================================================
           EMPTY STATE
        ========================================================= */

        .empty-state {
            text-align: center;

            padding: 45px 20px;

            color: #777;
        }

        .empty-state-icon {
            font-size: 40px;

            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #333;

            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;

            line-height: 1.5;
        }

        /* =========================================================
           STUDENT INFO
        ========================================================= */

        .student-summary {
            display: inline-flex;

            align-items: center;

            gap: 10px;

            padding: 8px 14px;

            background: #f7f8fa;

            border-radius: 20px;

            margin-bottom: 20px;

            font-size: 13px;

            color: #555;
        }

        /* =========================================================
           MOBILE
        ========================================================= */

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


    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">

        <div class="logo">
            Smart Faculty Portal
        </div>


    

           <ul class="menu">

    <li>
        <a href="dashboard.php">🏠 Dashboard</a>
    </li>

    <li>
        <a href="timetable.php">📅 Timetable</a>
    </li>

    <li>
        <a href="attendance.php" class="active">📊 Attendance</a>
    </li>

    <li>
        <a href="announcements.php">📢 Announcements</a>
    </li>

    <li>
        <a href="profile.php">👤 Profile</a>
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
    ========================================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">

            <h2>
                Attendance
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


            <!-- PAGE HEADING -->

            <div class="page-heading">

                <h1>
                    📊 My Attendance
                </h1>

                <p>
                    View your subject-wise attendance summary.
                </p>

            </div>


            <!-- STUDENT IDENTIFICATION -->

            <div class="student-summary">

                👤

                <?= htmlspecialchars($studentName) ?>

                ·

                <?= htmlspecialchars($sectionName) ?>

                ·

                Register No:

                <?= htmlspecialchars($student['register_number']) ?>

            </div>


            <!-- =================================================
                 SUMMARY CARDS
            ================================================== -->

            <div class="summary">


                <div class="summary-card">

                    <div class="summary-title">
                        Overall Attendance
                    </div>

                    <div class="summary-value">

                        <?php if ($totalClasses > 0): ?>

                            <?= htmlspecialchars($overallPercentage) ?>%

                        <?php else: ?>

                            N/A

                        <?php endif; ?>

                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-title">
                        Classes Attended
                    </div>

                    <div class="summary-value">

                        <?= htmlspecialchars($totalPresent) ?>

                    </div>

                </div>


                <div class="summary-card">

                    <div class="summary-title">
                        Total Classes
                    </div>

                    <div class="summary-value">

                        <?= htmlspecialchars($totalClasses) ?>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 SUBJECT-WISE ATTENDANCE
            ================================================== -->

            <div class="section">

                <h2>
                    Subject-wise Attendance
                </h2>


                <?php if (count($attendanceRows) === 0): ?>

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            📋
                        </div>

                        <h3>
                            No attendance records yet
                        </h3>

                        <p>
                            Attendance records for your classes
                            will appear here once faculty members
                            start marking attendance.
                        </p>

                    </div>


                <?php else: ?>


                    <div class="table-container">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Total Classes
                                    </th>

                                    <th>
                                        Present
                                    </th>

                                    <th>
                                        Absent
                                    </th>

                                    <th>
                                        Percentage
                                    </th>

                                    <th>
                                        Progress
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($attendanceRows as $row): ?>

                                    <?php

                                    $percentage =
                                        (float) $row['percentage'];

                                    $percentageClass =
                                        $percentage >= 75
                                            ? 'good'
                                            : 'warning';

                                    ?>


                                    <tr>

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $row['subject_code']
                                                ) ?>
                                            </strong>

                                            <br>

                                            <small>

                                                <?= htmlspecialchars(
                                                    $row['subject_name']
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $row['total_classes']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $row['present_classes']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $row['absent_classes']
                                            ) ?>

                                        </td>


                                        <td
                                            class="percentage
                                            <?= htmlspecialchars(
                                                $percentageClass
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $percentage
                                            ) ?>%

                                        </td>


                                        <td>

                                            <div class="progress-bar">

                                                <div
                                                    class="progress"
                                                    style="
                                                        width:
                                                        <?= min(
                                                            100,
                                                            max(
                                                                0,
                                                                $percentage
                                                            )
                                                        ) ?>%;
                                                    "
                                                >
                                                </div>

                                            </div>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>


            </div>


        </div>

    </main>


</body>

</html>