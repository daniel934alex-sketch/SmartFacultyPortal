
<?php

date_default_timezone_set("Asia/Kolkata");

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "Attendance History";

/* ==========================================
   SAFE OUTPUT HELPERS
   ========================================== */

function historyEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function historyTime($value)
{
    $timestamp = strtotime((string)$value);

    return $timestamp !== false
        ? date("h:i A", $timestamp)
        : "—";
}

function historyDate($value)
{
    $timestamp = strtotime((string)$value);

    return $timestamp !== false
        ? date("d M Y", $timestamp)
        : "—";
}

/* ==========================================
   STUDENT AUTHENTICATION
   ========================================== */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

/* ==========================================
   GET LOGGED-IN STUDENT
   ========================================== */

$studentSql = "
    SELECT
        s.student_id,
        s.register_number,
        s.student_name,
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
   LOAD ATTENDANCE HISTORY

   Keep the original attendance -> timetable
   -> subjects -> periods relationships.
   ========================================== */

$historySql = "
    SELECT
        a.attendance_date,
        a.status,

        s.subject_code,
        s.subject_name,

        p.period_number,
        p.start_time,
        p.end_time

    FROM attendance a

    INNER JOIN timetable t
        ON a.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    INNER JOIN periods p
        ON t.period_id = p.period_id

    WHERE a.student_id = ?

    ORDER BY
        a.attendance_date DESC,
        p.period_number ASC
";

$historyStmt = $conn->prepare($historySql);

if (!$historyStmt) {
    die("Attendance history query preparation failed.");
}

$historyStmt->bind_param("i", $studentId);
$historyStmt->execute();

$historyResult = $historyStmt->get_result();

$allHistory = [];

$totalPresent = 0;
$totalAbsent = 0;

while ($row = $historyResult->fetch_assoc()) {

    $allHistory[] = $row;

    if ($row["status"] === "PRESENT") {
        $totalPresent++;
    }

    if ($row["status"] === "ABSENT") {
        $totalAbsent++;
    }
}

$historyStmt->close();

$totalRecords = count($allHistory);

/* ==========================================
   OVERALL PERCENTAGE
   ========================================== */

$overallPercentage = $totalRecords > 0
    ? round(($totalPresent / $totalRecords) * 100, 1)
    : null;

$overallDisplay = $overallPercentage === null
    ? "N/A"
    : $overallPercentage . "%";

/* ==========================================
   OPTIONAL DATE FILTER

   Uses already-loaded student-only records.
   Does not change database data.
   ========================================== */

$selectedDate = trim(
    (string)($_GET["date"] ?? "")
);

$dateFilterValid = false;

if ($selectedDate !== "") {

    $parsedDate = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $selectedDate
    );

    $dateFilterValid =
        $parsedDate !== false &&
        $parsedDate->format("Y-m-d") === $selectedDate;
}

if (!$dateFilterValid) {
    $selectedDate = "";
}

$history = [];

foreach ($allHistory as $record) {

    if (
        $selectedDate === "" ||
        $record["attendance_date"] === $selectedDate
    ) {
        $history[] = $record;
    }
}

$filteredCount = count($history);

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
        Attendance History | SmartFacultyPortal
    </title>

    <style>
    /* ==========================================
       PREMIUM STUDENT PORTAL THEME
       ATTENDANCE HISTORY ONLY
       ========================================== */

    :root {
        --bg: #0c1424;
        --sidebar: #101a2d;
        --surface: #172338;
        --border: #2b3a54;
        --text: #eef2fc;
        --muted: #9eacc6;
        --violet: #aa94ff;
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

    button,
    input {
        font: inherit;
    }

    /* ==========================================
       STUDENT SIDEBAR
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
        display: flex;
        align-items: center;
        justify-content: center;

        width: 44px;
        height: 44px;
        flex-shrink: 0;

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
       TOPBAR AND CONTENT
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

    .history-header {
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

    .history-header::after {
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

    .history-eyebrow {
        display: inline-block;
        margin-bottom: 12px;

        color: #d0c3ff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    .history-header h1 {
        margin: 0 0 10px;
        color: #ffffff;
        font-size: clamp(25px, 3vw, 34px);
        font-weight: 800;
        line-height: 1.25;
    }

    .history-header p {
        margin: 0;
        color: #e2dbf9;
        font-size: 13px;
        line-height: 1.75;
    }

    .history-header-tag {
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
       SUMMARY CARDS
       ========================================== */

    .history-stats {
        display: grid;
        grid-template-columns: repeat(
            4,
            minmax(0,1fr)
        );

        gap: 17px;
        margin-bottom: 26px;
    }

    .history-stat {
        min-width: 0;
        padding: 23px;

        border: 1px solid var(--border);
        border-radius: 16px;

        background: var(--surface);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .history-stat-label {
        margin-bottom: 13px;
        color: #b0bed5;
        font-size: 12px;
        font-weight: 700;
    }

    .history-stat-value {
        color: #ffffff;
        font-size: 29px;
        font-weight: 800;
        line-height: 1.25;
    }

    .history-stat-foot {
        margin-top: 8px;
        color: #91a1bb;
        font-size: 11px;
        line-height: 1.6;
    }

    /* ==========================================
       HISTORY PANEL
       ========================================== */

    .history-panel {
        min-width: 0;
        padding: 26px;
        margin-bottom: 25px;

        border: 1px solid var(--border);
        border-radius: 17px;

        background: var(--surface);
        box-shadow: 0 10px 28px rgba(0,0,0,.1);
    }

    .history-panel-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;

        margin-bottom: 23px;
    }

    .history-panel-heading h2 {
        margin: 0;
        color: #ffffff;
        font-size: 20px;
        font-weight: 800;
    }

    .history-panel-heading p {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    /* ==========================================
       DATE FILTER
       ========================================== */

    .history-filter {
        display: flex;
        align-items: end;
        flex-wrap: wrap;
        gap: 13px;

        margin-bottom: 23px;
        padding: 19px;

        border: 1px solid #34445e;
        border-radius: 12px;

        background: #111d30;
    }

    .history-filter-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .history-filter label {
        color: #dce5f4;
        font-size: 12px;
        font-weight: 700;
    }

    .history-filter input[type="date"] {
        min-height: 45px;
        padding: 10px 13px;

        border: 1px solid #42516a;
        border-radius: 9px;

        outline: none;

        background: #10192a;
        color: #ffffff;

        color-scheme: dark;
    }

    .history-filter input:focus {
        border-color: #aa94ff;
        box-shadow: 0 0 0 3px rgba(170,148,255,.12);
    }

    .history-filter-submit,
    .history-filter-reset {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 45px;
        padding: 11px 19px;

        border-radius: 9px;

        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .history-filter-submit {
        border: none;
        background: linear-gradient(115deg,#b8a6ff,#947cfa);
        color: #191330;
    }

    .history-filter-reset {
        border: 1px solid #4b5a74;
        background: #28364e;
        color: #e1e9f7;
    }

    /* ==========================================
       HISTORY TABLE
       ========================================== */

    .history-table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;

        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .history-table {
        width: 100%;
        min-width: 860px;

        border-collapse: collapse;
        table-layout: auto;

        color: #e3ebf8;
        font-size: 13px;
    }

    .history-table th,
    .history-table td {
        padding: 16px 18px;

        border: none;
        border-bottom: 1px solid #29374d;

        text-align: left;
        vertical-align: middle;
        line-height: 1.6;
    }

    .history-table thead th {
        background: #23334c;
        color: #e2d9ff;

        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .history-table tbody tr {
        background: #172338;
    }

    .history-table tbody tr:nth-child(even) {
        background: #1b293e;
    }

    .history-table tbody tr:hover {
        background: #253650;
    }

    .history-table tbody tr:last-child td {
        border-bottom: none;
    }

    .history-table td {
        color: #e3ebf8;
    }

    .history-table th:nth-child(1),
    .history-table td:nth-child(1) {
        white-space: nowrap;
    }

    .history-table th:nth-child(2),
    .history-table td:nth-child(2) {
        white-space: nowrap;
    }

    .history-table th:nth-child(3),
    .history-table td:nth-child(3) {
        min-width: 155px;
        white-space: nowrap;
    }

    .history-subject-code {
        display: block;
        color: #ffffff;
        font-weight: 800;
    }

    .history-subject-name {
        display: block;
        margin-top: 4px;

        color: #a8b6ce;
        font-size: 11px;
    }

    .history-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        padding: 7px 12px;
        border-radius: 100px;

        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .history-status.present {
        border: 1px solid #34695c;
        background: #1b403b;
        color: #8be4cb;
    }

    .history-status.absent {
        border: 1px solid #8a5063;
        background: #462b38;
        color: #ffb6c5;
    }

    .history-status.other {
        border: 1px solid #514783;
        background: #302a55;
        color: #ded3ff;
    }

    /* ==========================================
       EMPTY STATE
       ========================================== */

    .history-empty {
        padding: 55px 20px;
        text-align: center;
    }

    .history-empty-icon {
        margin-bottom: 14px;
        font-size: 39px;
    }

    .history-empty h3 {
        margin: 0 0 10px;

        color: #ffffff;
        font-size: 20px;
    }

    .history-empty p {
        max-width: 520px;
        margin: 0 auto;

        color: #a8b6ce;
        font-size: 13px;
        line-height: 1.75;
    }

    .history-bottom-actions {
        display: flex;
        align-items: center;
        gap: 12px;

        margin-top: 22px;
    }

    .history-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 43px;
        padding: 11px 18px;

        border: 1px solid #695e9a;
        border-radius: 9px;

        background: #2d294f;
        color: #ddd3ff;

        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
    }

    .history-back:hover {
        background: #423765;
    }

    /* ==========================================
       RESPONSIVE
       ========================================== */

    @media (max-width: 1200px) {

        .history-stats {
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

        .history-header {
            padding: 24px 20px;
        }

        .history-stats {
            grid-template-columns: 1fr;
        }

        .history-panel {
            padding: 19px;
        }

        .history-filter {
            flex-direction: column;
            align-items: stretch;
        }

        .history-filter-field,
        .history-filter input[type="date"],
        .history-filter-submit,
        .history-filter-reset {
            width: 100%;
        }
    }
    </style>

</head>

<body>

    <!-- STUDENT SIDEBAR -->

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
                    <?= historyEscape($studentName) ?>
                </strong>

                <span>
                    <?= historyEscape($registerNumber) ?>
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


    <!-- MAIN AREA -->

    <main class="student-main">

        <!-- TOPBAR -->

        <header class="student-topbar">

            <div>

                <h2 class="student-topbar-title">
                    Attendance History
                </h2>

                <span class="student-topbar-subtitle">
                    Your recorded attendance
                </span>

            </div>

            <div class="student-account">

                <div>

                    <div class="student-account-name">
                        <?= historyEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>

                </div>

                <div class="student-avatar">
                    <?= historyEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <div class="student-content">

            <!-- PAGE HEADER -->

            <section class="history-header">

                <div>

                    <span class="history-eyebrow">
                        Academic Records
                    </span>

                    <h1>
                        Attendance History
                    </h1>

                    <p>
                        <?= historyEscape($studentName) ?>

                        &nbsp; • &nbsp;

                        Section
                        <?= historyEscape($sectionName) ?>

                        &nbsp; • &nbsp;

                        Register No:
                        <?= historyEscape($registerNumber) ?>
                    </p>

                </div>

                <span class="history-header-tag">
                    My Attendance Records
                </span>

            </section>


            <!-- SUMMARY CARDS -->

            <section
                class="history-stats"
                aria-label="Attendance history summary"
            >

                <div class="history-stat">

                    <div class="history-stat-label">
                        Total Records
                    </div>

                    <div class="history-stat-value">
                        <?= $totalRecords ?>
                    </div>

                    <div class="history-stat-foot">
                        All recorded attendance entries
                    </div>

                </div>

                <div class="history-stat">

                    <div class="history-stat-label">
                        Present
                    </div>

                    <div class="history-stat-value">
                        <?= $totalPresent ?>
                    </div>

                    <div class="history-stat-foot">
                        Classes marked present
                    </div>

                </div>

                <div class="history-stat">

                    <div class="history-stat-label">
                        Absent
                    </div>

                    <div class="history-stat-value">
                        <?= $totalAbsent ?>
                    </div>

                    <div class="history-stat-foot">
                        Classes marked absent
                    </div>

                </div>

                <div class="history-stat">

                    <div class="history-stat-label">
                        Overall Attendance
                    </div>

                    <div class="history-stat-value">
                        <?= historyEscape($overallDisplay) ?>
                    </div>

                    <div class="history-stat-foot">
                        Based on all recorded entries
                    </div>

                </div>

            </section>


            <!-- ATTENDANCE RECORDS -->

            <section class="history-panel">

                <div class="history-panel-heading">

                    <div>

                        <h2>
                            Attendance Records
                        </h2>

                        <p>
                            View attendance by date, period,
                            subject and status.
                        </p>

                    </div>

                </div>

                <!-- DATE FILTER -->

                <form
                    method="GET"
                    action="attendance-history.php"
                    class="history-filter"
                >

                    <div class="history-filter-field">

                        <label for="date">
                            Filter by Date
                        </label>

                        <input
                            type="date"
                            id="date"
                            name="date"
                            value="<?= historyEscape(
                                $selectedDate
                            ) ?>"
                        >

                    </div>

                    <button
                        type="submit"
                        class="history-filter-submit"
                    >
                        Apply Filter
                    </button>

                    <a
                        href="attendance-history.php"
                        class="history-filter-reset"
                    >
                        Reset
                    </a>

                </form>


                <?php if ($filteredCount === 0): ?>

                    <div class="history-empty">

                        <div class="history-empty-icon">
                            ◫
                        </div>

                        <h3>
                            <?= $selectedDate !== ""
                                ? "No Records for This Date"
                                : "No Attendance History Yet" ?>
                        </h3>

                        <p>

                            <?php if ($selectedDate !== ""): ?>

                                No attendance entries were found
                                for
                                <?= historyEscape(
                                    historyDate($selectedDate)
                                ) ?>.

                                Try choosing another date or reset
                                the filter.

                            <?php else: ?>

                                Your attendance history will appear
                                here once faculty members start
                                recording class attendance.

                            <?php endif; ?>

                        </p>

                    </div>

                <?php else: ?>

                    <div class="history-table-scroll">

                        <table class="history-table">

                            <thead>

                                <tr>
                                    <th>Date</th>
                                    <th>Period</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($history as $record): ?>

                                    <?php

                                    $status = strtoupper(
                                        (string)$record["status"]
                                    );

                                    $statusClass = match ($status) {
                                        "PRESENT" => "present",
                                        "ABSENT" => "absent",
                                        default => "other"
                                    };

                                    ?>

                                    <tr>

                                        <td>
                                            <?= historyEscape(
                                                historyDate(
                                                    $record["attendance_date"]
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                P<?= (int)$record["period_number"] ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= historyTime(
                                                $record["start_time"]
                                            ) ?>
                                            –
                                            <?= historyTime(
                                                $record["end_time"]
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="history-subject-code">
                                                <?= historyEscape(
                                                    $record["subject_code"]
                                                ) ?>
                                            </span>

                                            <span class="history-subject-name">
                                                <?= historyEscape(
                                                    $record["subject_name"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span
                                                class="history-status <?= $statusClass ?>"
                                            >
                                                <?= historyEscape(
                                                    $status
                                                ) ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>


                <div class="history-bottom-actions">

                    <a
                        href="attendance.php"
                        class="history-back"
                    >
                        ← Back to Attendance
                    </a>

                </div>

            </section>

        </div>

    </main>

</body>
</html>
