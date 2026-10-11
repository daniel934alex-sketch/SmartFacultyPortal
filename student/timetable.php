
<?php

date_default_timezone_set("Asia/Kolkata");

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "Student Timetable";

/* ==========================================
   HELPER FUNCTIONS
   ========================================== */

function timetableEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function timetableFormatTime($time)
{
    if (empty($time) || $time === "-") {
        return "—";
    }

    $timestamp = strtotime($time);

    if ($timestamp === false) {
        return "—";
    }

    return date("h:i A", $timestamp);
}

/* ==========================================
   VERIFY STUDENT SESSION
   ========================================== */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

/* ==========================================
   LOAD LOGGED-IN STUDENT
   ========================================== */

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

$studentName = $student["student_name"];
$registerNumber = $student["register_number"];
$sectionId = (int)$student["section_id"];
$sectionName = $student["section_name"];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

/* ==========================================
   WEEK DAYS
   ========================================== */

$days = [
    "MONDAY",
    "TUESDAY",
    "WEDNESDAY",
    "THURSDAY",
    "FRIDAY",
    "SATURDAY"
];

$todayDay = strtoupper(date("l"));

/* ==========================================
   LOAD PERIODS
   ========================================== */

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

    $periodNumber = (int)$periodRow["period_number"];

    $periods[$periodNumber] = $periodRow;
}

$periodStmt->close();

/* ==========================================
   LOAD PUBLISHED TIMETABLE
   ========================================== */

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

/* ==========================================
   STORE TIMETABLE BY DAY AND PERIOD
   ========================================== */

$timetable = [];
$dayCounts = [];
$totalPublishedPeriods = 0;

foreach ($days as $day) {
    $timetable[$day] = [];
    $dayCounts[$day] = 0;
}

while ($row = $timetableResult->fetch_assoc()) {

    $day = strtoupper($row["day"]);
    $periodNumber = (int)$row["period_number"];

    if (
        isset($timetable[$day]) &&
        $periodNumber >= 1 &&
        $periodNumber <= 9
    ) {

        if (!isset($timetable[$day][$periodNumber])) {
            $dayCounts[$day]++;
            $totalPublishedPeriods++;
        }

        $timetable[$day][$periodNumber] = $row;
    }
}

$timetableStmt->close();

$hasTimetable = $totalPublishedPeriods > 0;

/* ==========================================
   SELECTED DAY FOR QUICK NAVIGATION
   ========================================== */

$selectedDay = strtoupper(
    trim($_GET["day"] ?? "")
);

if (!in_array($selectedDay, $days, true)) {
    $selectedDay = in_array($todayDay, $days, true)
        ? $todayDay
        : "MONDAY";
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
        Student Timetable | SmartFacultyPortal
    </title>

    <style>
    /* ==========================================
       STUDENT TIMETABLE - PREMIUM DARK UI
       SELF-CONTAINED STYLES
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
       SIDEBAR
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
            background .2s ease,
            color .2s ease;
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
       MAIN AND TOPBAR
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
        color: var(--muted);
        font-size: 11px;
        text-align: right;
        margin-top: 3px;
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
       WELCOME / TITLE BANNER
       ========================================== */

    .tt-header {
        position: relative;
        overflow: hidden;

        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;

        padding: 33px;
        margin-bottom: 24px;

        border: 1px solid #5a4d91;
        border-radius: 20px;

        background: linear-gradient(
            115deg,
            #26234a,
            #373069 58%,
            #594a96
        );
    }

    .tt-header::after {
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

    .tt-eyebrow {
        display: inline-block;
        margin-bottom: 12px;

        color: #d0c3ff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    .tt-header h1 {
        margin: 0 0 10px;

        color: #ffffff;
        font-size: clamp(25px, 3vw, 34px);
        font-weight: 800;
        line-height: 1.25;
    }

    .tt-header p {
        margin: 0;
        color: #e2dbf9;
        font-size: 13px;
        line-height: 1.7;
    }

    .tt-header-tag {
        position: relative;
        z-index: 1;

        display: inline-flex;
        align-items: center;

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
       SUMMARY CARDS
       ========================================== */

    .tt-stats {
        display: grid;
        grid-template-columns: repeat(
            3,
            minmax(0, 1fr)
        );

        gap: 17px;
        margin-bottom: 26px;
    }

    .tt-stat {
        min-width: 0;

        padding: 23px;

        border: 1px solid var(--border);
        border-radius: 16px;

        background: var(--surface);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .tt-stat-label {
        margin-bottom: 13px;
        color: #b0bed5;
        font-size: 12px;
        font-weight: 700;
    }

    .tt-stat-value {
        color: #ffffff;
        font-size: 28px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .tt-stat-foot {
        margin-top: 8px;
        color: #91a1bb;
        font-size: 11px;
    }

    /* ==========================================
       DAY NAVIGATION
       ========================================== */

    .tt-day-nav {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;

        margin-bottom: 26px;
        padding: 17px;

        border: 1px solid var(--border);
        border-radius: 14px;

        background: #172338;
    }

    .tt-day-nav a {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 41px;
        padding: 10px 17px;

        border: 1px solid #3d4a68;
        border-radius: 10px;

        background: #111d30;
        color: #bfcae0;

        text-decoration: none;
        font-size: 12px;
        font-weight: 800;

        transition: background .2s;
    }

    .tt-day-nav a:hover {
        background: #302a55;
        color: #ffffff;
    }

    .tt-day-nav a.active {
        border-color: #7968b9;
        background: #393064;
        color: #ffffff;
    }

    .tt-day-nav .tt-today-label {
        margin-left: auto;
        color: #a6b5cc;
        font-size: 12px;
    }

    /* ==========================================
       DAY CARDS
       ========================================== */

    .tt-day-card {
        min-width: 0;

        margin-bottom: 26px;
        padding: 26px;

        border: 1px solid var(--border);
        border-radius: 17px;

        background: var(--surface);
        box-shadow: 0 10px 28px rgba(0,0,0,.1);

        scroll-margin-top: 20px;
    }

    .tt-day-card.today {
        border-color: #6355a2;
    }

    .tt-day-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 13px;

        margin-bottom: 21px;
    }

    .tt-day-heading h2 {
        margin: 0;
        color: #ffffff;
        font-size: 20px;
        font-weight: 800;
    }

    .tt-day-heading p {
        margin: 6px 0 0;
        color: #9eacc6;
        font-size: 12px;
    }

    .tt-day-count {
        display: inline-flex;
        align-items: center;

        padding: 8px 13px;

        border: 1px solid #514783;
        border-radius: 100px;

        background: #302a55;
        color: #dfd5ff;

        font-size: 11px;
        font-weight: 800;
    }

    /* ==========================================
       TABLE
       ========================================== */

    .tt-table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;

        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .tt-table {
        width: 100%;
        min-width: 850px;

        border-collapse: collapse;
        table-layout: auto;

        color: #e3ebf8;
        font-size: 13px;
    }

    .tt-table th,
    .tt-table td {
        padding: 15px 17px;

        border: none;
        border-bottom: 1px solid #29374d;

        text-align: left;
        vertical-align: middle;

        line-height: 1.6;
    }

    .tt-table thead th {
        background: #23334c;
        color: #e2d9ff;

        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .tt-table tbody tr {
        background: #172338;
    }

    .tt-table tbody tr:nth-child(even) {
        background: #1b293e;
    }

    .tt-table tbody tr:hover {
        background: #253650;
    }

    .tt-table tbody tr:last-child td {
        border-bottom: none;
    }

    .tt-table th:nth-child(1),
    .tt-table td:nth-child(1) {
        width: 90px;
        white-space: nowrap;
    }

    .tt-table th:nth-child(2),
    .tt-table td:nth-child(2) {
        min-width: 175px;
        white-space: nowrap;
    }

    .tt-table th:nth-child(3),
    .tt-table td:nth-child(3) {
        min-width: 240px;
    }

    .tt-table th:nth-child(4),
    .tt-table td:nth-child(4) {
        min-width: 180px;
    }

    .tt-table th:nth-child(5),
    .tt-table td:nth-child(5) {
        min-width: 130px;
    }

    .tt-period {
        color: #c4b3ff;
        font-weight: 800;
    }

    .tt-time {
        color: #d0dbeb;
        font-size: 12px;
        white-space: nowrap;
    }

    .tt-subject-code {
        display: block;
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
    }

    .tt-subject-name {
        display: block;
        margin-top: 4px;

        color: #a8b6ce;
        font-size: 11px;
        line-height: 1.5;
    }

    .tt-faculty {
        color: #e3ebf8;
        overflow-wrap: anywhere;
    }

    .tt-room {
        display: block;
        color: #ffffff;
        font-weight: 750;
    }

    .tt-room-type {
        display: block;
        margin-top: 4px;

        color: #a8b6ce;
        font-size: 11px;
    }

    .tt-free {
        color: #7385a3;
        font-size: 12px;
    }

    .tt-free-pill {
        display: inline-block;

        padding: 6px 11px;
        border: 1px solid #384a60;
        border-radius: 100px;

        background: #223044;
        color: #abb9cc;

        font-size: 11px;
        font-weight: 700;
    }

    /* ==========================================
       EMPTY TIMETABLE
       ========================================== */

    .tt-empty {
        padding: 65px 22px;
        border: 1px solid var(--border);
        border-radius: 17px;

        background: #172338;
        text-align: center;
    }

    .tt-empty-icon {
        margin-bottom: 15px;
        font-size: 43px;
    }

    .tt-empty h2 {
        margin: 0 0 10px;
        color: #ffffff;
        font-size: 21px;
    }

    .tt-empty p {
        max-width: 520px;
        margin: 0 auto;

        color: #a8b6ce;
        font-size: 13px;
        line-height: 1.8;
    }

    /* ==========================================
       MOBILE
       ========================================== */

    @media (max-width: 1150px) {
        .tt-stats {
            grid-template-columns: repeat(
                2,
                minmax(0, 1fr)
            );
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

        .tt-header {
            padding: 27px;
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
            grid-template-columns: repeat(
                2,
                minmax(0, 1fr)
            );
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

        .tt-header {
            padding: 24px 20px;
        }

        .tt-stats {
            grid-template-columns: 1fr;
        }

        .tt-day-card {
            padding: 19px;
        }

        .tt-day-nav {
            padding: 13px;
        }

        .tt-day-nav a {
            flex: 1 1 auto;
        }

        .tt-day-nav .tt-today-label {
            width: 100%;
            margin-left: 0;
            margin-top: 5px;
        }
    }
    </style>

</head>

<body>

    <!-- =====================================
         SIDEBAR
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

            <a
                href="timetable.php"
                class="active"
                aria-current="page"
            >
                <span class="student-menu-icon">▦</span>
                Timetable
            </a>

            <a href="attendance.php">
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
                    <?= timetableEscape($studentName) ?>
                </strong>

                <span>
                    <?= timetableEscape($registerNumber) ?>
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
         MAIN
         ===================================== -->

    <main class="student-main">

        <!-- TOP BAR -->

        <header class="student-topbar">

            <div>

                <h2 class="student-topbar-title">
                    Student Timetable
                </h2>

                <span class="student-topbar-subtitle">
                    Your published weekly schedule
                </span>

            </div>

            <div class="student-account">

                <div>

                    <div class="student-account-name">
                        <?= timetableEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>

                </div>

                <div class="student-avatar">
                    <?= timetableEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <!-- PAGE CONTENT -->

        <div class="student-content">

            <!-- =====================================
                 PAGE HEADER
                 ===================================== -->

            <section class="tt-header">

                <div>

                    <span class="tt-eyebrow">
                        Academic Schedule
                    </span>

                    <h1>
                        Weekly Timetable
                    </h1>

                    <p>
                        <?= timetableEscape($studentName) ?>
                        &nbsp; • &nbsp;

                        Section
                        <?= timetableEscape($sectionName) ?>
                        &nbsp; • &nbsp;

                        Register No:
                        <?= timetableEscape($registerNumber) ?>
                    </p>

                </div>

                <div class="tt-header-tag">
                    Published Schedule
                </div>

            </section>


            <!-- =====================================
                 SUMMARY CARDS
                 ===================================== -->

            <section
                class="tt-stats"
                aria-label="Timetable summary"
            >

                <div class="tt-stat">

                    <div class="tt-stat-label">
                        My Section
                    </div>

                    <div class="tt-stat-value">
                        <?= timetableEscape($sectionName) ?>
                    </div>

                    <div class="tt-stat-foot">
                        Assigned academic section
                    </div>

                </div>

                <div class="tt-stat">

                    <div class="tt-stat-label">
                        Weekly Classes
                    </div>

                    <div class="tt-stat-value">
                        <?= $totalPublishedPeriods ?>
                    </div>

                    <div class="tt-stat-foot">
                        Published timetable periods
                    </div>

                </div>

                <div class="tt-stat">

                    <div class="tt-stat-label">
                        Today's Classes
                    </div>

                    <div class="tt-stat-value">
                        <?= $dayCounts[$todayDay] ?? 0 ?>
                    </div>

                    <div class="tt-stat-foot">
                        <?= timetableEscape(
                            ucfirst(strtolower($todayDay))
                        ) ?>
                    </div>

                </div>

            </section>


            <?php if (!$hasTimetable): ?>

                <!-- EMPTY TIMETABLE -->

                <section class="tt-empty">

                    <div class="tt-empty-icon">
                        ▦
                    </div>

                    <h2>
                        Timetable Not Published Yet
                    </h2>

                    <p>
                        The timetable for section
                        <strong>
                            <?= timetableEscape($sectionName) ?>
                        </strong>
                        will appear here once the HOD
                        publishes it.
                    </p>

                </section>

            <?php else: ?>

                <!-- =================================
                     QUICK DAY NAVIGATION
                     ================================= -->

                <nav
                    class="tt-day-nav"
                    aria-label="Jump to timetable day"
                >

                    <?php foreach ($days as $day): ?>

                        <a
                            href="#day-<?= strtolower($day) ?>"
                            class="<?= $day === $selectedDay
                                ? "active"
                                : "" ?>"
                        >
                            <?= timetableEscape(
                                ucfirst(strtolower($day))
                            ) ?>
                        </a>

                    <?php endforeach; ?>

                    <span class="tt-today-label">
                        Today:
                        <?= timetableEscape(
                            ucfirst(strtolower($todayDay))
                        ) ?>
                    </span>

                </nav>


                <!-- =================================
                     ALL SIX DAYS
                     ================================= -->

                <?php foreach ($days as $day): ?>

                    <?php
                        $isToday = $day === $todayDay;
                    ?>

                    <section
                        id="day-<?= strtolower($day) ?>"
                        class="tt-day-card <?= $isToday
                            ? "today"
                            : "" ?>"
                    >

                        <div class="tt-day-heading">

                            <div>

                                <h2>
                                    <?= timetableEscape(
                                        ucfirst(strtolower($day))
                                    ) ?>

                                    <?php if ($isToday): ?>
                                        <span
                                            style="
                                                color:#bbaaff;
                                                font-size:12px;
                                                margin-left:8px;
                                            "
                                        >
                                            TODAY
                                        </span>
                                    <?php endif; ?>

                                </h2>

                                <p>
                                    <?= $dayCounts[$day] ?>
                                    published
                                    <?= $dayCounts[$day] === 1
                                        ? "period"
                                        : "periods" ?>
                                </p>

                            </div>

                            <span class="tt-day-count">
                                <?= $dayCounts[$day] ?>
                                Classes
                            </span>

                        </div>


                        <!-- TIMETABLE TABLE -->

                        <div class="tt-table-scroll">

                            <table class="tt-table">

                                <thead>

                                    <tr>
                                        <th>Period</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Regular Faculty</th>
                                        <th>Room</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php
                                    /*
                                     * Always display P1 to P9,
                                     * including unassigned slots.
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
                                            $row["start_time"]
                                            ?? (
                                                $periodInfo["start_time"]
                                                ?? null
                                            );

                                        $endTime =
                                            $row["end_time"]
                                            ?? (
                                                $periodInfo["end_time"]
                                                ?? null
                                            );
                                    ?>

                                        <tr>

                                            <!-- PERIOD -->

                                            <td>

                                                <span class="tt-period">
                                                    P<?= $periodNumber ?>
                                                </span>

                                            </td>

                                            <!-- TIME -->

                                            <td class="tt-time">

                                                <?= timetableFormatTime(
                                                    $startTime
                                                ) ?>

                                                –

                                                <?= timetableFormatTime(
                                                    $endTime
                                                ) ?>

                                            </td>

                                            <?php if ($row): ?>

                                                <!-- SUBJECT -->

                                                <td>

                                                    <span class="tt-subject-code">
                                                        <?= timetableEscape(
                                                            $row["subject_code"]
                                                        ) ?>
                                                    </span>

                                                    <span class="tt-subject-name">
                                                        <?= timetableEscape(
                                                            $row["subject_name"]
                                                        ) ?>
                                                    </span>

                                                </td>

                                                <!-- FACULTY -->

                                                <td class="tt-faculty">
                                                    <?= timetableEscape(
                                                        $row["faculty_name"]
                                                    ) ?>
                                                </td>

                                                <!-- ROOM -->

                                                <td>

                                                    <span class="tt-room">
                                                        <?= timetableEscape(
                                                            $row["room_number"]
                                                        ) ?>
                                                    </span>

                                                    <span class="tt-room-type">
                                                        <?= timetableEscape(
                                                            $row["room_type"]
                                                        ) ?>
                                                    </span>

                                                </td>

                                            <?php else: ?>

                                                <!-- FREE PERIOD -->

                                                <td>
                                                    <span class="tt-free-pill">
                                                        No Class
                                                    </span>
                                                </td>

                                                <td class="tt-free">
                                                    —
                                                </td>

                                                <td class="tt-free">
                                                    —
                                                </td>

                                            <?php endif; ?>

                                        </tr>

                                    <?php endfor; ?>

                                </tbody>

                            </table>

                        </div>

                    </section>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>
