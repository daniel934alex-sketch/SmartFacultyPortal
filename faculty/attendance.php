<?php
session_start();
require_once("../config/db.php");

/* ---------- Faculty Login ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'FACULTY' ||
    !isset($_SESSION['faculty_id'])
) {
    header("Location: ../auth/login.php");
    exit;
}

$faculty_id = (int)$_SESSION['faculty_id'];
$username = $_SESSION['username'] ?? 'Faculty';

$selected_date = $_GET['date'] ?? date('Y-m-d');
$period_id = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 1;

$message = '';
$message_type = 'success';
$timetable = null;
$students = [];
$existing_statuses = [];
$periods = [];

/* ---------- Validate Date ---------- */
$date_obj = DateTime::createFromFormat('!Y-m-d', $selected_date);

if (!$date_obj || $date_obj->format('Y-m-d') !== $selected_date) {
    $selected_date = date('Y-m-d');
    $date_obj = new DateTime($selected_date);
}

$valid_date = $selected_date <= date('Y-m-d');

/* ---------- Load Periods ---------- */
$period_result = $conn->query("
    SELECT period_id, period_number, start_time, end_time
    FROM periods
    ORDER BY period_number
");

if ($period_result) {
    while ($period = $period_result->fetch_assoc()) {
        $periods[] = $period;
    }
}

/* ---------- Find Published Timetable ---------- */
if ($valid_date && $period_id > 0) {
    $day_name = strtoupper($date_obj->format('l'));

    $stmt = $conn->prepare("
        SELECT
            t.timetable_id,
            t.section_id,
            t.subject_id,
            t.faculty_id,
            t.classroom_id,
            s.subject_name
        FROM timetable t
        LEFT JOIN subjects s ON s.subject_id = t.subject_id
        WHERE UPPER(t.day) = ?
          AND t.period_id = ?
          AND t.faculty_id = ?
          AND t.status = 'PUBLISHED'
        LIMIT 1
    ");

    $stmt->bind_param("sii", $day_name, $period_id, $faculty_id);
    $stmt->execute();
    $timetable = $stmt->get_result()->fetch_assoc();
}

/* ---------- Load Students and Saved Attendance ---------- */
if ($timetable) {
    $timetable_id = (int)$timetable['timetable_id'];
    $section_id = (int)$timetable['section_id'];

    $student_stmt = $conn->prepare("
        SELECT student_id, register_number, student_name, section_id
        FROM students
        WHERE section_id = ?
        ORDER BY student_id
    ");

    $student_stmt->bind_param("i", $section_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();

    while ($student = $student_result->fetch_assoc()) {
        $students[] = $student;
    }

    $status_stmt = $conn->prepare("
        SELECT student_id, status
        FROM attendance
        WHERE timetable_id = ?
          AND attendance_date = ?
    ");

    $status_stmt->bind_param("is", $timetable_id, $selected_date);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();

    while ($row = $status_result->fetch_assoc()) {
        $existing_statuses[(int)$row['student_id']] =
            strtoupper($row['status']);
    }
}

/* ---------- Save Attendance ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$valid_date) {
        $message = 'Future date attendance cannot be marked.';
        $message_type = 'error';

    } elseif (!$timetable) {
        $message = 'No published timetable found for this faculty, date and period.';
        $message_type = 'error';

    } elseif (empty($students)) {
        $message = 'No students found for this section.';
        $message_type = 'error';

    } elseif (
        !isset($_POST['attendance']) ||
        !is_array($_POST['attendance'])
    ) {
        $message = 'Please select attendance for all students.';
        $message_type = 'error';

    } else {
        $attendance_data = $_POST['attendance'];
        $allowed_ids = [];

        foreach ($students as $student) {
            $allowed_ids[] = (int)$student['student_id'];
        }

        $valid_submission = count($attendance_data) === count($students);

        foreach ($attendance_data as $student_id => $status) {
            if (
                !ctype_digit((string)$student_id) ||
                !in_array((int)$student_id, $allowed_ids, true) ||
                !in_array($status, ['PRESENT', 'ABSENT', 'OD'], true)
            ) {
                $valid_submission = false;
                break;
            }
        }

        if (!$valid_submission) {
            $message = 'Invalid attendance data. Please reload the page.';
            $message_type = 'error';

        } else {
            try {
                $conn->begin_transaction();

                $check_stmt = $conn->prepare("
                    SELECT attendance_id
                    FROM attendance
                    WHERE timetable_id = ?
                      AND student_id = ?
                      AND attendance_date = ?
                    LIMIT 1
                ");

                $update_stmt = $conn->prepare("
                    UPDATE attendance
                    SET status = ?, faculty_id = ?, marked_at = NOW()
                    WHERE attendance_id = ?
                ");

                $insert_stmt = $conn->prepare("
                    INSERT INTO attendance
                    (
                        timetable_id,
                        student_id,
                        faculty_id,
                        attendance_date,
                        status,
                        marked_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                foreach ($students as $student) {
                    $student_id = (int)$student['student_id'];
                    $status = $attendance_data[$student_id];

                    $check_stmt->bind_param(
                        "iis",
                        $timetable_id,
                        $student_id,
                        $selected_date
                    );

                    $check_stmt->execute();
                    $found = $check_stmt->get_result()->fetch_assoc();

                    if ($found) {
                        $attendance_id = (int)$found['attendance_id'];

                        $update_stmt->bind_param(
                            "sii",
                            $status,
                            $faculty_id,
                            $attendance_id
                        );

                        $update_stmt->execute();

                    } else {
                        $insert_stmt->bind_param(
                            "iiiss",
                            $timetable_id,
                            $student_id,
                            $faculty_id,
                            $selected_date,
                            $status
                        );

                        $insert_stmt->execute();
                    }
                }

                $conn->commit();

                $message = 'Attendance saved successfully!';
                $message_type = 'success';

                /* Refresh saved statuses */
                $existing_statuses = [];

                $status_stmt = $conn->prepare("
                    SELECT student_id, status
                    FROM attendance
                    WHERE timetable_id = ?
                      AND attendance_date = ?
                ");

                $status_stmt->bind_param(
                    "is",
                    $timetable_id,
                    $selected_date
                );

                $status_stmt->execute();
                $status_result = $status_stmt->get_result();

                while ($row = $status_result->fetch_assoc()) {
                    $existing_statuses[(int)$row['student_id']] =
                        strtoupper($row['status']);
                }

            } catch (Throwable $e) {
                $conn->rollback();
                error_log($e->getMessage());

                $message = 'Unable to save attendance. Please check the database configuration.';
                $message_type = 'error';
            }
        }
    }
}

/* ---------- Attendance Counts ---------- */
$total_students = count($students);
$present_count = 0;
$absent_count = 0;
$od_count = 0;

foreach ($students as $student) {
    $sid = (int)$student['student_id'];
    $status = $existing_statuses[$sid] ?? 'PRESENT';

    if ($status === 'PRESENT') {
        $present_count++;
    } elseif ($status === 'ABSENT') {
        $absent_count++;
    } elseif ($status === 'OD') {
        $od_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Attendance | Smart Faculty</title>
    <!-- Shared dashboard design tokens, sidebar and topbar. -->
    <link rel="stylesheet" href="../assets/css/style.css?v=dark4">
    <style>
        /* Attendance-specific styles; shared dashboard CSS stays untouched. */
        .fd-dark .fa-main { padding-top: 28px; }
        .fd-dark .fa-hero { min-height: 215px; padding: 34px 40px; }
        .fd-dark .fa-hero h1 { font-size: clamp(23px, 2.8vw, 31px); }
        .fd-dark .fa-hero .fa-hero-art { flex-basis: 130px; width: 130px; height: 130px; margin-right: 20px; }
        .fd-dark .fa-hero-art .fd-art-center { inset: 25px; border-radius: 22px; }
        .fd-dark .fa-hero-art .fd-art-center svg { width: 42px; height: 42px; }
        .fd-dark .fa-section { margin-top: 28px; }
        .fd-dark .fa-section-header { display:flex; align-items:end; justify-content:space-between; gap:14px; margin-bottom:17px; }
        .fd-dark .fa-section-header h2 { margin:7px 0; font-size:20px; color:#f6f7ff; }
        .fd-dark .fa-section-header p { margin:0; font-size:12px; color:var(--fd-muted); line-height:1.7; }
        .fd-dark .fa-eyebrow { font-size:10px; letter-spacing:1.5px; font-weight:800; color:#b6a5ff; }
        .fd-dark .fa-panel { background:var(--fd-panel); border:1px solid var(--fd-border); border-radius:17px; padding:26px; }
        .fd-dark .fa-filters { display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr) auto; gap:17px; align-items:end; }
        .fd-dark .fa-field { min-width:0; }
        .fd-dark .fa-field-label { display:block; font-size:12px; color:#d8def0; font-weight:700; margin:0 0 10px; }
        .fd-dark .fa-field-label span { color:#a690ff; margin-right:6px; }
        .fd-dark .fa-field-control { display:block; width:100%; min-height:47px; padding:0 14px; border:1px solid #35455f; border-radius:10px; background:#111a2b; color:#f1f5ff; font:500 13px Inter,'Segoe UI',Arial,sans-serif; outline:none; color-scheme:dark; }
        .fd-dark .fa-field-control:focus { border-color:#9a83ff; box-shadow:0 0 0 3px rgba(154,131,255,.17); }
        .fd-dark .fa-field-control option { background:#111a2b; color:#f1f5ff; }
        .fd-dark .fa-button { display:inline-flex; justify-content:center; align-items:center; gap:9px; min-height:47px; padding:12px 19px; border:0; border-radius:10px; background:#b0a0ff; color:#211938; cursor:pointer; text-decoration:none; font-size:12px; font-weight:800; font-family:inherit; white-space:nowrap; transition:background .2s, transform .2s; }
        .fd-dark .fa-button:hover { background:#cec3ff; transform:translateY(-1px); }
        .fd-dark .fa-button:focus-visible { outline:3px solid #d9d0ff; outline-offset:3px; }
        .fd-dark .fa-button svg { width:17px; height:17px; }
        .fd-dark .fa-filter-note { display:flex; align-items:center; gap:9px; margin:17px 0 0; color:#899ab4; font-size:11px; line-height:1.6; }
        .fd-dark .fa-filter-note svg { width:16px; height:16px; color:#b6a5ff; flex:0 0 auto; }
        .fd-dark .fa-alert { display:flex; align-items:start; gap:10px; margin-bottom:18px; padding:13px 15px; border:1px solid; border-radius:10px; font-size:12px; line-height:1.65; }
        .fd-dark .fa-alert svg { flex:0 0 18px; width:18px; height:18px; }
        .fd-dark .fa-alert.success { background:#17352e; color:#adf0cd; border-color:#285c49; }
        .fd-dark .fa-alert.error { background:#3c252f; color:#ffc4cc; border-color:#724354; }
        .fd-dark .fa-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; min-height:260px; padding:42px 22px; }
        .fd-dark .fa-empty-icon { display:grid; place-items:center; width:65px; height:65px; border-radius:18px; border:1px solid #50477d; background:#312c55; color:#bbabff; margin-bottom:20px; }
        .fd-dark .fa-empty-icon svg { width:31px; height:31px; }
        .fd-dark .fa-empty-kicker { color:#b7a7ff; font-size:10px; font-weight:800; letter-spacing:1.5px; }
        .fd-dark .fa-empty h2 { font-size:21px; margin:11px 0 8px; color:#f5f6ff; }
        .fd-dark .fa-empty p { max-width:540px; margin:5px auto 0; font-size:13px; line-height:1.8; color:var(--fd-muted); }
        .fd-dark .fa-empty .fa-empty-detail { font-size:11px; color:#7386a3; }
        .fd-dark .fa-class-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
        .fd-dark .fa-class-header h2 { margin:0; font-size:17px; color:#f5f6ff; }
        .fd-dark .fa-published { display:inline-flex; gap:7px; align-items:center; padding:7px 11px; border:1px solid #325f50; border-radius:25px; color:#9be8c7; background:#1d3c34; font-weight:800; font-size:10px; letter-spacing:.5px; }
        .fd-dark .fa-published::before { content:''; width:7px; height:7px; background:#56d7a0; border-radius:50%; }
        .fd-dark .fa-details-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .fd-dark .fa-detail { background:#111b2e; border:1px solid #2a384f; border-radius:12px; padding:15px 17px; min-width:0; }
        .fd-dark .fa-detail-label { display:block; color:#8596b0; font-size:10px; font-weight:750; letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; }
        .fd-dark .fa-detail-value { display:block; color:#f4f4ff; font-size:13px; font-weight:650; overflow-wrap:anywhere; }
        .fd-dark .fa-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin:18px 0 24px; }
        .fd-dark .fa-stat { padding:19px 20px; border:1px solid var(--fd-border); border-radius:14px; background:var(--fd-panel); min-width:0; }
        .fd-dark .fa-stat-top { display:flex; justify-content:space-between; align-items:center; gap:8px; }
        .fd-dark .fa-stat-label { color:#a6b4c9; font-size:11px; font-weight:700; }
        .fd-dark .fa-stat-icon { display:grid; place-items:center; flex:0 0 34px; width:34px; height:34px; border-radius:10px; background:#312c54; color:#b7a7ff; }
        .fd-dark .fa-stat-icon svg { width:18px; height:18px; }
        .fd-dark .fa-stat strong { display:block; font-size:29px; line-height:1; margin-top:16px; color:#f7f7ff; }
        .fd-dark .fa-stat.fa-present .fa-stat-icon { background:#1c3d35; color:#76dbaf; }
        .fd-dark .fa-stat.fa-present strong { color:#83e7b8; }
        .fd-dark .fa-stat.fa-absent .fa-stat-icon { background:#422936; color:#f4a5b5; }
        .fd-dark .fa-stat.fa-absent strong { color:#ffb4c2; }
        .fd-dark .fa-stat.fa-od .fa-stat-icon { background:#423a2a; color:#edd393; }
        .fd-dark .fa-stat.fa-od strong { color:#f5d58d; }
        .fd-dark .fa-table-header { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:17px; }
        .fd-dark .fa-table-header h2 { margin:0 0 6px; font-size:18px; color:#f6f7ff; }
        .fd-dark .fa-table-header p { margin:0; color:var(--fd-muted); font-size:12px; line-height:1.7; }
        .fd-dark .fa-table-count { color:#c7baff; background:#302a4e; border:1px solid #504374; border-radius:8px; padding:8px 11px; font-size:11px; font-weight:800; }
        .fd-dark .fa-table-scroll { overflow-x:auto; border:1px solid var(--fd-border); border-radius:12px; }
        .fd-dark .fa-table { width:100%; min-width:730px; border-collapse:collapse; }
        .fd-dark .fa-table thead { background:#111a2b; }
        .fd-dark .fa-table th { text-align:left; padding:14px 16px; color:#98a7c0; font-size:10px; letter-spacing:.7px; font-weight:800; text-transform:uppercase; }
        .fd-dark .fa-table td { padding:15px 16px; border-top:1px solid #2a364b; color:#dce5f7; font-size:12px; }
        .fd-dark .fa-table tbody tr:hover { background:#1b2940; }
        .fd-dark .fa-row-number { color:#8496b3; font-size:11px; }
        .fd-dark .fa-reg { font-family:Consolas,Monaco,monospace; font-size:12px; color:#c5b7ff; font-weight:650; }
        .fd-dark .fa-student-name { color:#f2f4ff; font-weight:700; }
        .fd-dark .fa-attendance-options { display:flex; gap:7px; flex-wrap:wrap; }
        .fd-dark .fa-attendance-option { position:relative; display:inline-flex; align-items:center; justify-content:center; min-width:91px; padding:9px 12px; border:1px solid #3a4961; border-radius:8px; background:#18263a; color:#a8b5c9; font-size:11px; font-weight:750; cursor:pointer; transition:background .15s,border-color .15s,color .15s; }
        .fd-dark .fa-attendance-option input { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }
        .fd-dark .fa-attendance-option:focus-within { outline:2px solid #b5a4ff; outline-offset:2px; }
        .fd-dark .fa-attendance-option:hover { background:#263651; }
        .fd-dark .fa-attendance-option.is-selected.present-option { border-color:#428a71; background:#21473b; color:#9cf1c8; }
        .fd-dark .fa-attendance-option.is-selected.absent-option { border-color:#965466; background:#4a2c39; color:#ffd2da; }
        .fd-dark .fa-attendance-option.is-selected.od-option { border-color:#847047; background:#433a2c; color:#ffe5a5; }
        .fd-dark .fa-save-row { display:flex; justify-content:space-between; align-items:center; gap:18px; flex-wrap:wrap; margin-top:20px; }
        .fd-dark .fa-save-note { max-width:530px; color:#8d9db6; font-size:11px; line-height:1.8; }
        .fd-dark .fa-save-note strong { color:#c5b7ff; }
        @media(max-width:1060px) {
            .fd-dark .fa-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .fd-dark .fa-filters button { grid-column:1/-1; justify-self:start; }
            .fd-dark .fa-details-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .fd-dark .fa-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
        @media(max-width:760px) {
            .fd-dark .fa-main { padding-top:18px; }
            .fd-dark .fa-hero { padding:27px 25px; }
            .fd-dark .fa-panel { padding:19px; }
            .fd-dark .fa-filters { grid-template-columns:1fr; gap:14px; }
            .fd-dark .fa-filters button { grid-column:auto; justify-self:stretch; }
            .fd-dark .fa-details-grid { grid-template-columns:1fr; }
            .fd-dark .fa-stats { gap:10px; }
            .fd-dark .fa-stat { padding:16px 13px; }
            .fd-dark .fa-stat strong { font-size:25px; }
            .fd-dark .fa-section-header { align-items:start; }
            .fd-dark .fa-table-header { align-items:start; }
        }
        @media(max-width:420px) {
            .fd-dark .fa-stats { grid-template-columns:1fr 1fr; }
            .fd-dark .fa-attendance-option { min-width:85px; padding:8px 9px; }
        }
    </style>
</head>
<body class="fd-dark">
<div class="fd-layout">
    <aside class="fd-sidebar" id="faculty-sidebar">
        <a href="dashboard.php" class="fd-brand">
            <span class="fd-brand-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m2 10 10-5 10 5-10 5-10-5Z"/>
                    <path d="M6 12v5c4 3 8 3 12 0v-5"/>
                    <path d="M22 10v7"/>
                </svg>
            </span>
            <span><strong>Smart Faculty</strong><small>ASSISTANT PORTAL</small></span>
        </a>

        <div class="fd-sidebar-label">MAIN MENU</div>
        <nav class="fd-nav" aria-label="Faculty navigation">
            <a href="dashboard.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="timetable.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h3M14 14h3M8 18h3"/></svg>
                <span>My Timetable</span>
            </a>
            <a href="attendance.php" class="fd-nav-link active" aria-current="page">
                <svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h5M8 16h4"/></svg>
                <span>Mark Attendance</span>
            </a>
            <a href="notifications.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                <span>Notifications</span>
            </a>
            <a href="profile.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                <span>My Profile</span>
            </a>

            <div class="fd-sidebar-label fd-nav-section">FACULTY MANAGEMENT</div>
            <a href="availability.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>Faculty Availability</span>
            </a>
            <a href="requests.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24"><path d="M4 7h16l-4-4M20 7l-4 4"/><path d="M20 17H4l4-4M4 17l4 4"/></svg>
                <span>Handover Requests</span>
            </a>
        </nav>

        <div class="fd-sidebar-bottom">
            <div class="fd-side-help">
                <span class="fd-help-icon"></span>
                <strong>Your teaching hub</strong>
                <p>Everything you need for your academic day.</p>
            </div>
            <a href="../auth/logout.php" class="fd-side-logout">
                <svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></svg>
                Logout
            </a>
        </div>
    </aside>
    <div class="fd-content">
        <header class="fd-topbar">
            <div class="fd-topbar-left">
                <button type="button" id="fd-menu-button" class="fd-menu-button" aria-label="Toggle navigation" aria-controls="faculty-sidebar" aria-expanded="false">☰</button>
                <div>
                    <span class="fd-page-label">ACADEMIC RECORDS</span>
                    <h2>Faculty Attendance</h2>
                </div>
            </div>
            <div class="fd-topbar-user">
                <div class="fd-user-avatar"><?= htmlspecialchars(strtoupper(substr((string)$username, 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="fd-user-details">
                    <strong><?= htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>Faculty Member</span>
                </div>
            </div>
        </header>

        <main class="fd-main fa-main">
            <section class="fd-hero fa-hero">
                <div class="fd-hero-content">
                    <span class="fd-hero-eyebrow">FACULTY WORKSPACE / ATTENDANCE</span>
                    <h1>Attendance, made simple.</h1>
                    <p>Pick a date and class period, review your student list, and record attendance in one place.</p>
                    <div class="fd-hero-actions">
                        <a class="fd-btn-primary" href="timetable.php">View My Timetable <span aria-hidden="true">→</span></a>
                        <a class="fd-btn-outline" href="dashboard.php">Back to Dashboard</a>
                    </div>
                </div>
                <div class="fd-hero-art fa-hero-art" aria-hidden="true">
                    <div class="fd-orbit fd-orbit-one"></div>
                    <div class="fd-orbit fd-orbit-two"></div>
                    <div class="fd-art-center">
                        <svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16l2 2 4-4"/></svg>
                    </div>
                </div>
            </section>

            <section class="fa-section">
                <div class="fa-section-header">
                    <div>
                        <span class="fa-eyebrow">SELECT CLASS</span>
                        <h2>Choose Attendance Session</h2>
                        <p>Select a date and teaching period from your published timetable.</p>
                    </div>
                </div>
                <div class="fa-panel">
                    <?php if ($message !== ''): ?>
                        <div class="fa-alert <?= $message_type === 'success' ? 'success' : 'error' ?>" role="status">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!$valid_date): ?>
                        <div class="fa-alert error" role="alert">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                            <span>Future date attendance cannot be marked.</span>
                        </div>
                    <?php endif; ?>
                    <form class="fa-filters" method="GET">
                        <div class="fa-field">
                            <label class="fa-field-label" for="date"><span>01</span> Attendance Date</label>
                            <input class="fa-field-control" type="date" id="date" name="date"
                                   value="<?= htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8') ?>"
                                   max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="fa-field">
                            <label class="fa-field-label" for="period_id"><span>02</span> Class Period</label>
                            <select class="fa-field-control" name="period_id" id="period_id" required>
                                <?php foreach ($periods as $period): ?>
                                    <option value="<?= (int)$period['period_id'] ?>" <?= (int)$period['period_id'] === $period_id ? 'selected' : '' ?>>
                                        Period <?= (int)$period['period_number'] ?> (<?= htmlspecialchars($period['start_time'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($period['end_time'], ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="fa-button" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                            Load Class
                        </button>
                    </form>
                    <p class="fa-filter-note">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                        Attendance can be recorded for today or an earlier date, for published classes only.
                    </p>
                </div>
            </section>

            <?php if (!$valid_date): ?>
                <section class="fa-section fa-panel fa-empty" aria-live="polite">
                    <div class="fa-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M9 15l6 0"/></svg>
                    </div>
                    <span class="fa-empty-kicker">DATE NOT AVAILABLE</span>
                    <h2>Choose an Earlier Date</h2>
                    <p>Please select today or a previous date to mark attendance.</p>
                </section>
            <?php elseif (!$timetable): ?>
                <section class="fa-section fa-panel fa-empty" aria-live="polite">
                    <div class="fa-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 15h8M8 18h5"/></svg>
                    </div>
                    <span class="fa-empty-kicker">NO CLASS AVAILABLE</span>
                    <h2>No Published Class Found</h2>
                    <p>There isn’t a published class for the selected day and period in your teaching schedule.</p>
                    <p class="fa-empty-detail">Try another period, or check the day, faculty assignment and PUBLISHED status in your timetable.</p>
                </section>
            <?php elseif (empty($students)): ?>
                <section class="fa-section fa-panel fa-empty" aria-live="polite">
                    <div class="fa-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 8a3 3 0 0 1 0 6M17 17a5 5 0 0 1 4 3"/></svg>
                    </div>
                    <span class="fa-empty-kicker">CLASS LIST EMPTY</span>
                    <h2>No Students Found</h2>
                    <p>There are no students currently assigned to this class section.</p>
                </section>
            <?php else: ?>
                <section class="fa-section fa-panel">
                    <div class="fa-class-header">
                        <h2>Class Details</h2>
                        <span class="fa-published">PUBLISHED CLASS</span>
                    </div>
                    <?php
                        $selected_period_number = $period_id;
                        foreach ($periods as $period) {
                            if ((int)$period['period_id'] === $period_id) {
                                $selected_period_number = (int)$period['period_number'];
                                break;
                            }
                        }
                    ?>
                    <div class="fa-details-grid">
                        <div class="fa-detail"><span class="fa-detail-label">Subject</span><span class="fa-detail-value"><?= htmlspecialchars($timetable['subject_name'] ?? 'Subject', ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div class="fa-detail"><span class="fa-detail-label">Section</span><span class="fa-detail-value">Section <?= (int)$timetable['section_id'] ?></span></div>
                        <div class="fa-detail"><span class="fa-detail-label">Period</span><span class="fa-detail-value">Period <?= (int)$selected_period_number ?></span></div>
                        <div class="fa-detail"><span class="fa-detail-label">Attendance Date</span><span class="fa-detail-value"><?= htmlspecialchars($date_obj->format('D, d M Y'), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div class="fa-detail"><span class="fa-detail-label">Classroom</span><span class="fa-detail-value">Room ID <?= (int)$timetable['classroom_id'] ?></span></div>
                        <div class="fa-detail"><span class="fa-detail-label">Teaching Day</span><span class="fa-detail-value"><?= htmlspecialchars($date_obj->format('l'), ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                </section>

                <section class="fa-stats" aria-label="Attendance summary" aria-live="polite">
                    <div class="fa-stat">
                        <div class="fa-stat-top"><span class="fa-stat-label">Total Students</span><span class="fa-stat-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 8a3 3 0 0 1 0 6M17 17a5 5 0 0 1 4 3"/></svg></span></div>
                        <strong><?= $total_students ?></strong>
                    </div>
                    <div class="fa-stat fa-present">
                        <div class="fa-stat-top"><span class="fa-stat-label">Present</span><span class="fa-stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></span></div>
                        <strong id="presentCount"><?= $present_count ?></strong>
                    </div>
                    <div class="fa-stat fa-absent">
                        <div class="fa-stat-top"><span class="fa-stat-label">Absent</span><span class="fa-stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg></span></div>
                        <strong id="absentCount"><?= $absent_count ?></strong>
                    </div>
                    <div class="fa-stat fa-od">
                        <div class="fa-stat-top"><span class="fa-stat-label">On Duty (OD)</span><span class="fa-stat-icon"><svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span></div>
                        <strong id="odCount"><?= $od_count ?></strong>
                    </div>
                </section>

                <section class="fa-section" aria-label="Student attendance">
                    <form method="POST" action="?date=<?= urlencode($selected_date) ?>&amp;period_id=<?= $period_id ?>"
                          class="fa-panel" onsubmit="return confirm('Save attendance for this class and period?');">
                        <div class="fa-table-header">
                            <div>
                                <h2>Student Attendance</h2>
                                <p>Students default to Present. Select Absent or OD where needed.</p>
                            </div>
                            <span class="fa-table-count"><?= $total_students ?> STUDENTS</span>
                        </div>
                        <div class="fa-table-scroll">
                            <table class="fa-table">
                                <thead>
                                    <tr><th scope="col">#</th><th scope="col">Register Number</th><th scope="col">Student Name</th><th scope="col">Attendance Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php
                                            $sid = (int)$student['student_id'];
                                            $status = $existing_statuses[$sid] ?? 'PRESENT';
                                            $options = [
                                                'PRESENT' => ['✓', 'Present', 'present-option'],
                                                'ABSENT' => ['✕', 'Absent', 'absent-option'],
                                                'OD' => ['▣', 'OD', 'od-option']
                                            ];
                                        ?>
                                        <tr>
                                            <td class="fa-row-number"><?= $index + 1 ?></td>
                                            <td><span class="fa-reg"><?= htmlspecialchars($student['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td><span class="fa-student-name"><?= htmlspecialchars($student['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td>
                                                <div class="fa-attendance-options" role="group" aria-label="Attendance for <?= htmlspecialchars($student['student_name'] ?? ('student ' . $sid), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php foreach ($options as $value => $option): ?>
                                                        <label class="fa-attendance-option <?= $option[2] ?> <?= $status === $value ? 'is-selected' : '' ?>">
                                                            <input class="status-select" type="radio" name="attendance[<?= $sid ?>]" value="<?= $value ?>"
                                                                   <?= $status === $value ? 'checked' : '' ?> onchange="updateCounts()" required>
                                                            <?= $option[0] ?> &nbsp;<?= $option[1] ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="fa-save-row">
                            <p class="fa-save-note"><strong>Remember:</strong> Each class period has separate attendance records. Saving updates only this date and period.</p>
                            <button class="fa-button" type="submit">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h13l3 3v13H4zM8 4v6h8V4M8 20v-7h8v7"/></svg>
                                Save Attendance
                            </button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <footer class="fd-footer"><span>© <?= date('Y') ?> Smart Faculty Assistant Portal</span><span>Faculty Workspace / Attendance</span></footer>
        </main>
    </div>
</div>
<script>
(function () {
    const menuButton = document.getElementById('fd-menu-button');
    const sidebar = document.getElementById('faculty-sidebar');
    if (menuButton && sidebar) {
        menuButton.addEventListener('click', function () {
            const open = sidebar.classList.toggle('fd-open');
            menuButton.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                sidebar.classList.remove('fd-open');
                menuButton.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();

function updateCounts() {
    const rows = document.querySelectorAll('.fa-table tbody tr');
    let present = 0, absent = 0, od = 0;
    rows.forEach(function (row) {
        const selected = row.querySelector('input.status-select:checked');
        row.querySelectorAll('.fa-attendance-option').forEach(function (label) {
            label.classList.remove('is-selected');
        });
        if (!selected) return;
        selected.closest('.fa-attendance-option').classList.add('is-selected');
        if (selected.value === 'PRESENT') present++;
        else if (selected.value === 'ABSENT') absent++;
        else if (selected.value === 'OD') od++;
    });
    const presentElement = document.getElementById('presentCount');
    const absentElement = document.getElementById('absentCount');
    const odElement = document.getElementById('odCount');
    if (presentElement) presentElement.textContent = present;
    if (absentElement) absentElement.textContent = absent;
    if (odElement) odElement.textContent = od;
}
updateCounts();
</script>
</body>
</html>
