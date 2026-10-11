
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "My Attendance";

/* ==========================================
   SAFE OUTPUT
   ========================================== */

function attendanceEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ==========================================
   LOAD LOGGED-IN STUDENT
   ========================================== */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

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

$studentId = (int)$student["student_id"];
$studentName = $student["student_name"];
$registerNumber = $student["register_number"];
$sectionName = $student["section_name"];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

/* ==========================================
   SUBJECT-WISE ATTENDANCE
   ========================================== */

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

    ORDER BY sub.subject_name ASC
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

foreach ($attendanceResult as $row) {

    $rowTotal = (int)$row["total_classes"];
    $rowPresent = (int)$row["present_classes"];
    $rowAbsent = (int)$row["absent_classes"];

    $row["total_classes"] = $rowTotal;
    $row["present_classes"] = $rowPresent;
    $row["absent_classes"] = $rowAbsent;

    $row["percentage"] = $rowTotal > 0
        ? round(($rowPresent / $rowTotal) * 100, 1)
        : 0;

    $attendanceRows[] = $row;

    $totalClasses += $rowTotal;
    $totalPresent += $rowPresent;
    $totalAbsent += $rowAbsent;
}

$attendanceStmt->close();

/* ==========================================
   OVERALL ATTENDANCE
   ========================================== */

$overallPercentage = $totalClasses > 0
    ? round(($totalPresent / $totalClasses) * 100, 1)
    : 0;

$attendanceDisplay = $totalClasses > 0
    ? $overallPercentage . "%"
    : "N/A";

$overallStatus = $totalClasses === 0
    ? "No records yet"
    : ($overallPercentage >= 75
        ? "On Track"
        : "Below 75%");

$overallStatusClass = $totalClasses === 0
    ? "neutral"
    : ($overallPercentage >= 75
        ? "good"
        : "warning");

$subjectCount = count($attendanceRows);

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
        My Attendance | SmartFacultyPortal
    </title>

    <style>
    /* ==========================================
       STUDENT ATTENDANCE
       PREMIUM NAVY + VIOLET THEME
       SELF-CONTAINED CSS
       ========================================== */

    :root {
        --bg: #0c1424;
        --sidebar: #101a2d;
        --surface: #172338;
        --surface-alt: #1c2a42;
        --border: #2b3a54;
        --text: #eef2fc;
        --muted: #9eacc6;
        --violet: #aa94ff;
        --violet-light: #d4c8ff;
        --green: #75dec1;
        --red: #ffacbc;
    }

    * {
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
        min-height: 100vh;
        background: var(--bg);
        color: var(--text);
        font-family: Arial, Helvetica, sans-serif;
        line-height: 1.5;
    }

    a {
        color: inherit;
    }

    /* ==========================================
       SIDEBAR - MATCHES STUDENT DASHBOARD
       ========================================== */

    .student-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;

        width: 255px;
        padding: 28px 17px;

        display: flex;
        flex-direction: column;

        border-right: 1px solid var(--border);
        background: var(--sidebar);

        z-index: 50;
    }

    .student-brand {
        display: flex;
        align-items: center;
        gap: 12px;

        padding: 4px 8px 29px;
        border-bottom: 1px solid var(--border);
    }

    .student-brand-icon {
        width: 44px;
        height: 44px;
        flex-shrink: 0;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 13px;

        background: linear-gradient(
            135deg,
            #7461c9,
            #b59dff
        );

        color: #15112c;
        font-size: 23px;
        font-weight: 800;
    }

    .student-brand-name {
        color: #ffffff;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.3;
    }

    .student-brand-caption {
        margin-top: 4px;
        color: var(--muted);
        font-size: 11px;
    }

    .student-nav-title {
        margin: 29px 12px 13px;

        color: #8393b2;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.7px;
        text-transform: uppercase;
    }

    .student-menu {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .student-menu a {
        display: flex;
        align-items: center;
        gap: 12px;

        min-height: 46px;
        padding: 12px 15px;

        border: 1px solid transparent;
        border-radius: 11px;

        color: #b5c2da;
        text-decoration: none;
        font-size: 13px;
        font-weight: 650;

        transition:
            background .2s,
            color .2s;
    }

    .student-menu a:hover {
        background: #24324c;
        color: #ffffff;
    }

    .student-menu a.active {
        border-color: #584a8c;
        background: #302a55;
        color: #e5dcff;
    }

    .student-menu-icon {
        width: 24px;
        text-align: center;
        font-size: 17px;
    }

    .student-sidebar-footer {
        margin-top: auto;
        padding-top: 25px;
    }

    .student-sidebar-profile {
        padding: 14px;
        margin-bottom: 13px;

        border: 1px solid var(--border);
        border-radius: 12px;

        background: #18263c;
    }

    .student-sidebar-profile strong {
        display: block;
        color: #ffffff;
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .student-sidebar-profile span {
        display: block;
        margin-top: 5px;
        color: var(--muted);
        font-size: 11px;
    }

    .student-logout {
        display: block;
        padding: 12px 15px;

        border: 1px solid #80465a;
        border-radius: 10px;

        background: #402633;
        color: #ffd2dc;

        text-align: center;
        text-decoration: none;
        font-size: 13px;
        font-weight: 750;
    }

    .student-logout:hover {
        background: #57303f;
    }

    /* ==========================================
       MAIN LAYOUT
       ========================================== */

    .student-main {
        min-width: 0;
        min-height: 100vh;
        margin-left: 255px;
    }

    .student-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;

        min-height: 78px;
        padding: 17px 34px;

        border-bottom: 1px solid var(--border);
        background: #111c2f;
    }

    .student-topbar-title {
        margin: 0;
        color: #ffffff;
        font-size: 19px;
        font-weight: 800;
    }

    .student-topbar-subtitle {
        display: block;
        margin-top: 4px;
        color: var(--muted);
        font-size: 12px;
    }

    .student-account {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .student-account-name {
        color: #ffffff;
        font-size: 13px;
        font-weight: 750;
        text-align: right;
    }

    .student-account-role {
        margin-top: 3px;
        color: var(--muted);
        font-size: 11px;
        text-align: right;
    }

    .student-avatar {
        display: flex;
        align-items: center;
        justify-content: center;

        width: 43px;
        height: 43px;
        flex-shrink: 0;

        border: 1px solid #7364aa;
        border-radius: 50%;

        background: #37305d;
        color: #ffffff;

        font-size: 17px;
        font-weight: 800;
    }

    .student-content {
        width: 100%;
        max-width: 1550px;

        margin: 0 auto;
        padding: 32px;
    }

    /* ==========================================
       PAGE BANNER
       ========================================== */

    .attendance-header {
        position: relative;
        overflow: hidden;

        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;

        padding: 33px;
        margin-bottom: 25px;

        border: 1px solid #5a4d91;
        border-radius: 20px;

        background: linear-gradient(
            115deg,
            #26234a,
            #373069 58%,
            #594a96
        );
    }

    .attendance-header::after {
        content: "";
        position: absolute;

        top: -110px;
        right: -70px;

        width: 300px;
        height: 300px;

        border: 1px solid rgba(255,255,255,.12);
        border-radius: 50%;

        pointer-events: none;
    }

    .attendance-eyebrow {
        display: inline-block;
        margin-bottom: 12px;

        color: #d0c3ff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    .attendance-header h1 {
        margin: 0 0 10px;
        color: #ffffff;
        font-size: clamp(25px, 3vw, 34px);
        font-weight: 800;
        line-height: 1.25;
    }

    .attendance-header p {
        margin: 0;
        color: #e2dbf9;
        font-size: 13px;
        line-height: 1.75;
    }

    .attendance-header-tag {
        position: relative;
        z-index: 1;

        padding: 11px 17px;

        border: 1px solid rgba(255,255,255,.2);
        border-radius: 100px;

        background: rgba(255,255,255,.09);
        color: #ffffff;

        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    /* ==========================================
       OVERVIEW CARDS
       ========================================== */

    .attendance-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 17px;
        margin-bottom: 26px;
    }

    .attendance-stat {
        min-width: 0;
        padding: 23px;

        border: 1px solid var(--border);
        border-radius: 16px;

        background: var(--surface);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .attendance-stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;

        margin-bottom: 17px;
    }

    .attendance-stat-label {
        color: #b0bed5;
        font-size: 12px;
        font-weight: 700;
    }

    .attendance-stat-icon {
        display: flex;
        justify-content: center;
        align-items: center;

        width: 38px;
        height: 38px;
        flex-shrink: 0;

        border-radius: 11px;
        background: #322c54;

        color: #c7b8ff;
        font-size: 18px;
    }

    .attendance-stat-value {
        color: #ffffff;
        font-size: 29px;
        font-weight: 800;
        line-height: 1.25;
    }

    .attendance-stat-foot {
        margin-top: 9px;

        color: #91a1bb;
        font-size: 11px;
        line-height: 1.6;
    }

    /* ==========================================
       PROGRESS BAR
       ========================================== */

    .attendance-progress {
        overflow: hidden;
        width: 100%;
        height: 8px;

        margin-top: 15px;

        border-radius: 100px;
        background: #34415b;
    }

    .attendance-progress-fill {
        width: 0;
        height: 100%;

        border-radius: inherit;

        background: linear-gradient(
            90deg,
            #8573e9,
            #c1afff
        );
    }

    .attendance-progress-fill.good {
        background: linear-gradient(
            90deg,
            #37a88a,
            #8be4cb
        );
    }

    .attendance-progress-fill.warning {
        background: linear-gradient(
            90deg,
            #d16b83,
            #ffadbf
        );
    }

    /* ==========================================
       SUBJECT-WISE PANEL
       ========================================== */

    .attendance-panel {
        min-width: 0;
        padding: 26px;
        margin-bottom: 25px;

        border: 1px solid var(--border);
        border-radius: 17px;

        background: var(--surface);
        box-shadow: 0 10px 28px rgba(0,0,0,.1);
    }

    .attendance-panel-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;

        margin-bottom: 23px;
    }

    .attendance-panel-heading h2 {
        margin: 0;
        color: #ffffff;
        font-size: 20px;
        font-weight: 800;
    }

    .attendance-panel-heading p {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    .attendance-panel-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 40px;
        padding: 9px 16px;

        border: 1px solid #695e9a;
        border-radius: 9px;

        background: #2d294f;
        color: #ddd3ff;

        text-decoration: none;
        font-size: 12px;
        font-weight: 750;
    }

    .attendance-panel-link:hover {
        background: #423765;
    }

    /* ==========================================
       ATTENDANCE TABLE
       ========================================== */

    .attendance-table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;

        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .attendance-table {
        width: 100%;
        min-width: 830px;

        margin: 0;
        border-collapse: collapse;
        table-layout: auto;

        color: #e3ebf8;
        font-size: 13px;
    }

    .attendance-table th,
    .attendance-table td {
        padding: 16px 18px;

        border: none;
        border-bottom: 1px solid #29374d;

        text-align: left;
        vertical-align: middle;
        line-height: 1.6;
    }

    .attendance-table thead th {
        background: #23334c;
        color: #e2d9ff;

        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .attendance-table tbody tr {
        background: #172338;
    }

    .attendance-table tbody tr:nth-child(even) {
        background: #1b293e;
    }

    .attendance-table tbody tr:hover {
        background: #253650;
    }

    .attendance-table tbody tr:last-child td {
        border-bottom: none;
    }

    .attendance-table th:first-child,
    .attendance-table td:first-child {
        min-width: 225px;
    }

    .attendance-table th:last-child,
    .attendance-table td:last-child {
        min-width: 150px;
    }

    .attendance-subject-code {
        display: block;
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
    }

    .attendance-subject-name {
        display: block;
        margin-top: 4px;

        color: #a8b6ce;
        font-size: 11px;
    }

    .attendance-number {
        color: #eef2fc;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .attendance-percentage {
        display: inline-flex;
        align-items: center;

        padding: 7px 12px;

        border: 1px solid #514783;
        border-radius: 100px;

        background: #302a55;
        color: #ded3ff;

        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .attendance-percentage.good {
        border-color: #34695c;
        background: #1b403b;
        color: #8be4cb;
    }

    .attendance-percentage.warning {
        border-color: #8a5063;
        background: #462b38;
        color: #ffb6c5;
    }

    .attendance-percentage.neutral {
        border-color: #43516b;
        background: #263349;
        color: #b4c1d7;
    }

    /* ==========================================
       EMPTY STATE
       ========================================== */

    .attendance-empty {
        padding: 55px 20px;
        text-align: center;
    }

    .attendance-empty-icon {
        margin-bottom: 14px;
        font-size: 40px;
    }

    .attendance-empty h3 {
        margin: 0 0 10px;
        color: #ffffff;
        font-size: 20px;
    }

    .attendance-empty p {
        max-width: 510px;
        margin: 0 auto;

        color: #a8b6ce;
        font-size: 13px;
        line-height: 1.75;
    }

    /* ==========================================
       INFO BOX
       ========================================== */

    .attendance-info {
        padding: 18px 21px;

        border: 1px solid #4a4579;
        border-radius: 12px;

        background: #22213f;
        color: #d4cbf1;

        font-size: 12px;
        line-height: 1.8;
    }

    .attendance-info strong {
        color: #ffffff;
    }

    /* ==========================================
       RESPONSIVE LAYOUT
       ========================================== */

    @media (max-width: 1200px) {
        .attendance-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 950px) {
        .student-sidebar {
            width: 210px;
        }

        .student-main {
            margin-left: 210px;
        }

        .student-content {
            padding: 24px;
        }
    }

    @media (max-width: 650px) {
        .student-sidebar {
            position: relative;
            width: 100%;
            height: auto;
            padding: 17px;
        }

        .student-brand {
            padding-bottom: 15px;
        }

        .student-nav-title {
            margin: 18px 7px 12px;
        }

        .student-menu {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
        }

        .student-menu a {
            padding: 10px;
            font-size: 12px;
        }

        .student-sidebar-footer {
            margin-top: 15px;
            padding-top: 0;
        }

        .student-main {
            margin-left: 0;
        }

        .student-topbar {
            padding: 17px;
        }

        .student-topbar-title {
            font-size: 16px;
        }

        .student-account-name,
        .student-account-role {
            display: none;
        }

        .student-content {
            padding: 17px;
        }

        .attendance-header {
            padding: 24px 20px;
        }

        .attendance-stats {
            grid-template-columns: 1fr;
        }

        .attendance-panel {
            padding: 19px;
        }

        .attendance-panel-heading h2 {
            font-size: 18px;
        }
    }
    </style>

</head>

<body>

    <!-- =====================================
         STUDENT SIDEBAR
         ===================================== -->

    <aside class="student-sidebar">

        <div class="student-brand">

            <div class="student-brand-icon">
                S
            </div>

            <div>

                <div class="student-brand-name">
                    SmartFaculty
                </div>

                <div class="student-brand-caption">
                    Student Portal
                </div>

            </div>

        </div>

        <div class="student-nav-title">
            Navigation
        </div>

        <nav
            class="student-menu"
            aria-label="Student navigation"
        >

            <a href="dashboard.php">
                <span class="student-menu-icon">⌂</span>
                Dashboard
            </a>

            <a href="timetable.php">
                <span class="student-menu-icon">▦</span>
                Timetable
            </a>

            <a
                href="attendance.php"
                class="active"
                aria-current="page"
            >
                <span class="student-menu-icon">◫</span>
                Attendance
            </a>

            <a href="announcements.php">
                <span class="student-menu-icon">◉</span>
                Announcements
            </a>

            <a href="profile.php">
                <span class="student-menu-icon">♙</span>
                My Profile
            </a>

        </nav>

        <div class="student-sidebar-footer">

            <div class="student-sidebar-profile">

                <strong>
                    <?= attendanceEscape($studentName) ?>
                </strong>

                <span>
                    <?= attendanceEscape($registerNumber) ?>
                </span>

            </div>

            <a
                href="../auth/logout.php"
                class="student-logout"
            >
                Log Out
            </a>

        </div>

    </aside>


    <!-- =====================================
         MAIN AREA
         ===================================== -->

    <main class="student-main">

        <!-- TOP BAR -->

        <header class="student-topbar">

            <div>

                <h2 class="student-topbar-title">
                    Student Attendance
                </h2>

                <span class="student-topbar-subtitle">
                    Your academic attendance overview
                </span>

            </div>

            <div class="student-account">

                <div>

                    <div class="student-account-name">
                        <?= attendanceEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>

                </div>

                <div class="student-avatar">
                    <?= attendanceEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <div class="student-content">

            <!-- =================================
                 PAGE HEADER
                 ================================= -->

            <section class="attendance-header">

                <div>

                    <span class="attendance-eyebrow">
                        Academic Performance
                    </span>

                    <h1>
                        My Attendance
                    </h1>

                    <p>
                        <?= attendanceEscape($studentName) ?>

                        &nbsp; • &nbsp;

                        Section
                        <?= attendanceEscape($sectionName) ?>

                        &nbsp; • &nbsp;

                        Register No:
                        <?= attendanceEscape($registerNumber) ?>
                    </p>

                </div>

                <span class="attendance-header-tag">
                    Attendance Overview
                </span>

            </section>


            <!-- =================================
                 SUMMARY CARDS
                 ================================= -->

            <section
                class="attendance-stats"
                aria-label="Attendance summary"
            >

                <!-- OVERALL PERCENTAGE -->

                <div class="attendance-stat">

                    <div class="attendance-stat-top">

                        <span class="attendance-stat-label">
                            Overall Attendance
                        </span>

                        <span class="attendance-stat-icon">
                            ◫
                        </span>

                    </div>

                    <div class="attendance-stat-value">
                        <?= attendanceEscape($attendanceDisplay) ?>
                    </div>

                    <div class="attendance-stat-foot">
                        <?= attendanceEscape($overallStatus) ?>
                    </div>

                    <?php if ($totalClasses > 0): ?>

                        <div
                            class="attendance-progress"
                            role="progressbar"
                            aria-label="Overall attendance"
                            aria-valuenow="<?= $overallPercentage ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >

                            <div
                                class="attendance-progress-fill <?= $overallStatusClass ?>"
                                style="width: <?= max(
                                    0,
                                    min(100, $overallPercentage)
                                ) ?>%;"
                            ></div>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- PRESENT -->

                <div class="attendance-stat">

                    <div class="attendance-stat-top">

                        <span class="attendance-stat-label">
                            Classes Attended
                        </span>

                        <span class="attendance-stat-icon">
                            ✓
                        </span>

                    </div>

                    <div class="attendance-stat-value">
                        <?= $totalPresent ?>
                    </div>

                    <div class="attendance-stat-foot">
                        Marked present
                    </div>

                </div>


                <!-- ABSENT -->

                <div class="attendance-stat">

                    <div class="attendance-stat-top">

                        <span class="attendance-stat-label">
                            Classes Missed
                        </span>

                        <span class="attendance-stat-icon">
                            ×
                        </span>

                    </div>

                    <div class="attendance-stat-value">
                        <?= $totalAbsent ?>
                    </div>

                    <div class="attendance-stat-foot">
                        Marked absent
                    </div>

                </div>


                <!-- TOTAL -->

                <div class="attendance-stat">

                    <div class="attendance-stat-top">

                        <span class="attendance-stat-label">
                            Total Recorded Classes
                        </span>

                        <span class="attendance-stat-icon">
                            ▦
                        </span>

                    </div>

                    <div class="attendance-stat-value">
                        <?= $totalClasses ?>
                    </div>

                    <div class="attendance-stat-foot">
                        Across
                        <?= $subjectCount ?>
                        <?= $subjectCount === 1
                            ? "subject"
                            : "subjects" ?>
                    </div>

                </div>

            </section>


            <!-- =================================
                 SUBJECT-WISE ATTENDANCE
                 ================================= -->

            <section class="attendance-panel">

                <div class="attendance-panel-heading">

                    <div>

                        <h2>
                            Subject-wise Attendance
                        </h2>

                        <p>
                            Attendance records for each subject.
                        </p>

                    </div>

                    <a
                        href="attendance-history.php"
                        class="attendance-panel-link"
                    >
                        View Attendance History →
                    </a>

                </div>

                <?php if (count($attendanceRows) === 0): ?>

                    <!-- EMPTY STATE -->

                    <div class="attendance-empty">

                        <div class="attendance-empty-icon">
                            ◫
                        </div>

                        <h3>
                            No Attendance Records Yet
                        </h3>

                        <p>
                            Your attendance information will appear
                            here once faculty members begin marking
                            attendance for your classes.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="attendance-table-scroll">

                        <table class="attendance-table">

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

                                <?php foreach (
                                    $attendanceRows as $row
                                ): ?>

                                    <?php

                                    $percentage =
                                        (float)$row["percentage"];

                                    $percentageClass =
                                        $percentage >= 75
                                            ? "good"
                                            : "warning";

                                    ?>

                                    <tr>

                                        <!-- SUBJECT -->

                                        <td>

                                            <span class="attendance-subject-code">
                                                <?= attendanceEscape(
                                                    $row["subject_code"]
                                                ) ?>
                                            </span>

                                            <span class="attendance-subject-name">
                                                <?= attendanceEscape(
                                                    $row["subject_name"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <!-- TOTAL -->

                                        <td class="attendance-number">
                                            <?= (int)$row["total_classes"] ?>
                                        </td>

                                        <!-- PRESENT -->

                                        <td class="attendance-number">
                                            <?= (int)$row["present_classes"] ?>
                                        </td>

                                        <!-- ABSENT -->

                                        <td class="attendance-number">
                                            <?= (int)$row["absent_classes"] ?>
                                        </td>

                                        <!-- PERCENTAGE -->

                                        <td>

                                            <span
                                                class="attendance-percentage <?= $percentageClass ?>"
                                            >
                                                <?= attendanceEscape(
                                                    $percentage
                                                ) ?>%
                                            </span>

                                        </td>

                                        <!-- PROGRESS -->

                                        <td>

                                            <div
                                                class="attendance-progress"
                                                role="progressbar"
                                                aria-label="Attendance for <?= attendanceEscape(
                                                    $row["subject_code"]
                                                ) ?>"
                                                aria-valuenow="<?= $percentage ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                style="margin-top:0;"
                                            >

                                                <div
                                                    class="attendance-progress-fill <?= $percentageClass ?>"
                                                    style="width: <?= max(
                                                        0,
                                                        min(100, $percentage)
                                                    ) ?>%;"
                                                ></div>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================
                 ATTENDANCE INFORMATION
                 ================================= -->

            <div class="attendance-info">

                <strong>Attendance information:</strong>

                Percentages are calculated from recorded
                attendance entries.

                <strong>75%</strong> is used as the
                status threshold on this page.

                If attendance has not been recorded,
                the overall percentage is shown as
                <strong>N/A</strong>.

            </div>

        </div>

    </main>

</body>
</html>
