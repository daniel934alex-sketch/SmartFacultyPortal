
<?php

date_default_timezone_set("Asia/Kolkata");

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "Student Dashboard";

/* =========================================
   OUTPUT ESCAPING
   ========================================= */

function studentDashEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   CURRENT STUDENT
   ========================================= */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

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

$studentId = (int)$student["student_id"];
$studentName = $student["student_name"];
$registerNumber = $student["register_number"];
$sectionId = (int)$student["section_id"];
$sectionName = $student["section_name"];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

/* =========================================
   ATTENDANCE SUMMARY
   ========================================= */

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

$attendanceData = $attendanceStmt
    ->get_result()
    ->fetch_assoc();

$attendanceStmt->close();

$totalAttendanceClasses =
    (int)($attendanceData["total_classes"] ?? 0);

$presentAttendanceClasses =
    (int)($attendanceData["present_classes"] ?? 0);

$attendancePercentage = null;
$attendanceDisplay = "N/A";

if ($totalAttendanceClasses > 0) {

    $attendancePercentage = round(
        ($presentAttendanceClasses / $totalAttendanceClasses) * 100,
        1
    );

    $attendanceDisplay = $attendancePercentage . "%";
}

/* =========================================
   TODAY'S TIMETABLE
   ========================================= */

$todayDay = strtoupper(date("l"));

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
$todayClasses = [];

while ($classRow = $todayResult->fetch_assoc()) {
    $todayClasses[] = $classRow;
}

$todayStmt->close();

$todayClassCount = count($todayClasses);

/* =========================================
   CURRENT AND NEXT CLASS
   ========================================= */

$currentTime = date("H:i:s");

$nextClass = null;

foreach ($todayClasses as $class) {

    if ($currentTime < $class["end_time"]) {
        $nextClass = $class;
        break;
    }
}

$nextClassPeriod = $nextClass
    ? "P" . $nextClass["period_number"]
    : "—";

$nextClassSubject = $nextClass
    ? $nextClass["subject_code"]
    : "No more classes";

function studentDashClassStatus($startTime, $endTime)
{
    $currentTime = date("H:i:s");

    if ($currentTime >= $endTime) {
        return "Completed";
    }

    if ($currentTime >= $startTime) {
        return "Ongoing";
    }

    return "Upcoming";
}

function studentDashTime($time)
{
    return date("h:i A", strtotime($time));
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
        Student Dashboard | SmartFacultyPortal
    </title>

    <style>
        /* =========================================
           STUDENT DASHBOARD
           PREMIUM NAVY + VIOLET THEME
           SELF-CONTAINED CSS
           ========================================= */

        :root {
            --bg: #0c1424;
            --sidebar: #111b2d;
            --surface: #172338;
            --surface-alt: #1c2a42;
            --border: #2b3a54;
            --text: #eef2fc;
            --muted: #9eacc6;
            --violet: #aa94ff;
            --violet-light: #d4c8ff;
            --green: #75dec1;
            --amber: #f1d38c;
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

        button {
            font: inherit;
        }

        /* =========================================
           SIDEBAR
           ========================================= */

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
            background: #101a2d;
            z-index: 50;
        }

        .student-brand {
            display: flex;
            gap: 12px;
            align-items: center;

            padding: 4px 8px 29px;
            border-bottom: 1px solid var(--border);
        }

        .student-brand-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;

            display: flex;
            justify-content: center;
            align-items: center;

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
            color: var(--muted);
            font-size: 11px;
            margin-top: 4px;
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

            transition: background .2s, color .2s;
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

        /* =========================================
           MAIN LAYOUT
           ========================================= */

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
            min-width: 0;
        }

        .student-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;

            width: 43px;
            height: 43px;

            border: 1px solid #7364aa;
            border-radius: 50%;

            background: #37305d;
            color: #ffffff;
            font-size: 17px;
            font-weight: 800;
        }

        .student-account-name {
            color: #ffffff;
            font-size: 13px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .student-account-role {
            color: var(--muted);
            font-size: 11px;
            margin-top: 3px;
        }

        .student-content {
            width: 100%;
            max-width: 1550px;
            margin: 0 auto;
            padding: 32px;
        }

        /* =========================================
           WELCOME
           ========================================= */

        .student-welcome {
            position: relative;
            overflow: hidden;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;

            margin-bottom: 26px;
            padding: 34px;

            border: 1px solid #5a4d91;
            border-radius: 20px;

            background: linear-gradient(
                115deg,
                #26234a 0%,
                #373069 58%,
                #594a96 100%
            );
        }

        .student-welcome::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -110px;

            width: 320px;
            height: 320px;

            border: 1px solid rgba(255,255,255,.13);
            border-radius: 50%;

            pointer-events: none;
        }

        .student-eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            color: #d0c3ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .student-welcome h1 {
            margin: 0 0 11px;
            color: #ffffff;
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 800;
            line-height: 1.25;
        }

        .student-welcome p {
            margin: 0;
            color: #e2dbf9;
            font-size: 13px;
            line-height: 1.75;
        }

        .student-welcome-date {
            position: relative;
            z-index: 1;

            flex-shrink: 0;
            padding: 16px 20px;

            border: 1px solid rgba(255,255,255,.2);
            border-radius: 13px;

            background: rgba(255,255,255,.08);
            color: #ffffff;

            text-align: center;
        }

        .student-welcome-date strong {
            display: block;
            font-size: 15px;
        }

        .student-welcome-date span {
            display: block;
            margin-top: 5px;
            color: #e0d7ff;
            font-size: 12px;
        }

        /* =========================================
           STAT CARDS
           ========================================= */

        .student-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 27px;
        }

        .student-stat {
            min-width: 0;
            padding: 23px;

            border: 1px solid var(--border);
            border-radius: 16px;

            background: var(--surface);
            box-shadow: 0 10px 25px rgba(0,0,0,.1);
        }

        .student-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }

        .student-stat-label {
            color: #b0bed5;
            font-size: 12px;
            font-weight: 700;
        }

        .student-stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 38px;
            height: 38px;
            flex-shrink: 0;

            border-radius: 11px;
            background: #322c54;
            color: #c7b8ff;
            font-size: 18px;
        }

        .student-stat-value {
            color: #ffffff;
            font-size: 29px;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .student-stat-foot {
            margin-top: 9px;
            color: #91a1bb;
            font-size: 11px;
            line-height: 1.5;
        }

        .student-progress {
            overflow: hidden;
            height: 7px;
            margin-top: 14px;
            border-radius: 100px;
            background: #34415b;
        }

        .student-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(
                90deg,
                #8573e9,
                #c1afff
            );
        }

        /* =========================================
           CONTENT PANELS
           ========================================= */

        .student-panel {
            min-width: 0;
            padding: 26px;
            margin-bottom: 25px;

            border: 1px solid var(--border);
            border-radius: 17px;

            background: var(--surface);
            box-shadow: 0 10px 28px rgba(0,0,0,.1);
        }

        .student-panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;

            margin-bottom: 23px;
        }

        .student-panel-heading h2 {
            margin: 0;
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
        }

        .student-panel-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .student-panel-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 38px;
            padding: 9px 15px;

            border: 1px solid #695e9a;
            border-radius: 9px;

            background: #2d294f;
            color: #ddd3ff;

            text-decoration: none;
            font-size: 12px;
            font-weight: 750;
        }

        .student-panel-link:hover {
            background: #423765;
        }

        /* =========================================
           TIMETABLE
           ========================================= */

        .student-table-scroll {
            overflow-x: auto;
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .student-table {
            width: 100%;
            min-width: 820px;
            border-collapse: collapse;
            font-size: 13px;
        }

        .student-table th,
        .student-table td {
            padding: 15px 17px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #2b3a52;
        }

        .student-table th {
            background: #24334c;
            color: #ddd5ff;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .student-table tbody tr:nth-child(even) {
            background: #1c2a40;
        }

        .student-table tbody tr:hover {
            background: #253651;
        }

        .student-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .student-table td {
            color: #dbe5f4;
        }

        .student-table strong {
            color: #ffffff;
        }

        .student-secondary {
            display: block;
            margin-top: 4px;
            color: #a1afc7;
            font-size: 11px;
        }

        .student-period {
            color: #c4b3ff;
            font-weight: 800;
            white-space: nowrap;
        }

        .student-time {
            white-space: nowrap;
            color: #d0dbeb;
            font-size: 12px;
        }

        /* =========================================
           CLASS STATUS
           ========================================= */

        .student-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 6px 11px;
            border-radius: 100px;

            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .student-status.completed {
            border: 1px solid #34695c;
            background: #1b403b;
            color: #8be4cb;
        }

        .student-status.ongoing {
            border: 1px solid #8b7039;
            background: #46391e;
            color: #ffe2a1;
        }

        .student-status.upcoming {
            border: 1px solid #5b5393;
            background: #312c58;
            color: #cfc2ff;
        }

        .student-empty {
            padding: 34px 20px;
            text-align: center;
            color: #b0bed5;
            font-size: 13px;
        }

        /* =========================================
           QUICK LINKS
           ========================================= */

        .student-quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
        }

        .student-quick-link {
            display: block;
            min-width: 0;
            padding: 22px;

            border: 1px solid #36435e;
            border-radius: 13px;

            background: #111d30;
            color: #ffffff;
            text-decoration: none;

            transition: transform .2s, background .2s;
        }

        .student-quick-link:hover {
            transform: translateY(-3px);
            background: #263451;
        }

        .student-quick-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 44px;
            height: 44px;
            margin-bottom: 16px;

            border-radius: 12px;
            background: #322c54;

            font-size: 22px;
        }

        .student-quick-link strong {
            display: block;
            margin-bottom: 7px;
            color: #ffffff;
            font-size: 14px;
        }

        .student-quick-link span:last-child {
            display: block;
            color: #a6b5cc;
            font-size: 12px;
            line-height: 1.7;
        }

        /* =========================================
           RESPONSIVE DESIGN
           ========================================= */

        @media (max-width: 1200px) {
            .student-stats {
                grid-template-columns: repeat(2,minmax(0,1fr));
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

            .student-quick-grid {
                grid-template-columns: 1fr;
            }

            .student-welcome {
                align-items: flex-start;
                flex-direction: column;
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

            .student-account-name {
                display: none;
            }

            .student-account-role {
                display: none;
            }

            .student-content {
                padding: 17px;
            }

            .student-welcome {
                padding: 24px 20px;
            }

            .student-stats {
                grid-template-columns: 1fr;
            }

            .student-panel {
                padding: 19px;
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

        <nav class="student-menu" aria-label="Student navigation">

            <a
                href="dashboard.php"
                class="active"
                aria-current="page"
            >
                <span class="student-menu-icon">⌂</span>
                Dashboard
            </a>

            <a href="timetable.php">
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
                    <?= studentDashEscape($studentName) ?>
                </strong>

                <span>
                    <?= studentDashEscape($registerNumber) ?>
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
                    Student Dashboard
                </h2>

                <span class="student-topbar-subtitle">
                    Your academic overview
                </span>

            </div>

            <div class="student-account">

                <div>

                    <div class="student-account-name">
                        <?= studentDashEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>

                </div>

                <div class="student-avatar">
                    <?= studentDashEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <div class="student-content">

            <!-- =================================
                 WELCOME BANNER
                 ================================= -->

            <section class="student-welcome">

                <div>

                    <span class="student-eyebrow">
                        Your Learning Space
                    </span>

                    <h1>
                        Welcome,
                        <?= studentDashEscape($studentName) ?>!
                        👋
                    </h1>

                    <p>
                        Section <?= studentDashEscape($sectionName) ?>
                        &nbsp; • &nbsp;
                        Register No:
                        <?= studentDashEscape($registerNumber) ?>
                    </p>

                </div>

                <div class="student-welcome-date">

                    <strong>
                        <?= date("l") ?>
                    </strong>

                    <span>
                        <?= date("d M Y") ?>
                    </span>

                </div>

            </section>


            <!-- =================================
                 SUMMARY CARDS
                 ================================= -->

            <section
                class="student-stats"
                aria-label="Academic summary"
            >

                <!-- SECTION -->

                <div class="student-stat">

                    <div class="student-stat-top">

                        <span class="student-stat-label">
                            My Section
                        </span>

                        <div class="student-stat-icon">
                            ▦
                        </div>

                    </div>

                    <div class="student-stat-value">
                        <?= studentDashEscape($sectionName) ?>
                    </div>

                    <div class="student-stat-foot">
                        Your assigned class section
                    </div>

                </div>


                <!-- TODAY CLASSES -->

                <div class="student-stat">

                    <div class="student-stat-top">

                        <span class="student-stat-label">
                            Today's Classes
                        </span>

                        <div class="student-stat-icon">
                            ◷
                        </div>

                    </div>

                    <div class="student-stat-value">
                        <?= $todayClassCount ?>
                    </div>

                    <div class="student-stat-foot">
                        Published periods scheduled today
                    </div>

                </div>


                <!-- ATTENDANCE -->

                <div class="student-stat">

                    <div class="student-stat-top">

                        <span class="student-stat-label">
                            My Attendance
                        </span>

                        <div class="student-stat-icon">
                            ◫
                        </div>

                    </div>

                    <div class="student-stat-value">
                        <?= studentDashEscape($attendanceDisplay) ?>
                    </div>

                    <div class="student-stat-foot">
                        <?= $presentAttendanceClasses ?>
                        of
                        <?= $totalAttendanceClasses ?>
                        recorded classes attended
                    </div>

                    <?php if ($attendancePercentage !== null): ?>

                        <div
                            class="student-progress"
                            role="progressbar"
                            aria-label="Attendance percentage"
                            aria-valuenow="<?= $attendancePercentage ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >

                            <div
                                class="student-progress-bar"
                                style="width: <?= max(
                                    0,
                                    min(100, $attendancePercentage)
                                ) ?>%;"
                            ></div>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- NEXT CLASS -->

                <div class="student-stat">

                    <div class="student-stat-top">

                        <span class="student-stat-label">
                            Next / Current Class
                        </span>

                        <div class="student-stat-icon">
                            ◈
                        </div>

                    </div>

                    <div class="student-stat-value">
                        <?= studentDashEscape($nextClassPeriod) ?>
                    </div>

                    <div class="student-stat-foot">
                        <?= studentDashEscape($nextClassSubject) ?>
                    </div>

                </div>

            </section>


            <!-- =================================
                 TODAY'S TIMETABLE
                 ================================= -->

            <section class="student-panel">

                <div class="student-panel-heading">

                    <div>

                        <h2>
                            Today's Classes
                        </h2>

                        <p>
                            <?= studentDashEscape(
                                ucfirst(strtolower($todayDay))
                            ) ?>
                            •
                            <?= $todayClassCount ?>
                            scheduled period<?= $todayClassCount === 1
                                ? ""
                                : "s" ?>
                        </p>

                    </div>

                    <a
                        href="timetable.php"
                        class="student-panel-link"
                    >
                        View Full Timetable →
                    </a>

                </div>

                <?php if ($todayClassCount > 0): ?>

                    <div class="student-table-scroll">

                        <table class="student-table">

                            <thead>

                                <tr>
                                    <th>Period</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Room</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $todayClasses as $class
                                ): ?>

                                    <?php

                                    $classStatus = studentDashClassStatus(
                                        $class["start_time"],
                                        $class["end_time"]
                                    );

                                    $statusClass = strtolower(
                                        $classStatus
                                    );

                                    ?>

                                    <tr>

                                        <td>
                                            <span class="student-period">
                                                P<?= (int)$class["period_number"] ?>
                                            </span>
                                        </td>

                                        <td class="student-time">
                                            <?= studentDashTime(
                                                $class["start_time"]
                                            ) ?>
                                            –
                                            <?= studentDashTime(
                                                $class["end_time"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= studentDashEscape(
                                                    $class["subject_code"]
                                                ) ?>
                                            </strong>

                                            <span class="student-secondary">
                                                <?= studentDashEscape(
                                                    $class["subject_name"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= studentDashEscape(
                                                $class["faculty_name"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?= studentDashEscape(
                                                    $class["room_number"]
                                                ) ?>
                                            </strong>

                                            <span class="student-secondary">
                                                <?= studentDashEscape(
                                                    $class["room_type"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span
                                                class="student-status <?= studentDashEscape(
                                                    $statusClass
                                                ) ?>"
                                            >
                                                <?= studentDashEscape(
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

                    <div class="student-empty">
                        No published classes scheduled for today.
                        Check the weekly timetable for other days.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================
                 QUICK ACCESS
                 ================================= -->

            <section class="student-panel">

                <div class="student-panel-heading">

                    <div>

                        <h2>
                            Quick Access
                        </h2>

                        <p>
                            Jump straight into your academic tools.
                        </p>

                    </div>

                </div>

                <div class="student-quick-grid">

                    <a
                        href="timetable.php"
                        class="student-quick-link"
                    >

                        <div class="student-quick-icon">
                            ▦
                        </div>

                        <strong>
                            View Timetable
                        </strong>

                        <span>
                            See your subjects, classrooms,
                            faculty and weekly schedule.
                        </span>

                    </a>


                    <a
                        href="attendance.php"
                        class="student-quick-link"
                    >

                        <div class="student-quick-icon">
                            ◫
                        </div>

                        <strong>
                            My Attendance
                        </strong>

                        <span>
                            Check your attendance records
                            and subject-wise percentages.
                        </span>

                    </a>


                    <a
                        href="announcements.php"
                        class="student-quick-link"
                    >

                        <div class="student-quick-icon">
                            ◉
                        </div>

                        <strong>
                            Announcements
                        </strong>

                        <span>
                            Read the latest notices and
                            announcements from your department.
                        </span>

                    </a>

                </div>

            </section>

        </div>

    </main>

</body>
</html>
