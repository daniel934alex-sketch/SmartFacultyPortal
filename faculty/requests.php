<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FACULTY') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int) $_SESSION['user_id'];

/* GET CURRENT FACULTY */

$stmt = $conn->prepare("
    SELECT faculty_id, faculty_name, faculty_code
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Faculty record not found.");
}

$faculty = $result->fetch_assoc();
$faculty_id = (int)$faculty['faculty_id'];
$stmt->close();

/* GET HANDOVER REQUESTS - ORIGINAL QUERY */

$sql = "
    SELECT
        hr.request_id,
        hr.handover_id,
        hr.status AS request_status,
        hr.requested_at,
        hr.responded_at,
        ch.reason,
        ch.status AS handover_status,
        t.timetable_id,
        t.day,
        t.period_id,
        t.section_id,
        t.subject_id,
        t.classroom_id,
        t.status AS timetable_status,
        original_faculty.faculty_name AS original_faculty_name,
        original_faculty.faculty_code AS original_faculty_code
    FROM handover_requests hr
    INNER JOIN class_handover ch
        ON ch.handover_id = hr.handover_id
    INNER JOIN timetable t
        ON t.timetable_id = ch.timetable_id
    INNER JOIN faculty original_faculty
        ON original_faculty.faculty_id = ch.original_faculty_id
    WHERE hr.alternate_faculty_id = ?
    ORDER BY hr.requested_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();
$requests = [];

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$stmt->close();

/* OUTGOING HANDOVER REQUESTS - requests sent BY this faculty */
$outgoingSql = "
    SELECT
        hr.request_id,
        hr.handover_id,
        hr.status AS request_status,
        hr.requested_at,
        hr.responded_at,
        ch.reason,
        ch.status AS handover_status,
        t.day,
        t.period_id,
        t.section_id,
        t.subject_id,
        t.classroom_id,
        alternate_faculty.faculty_name AS alternate_faculty_name,
        alternate_faculty.faculty_code AS alternate_faculty_code
    FROM class_handover ch
    INNER JOIN handover_requests hr
        ON hr.handover_id = ch.handover_id
    INNER JOIN timetable t
        ON t.timetable_id = ch.timetable_id
    INNER JOIN faculty alternate_faculty
        ON alternate_faculty.faculty_id = hr.alternate_faculty_id
    WHERE ch.original_faculty_id = ?
    ORDER BY hr.requested_at DESC, hr.request_id DESC
";

$outgoingStmt = $conn->prepare($outgoingSql);
$outgoingStmt->bind_param("i", $faculty_id);
$outgoingStmt->execute();
$outgoingResult = $outgoingStmt->get_result();
$sentRequests = [];
while ($sentRow = $outgoingResult->fetch_assoc()) {
    $sentRequests[] = $sentRow;
}
$outgoingStmt->close();
$conn->close();

/* DISPLAY HELPERS */

function reqEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$username = reqEscape($_SESSION['username'] ?? 'Faculty');
$initial = strtoupper(substr($username, 0, 1));

$total = count($requests);
$pending = 0;
$accepted = 0;
$rejected = 0;

foreach ($requests as $request) {
    $status = strtoupper((string)$request['request_status']);

    if ($status === 'PENDING') $pending++;
    if ($status === 'ACCEPTED') $accepted++;
    if ($status === 'REJECTED') $rejected++;
}

$sentTotal = count($sentRequests);
$sentPending = 0;
$sentAccepted = 0;
$sentRejected = 0;
foreach ($sentRequests as $sentRequest) {
    $sentStatus = strtoupper((string)$sentRequest['request_status']);
    if ($sentStatus === 'PENDING') $sentPending++;
    if ($sentStatus === 'ACCEPTED') $sentAccepted++;
    if ($sentStatus === 'REJECTED') $sentRejected++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Handover Requests | Smart Faculty</title>

<style>
/* ======================================
   FACULTY HANDOVER - PREMIUM DARK THEME
   Scoped to requests.php only
   ====================================== */

html {
    scrollbar-gutter: stable;
    overflow-y: scroll;
}

body.hr-page {
    --hr-bg: #0c1220;
    --hr-sidebar: #111a2b;
    --hr-card: #172235;
    --hr-border: #2a364b;
    --hr-text: #f1f5ff;
    --hr-muted: #96a4bc;
    --hr-accent: #b5a3ff;

    margin: 0;
    min-height: 100vh;
    background: var(--hr-bg);
    color: var(--hr-text);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.hr-page *,
.hr-page *::before,
.hr-page *::after {
    box-sizing: border-box;
}

.hr-page a {
    text-decoration: none;
}

.hr-page svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* LAYOUT */

.hr-page .hr-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.hr-page .hr-sidebar {
    position: sticky;
    top: 0;
    flex: 0 0 264px;
    width: 264px;
    min-width: 264px;
    max-width: 264px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    height: 100vh;
    padding: 28px 17px 22px;
    background: var(--hr-sidebar);
    border-right: 1px solid var(--hr-border);
    overflow-y: auto;
    z-index: 30;
}

.hr-page .hr-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 7px 45px;
    color: #fff;
}

.hr-page .hr-brand-logo {
    display: grid;
    place-items: center;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: linear-gradient(135deg, #a58aff, #6550cc);
}

.hr-page .hr-brand-logo svg {
    width: 27px;
    height: 27px;
}

.hr-page .hr-brand strong {
    display: block;
    font-size: 15px;
}

.hr-page .hr-brand small {
    display: block;
    margin-top: 5px;
    color: #9dabc2;
    font-size: 9px;
    letter-spacing: 1.5px;
}

.hr-page .hr-sidebar-label {
    padding: 0 14px;
    margin-bottom: 15px;
    color: #687991;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.8px;
}

.hr-page .hr-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.hr-page .hr-nav-section {
    margin: 27px 0 15px;
}

.hr-page .hr-nav-link {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    border: 1px solid transparent;
    border-radius: 11px;
    color: #98a6bc;
    font-size: 13px;
    font-weight: 550;
    transition: .2s ease;
}

.hr-page .hr-nav-link:hover {
    color: #fff;
    background: #202c41;
}

.hr-page .hr-nav-link.active {
    color: #e2d9ff;
    background: #302b53;
    border-color: #50467c;
    font-weight: 700;
}

.hr-page .hr-nav-link svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.hr-page .hr-sidebar-bottom {
    margin-top: auto;
    padding-top: 35px;
}

.hr-page .hr-side-help {
    padding: 18px;
    border: 1px solid #3b375f;
    border-radius: 14px;
    background: linear-gradient(145deg, #2b294e, #1a2440);
}

.hr-page .hr-side-help strong {
    display: block;
    color: #fff;
    font-size: 13px;
}

.hr-page .hr-side-help p {
    margin: 9px 0 0;
    color: #a7b0ca;
    font-size: 11px;
    line-height: 1.7;
}

.hr-page .hr-side-logout {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    padding: 13px 14px;
    border-radius: 10px;
    color: #f1a4b1;
    font-size: 13px;
}

.hr-page .hr-side-logout:hover {
    background: #422536;
}

/* TOP HEADER */

.hr-page .hr-content {
    flex: 1;
    min-width: 0;
}

.hr-page .hr-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    min-height: 91px;
    padding: 17px 35px;
    background: #111a2b;
    border-bottom: 1px solid var(--hr-border);
}

.hr-page .hr-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hr-page .hr-page-label {
    color: #9983ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.hr-page .hr-topbar h2 {
    margin: 5px 0 0;
    color: #f4f5ff;
    font-size: 20px;
}

.hr-page .hr-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hr-page .hr-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #393060;
    color: #e8deff;
    font-weight: 800;
}

.hr-page .hr-user-info strong {
    display: block;
    font-size: 13px;
}

.hr-page .hr-user-info span {
    display: block;
    margin-top: 5px;
    color: var(--hr-muted);
    font-size: 11px;
}

.hr-page .hr-menu-button {
    display: none;
    padding: 9px 12px;
    border: 1px solid var(--hr-border);
    border-radius: 9px;
    background: #263248;
    color: white;
    cursor: pointer;
}

/* MAIN AREA */

.hr-page .hr-main {
    width: min(1230px, 100%);
    margin: 0 auto;
    padding: 34px;
    box-sizing: border-box;
}

/* HERO */

.hr-page .hr-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    min-height: 225px;
    padding: 36px 42px;
    border: 1px solid #51478c;
    border-radius: 21px;
    background: linear-gradient(115deg, #28244e, #39316e 55%, #5c4da0);
    overflow: hidden;
}

.hr-page .hr-eyebrow {
    color: #bfb0ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.7px;
}

.hr-page .hr-hero h1 {
    margin: 14px 0 12px;
    color: #fff;
    font-size: clamp(26px, 3vw, 36px);
}

.hr-page .hr-hero p {
    margin: 0;
    max-width: 560px;
    color: #d2cfea;
    font-size: 13px;
    line-height: 1.8;
}

.hr-page .hr-back {
    display: inline-flex;
    margin-top: 23px;
    padding: 12px 18px;
    border-radius: 10px;
    color: #191331;
    background: #b0a0ff;
    font-size: 12px;
    font-weight: 750;
}

.hr-page .hr-back:hover {
    background: #cec3ff;
}

.hr-page .hr-hero-art {
    display: grid;
    place-items: center;
    flex: 0 0 130px;
    width: 130px;
    height: 130px;
    border: 1px solid #ffffff35;
    border-radius: 30px;
    background: #ffffff19;
    transform: rotate(-8deg);
}

.hr-page .hr-hero-art svg {
    width: 65px;
    height: 65px;
    color: #fff;
}

/* SUMMARY */

.hr-page .hr-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-top: 25px;
}

.hr-page .hr-stat {
    padding: 22px;
    border: 1px solid var(--hr-border);
    border-radius: 16px;
    background: var(--hr-card);
}

.hr-page .hr-stat-label {
    display: block;
    color: #ac9af9;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hr-page .hr-stat strong {
    display: block;
    margin: 12px 0 7px;
    color: #fff;
    font-size: 30px;
}

.hr-page .hr-stat small {
    color: var(--hr-muted);
    font-size: 11px;
}

/* SECTION */

.hr-page .hr-section {
    margin-top: 38px;
}

.hr-page .hr-section-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 22px;
}

.hr-page .hr-section-head h2 {
    margin: 8px 0;
    color: #fff;
    font-size: 22px;
}

.hr-page .hr-section-head p {
    margin: 0;
    color: var(--hr-muted);
    font-size: 13px;
}

.hr-page .hr-count-badge {
    padding: 9px 12px;
    border: 1px solid #36465d;
    border-radius: 9px;
    background: var(--hr-card);
    color: #c4cbe0;
    font-size: 11px;
}

/* REQUEST CARDS */

.hr-page .hr-request-list {
    display: grid;
    gap: 18px;
}

.hr-page .hr-request-card {
    padding: 25px;
    border: 1px solid var(--hr-border);
    border-radius: 17px;
    background: var(--hr-card);
}

.hr-page .hr-request-card:hover {
    border-color: #62588d;
}

.hr-page .hr-request-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}

.hr-page .hr-request-number {
    display: block;
    margin-bottom: 8px;
    color: #ac9af9;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hr-page .hr-request-header h3 {
    margin: 0;
    color: #fff;
    font-size: 18px;
}

.hr-page .hr-status {
    display: inline-flex;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .5px;
}

.hr-page .hr-status.pending {
    background: #493c29;
    color: #ffd38b;
}

.hr-page .hr-status.accepted {
    background: #1e453b;
    color: #79e4b2;
}

.hr-page .hr-status.rejected {
    background: #482c39;
    color: #ffa4b5;
}

.hr-page .hr-status.expired {
    background: #344052;
    color: #c4cbd7;
}

.hr-page .hr-status.other {
    background: #35365b;
    color: #d4ccff;
}

/* REQUEST DETAILS */

.hr-page .hr-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.hr-page .hr-detail-item {
    min-width: 0;
    padding: 15px;
    border: 1px solid #2c3950;
    border-radius: 10px;
    background: #1c293d;
}

.hr-page .hr-detail-item span {
    display: block;
    margin-bottom: 9px;
    color: #91a0b8;
    font-size: 11px;
}

.hr-page .hr-detail-item strong {
    display: block;
    color: #edf0ff;
    font-size: 13px;
    overflow-wrap: anywhere;
}

/* REASON */

.hr-page .hr-reason {
    margin-top: 20px;
    padding: 18px;
    border: 1px solid #443d69;
    border-left: 4px solid #9a83ff;
    border-radius: 11px;
    background: #242640;
}

.hr-page .hr-reason strong {
    display: block;
    margin-bottom: 9px;
    color: #d0c4ff;
    font-size: 12px;
}

.hr-page .hr-reason p {
    margin: 0;
    color: #c7cde0;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

/* ACTION BUTTONS */

.hr-page .hr-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.hr-page .hr-buttons button {
    padding: 12px 20px;
    border: none;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s ease;
}

.hr-page .hr-accept-btn {
    background: #2eac79;
    color: #092b1f;
}

.hr-page .hr-accept-btn:hover {
    background: #63d6a4;
}

.hr-page .hr-reject-btn {
    background: #593344;
    color: #ffb6c1;
    border: 1px solid #84506b !important;
}

.hr-page .hr-reject-btn:hover {
    background: #754052;
}

.hr-page .hr-buttons button:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* MESSAGES */

.hr-page .hr-message {
    display: none;
    margin-top: 24px;
    padding: 16px 19px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 650;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.hr-page .hr-success {
    border: 1px solid #2d775d;
    background: #1a3b32;
    color: #84e5bc;
}

.hr-page .hr-error {
    border: 1px solid #854455;
    background: #402936;
    color: #ffb1c0;
}

/* EMPTY STATE */

.hr-page .hr-empty {
    padding: 55px 25px;
    border: 1px solid var(--hr-border);
    border-radius: 17px;
    background: var(--hr-card);
    text-align: center;
}

.hr-page .hr-empty-icon {
    display: grid;
    place-items: center;
    width: 70px;
    height: 70px;
    margin: 0 auto 18px;
    border-radius: 20px;
    color: #b7a4ff;
    background: #373052;
}

.hr-page .hr-empty-icon svg {
    width: 35px;
    height: 35px;
}

.hr-page .hr-empty h3 {
    margin: 0 0 10px;
    color: #fff;
}

.hr-page .hr-empty p {
    margin: 0;
    color: var(--hr-muted);
    font-size: 13px;
}

/* FOOTER */

.hr-page .hr-footer {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 40px;
    padding: 22px 0;
    border-top: 1px solid var(--hr-border);
    color: #77879e;
    font-size: 11px;
}

.hr-page a:focus-visible,
.hr-page button:focus-visible {
    outline: 3px solid #b6a5ff;
    outline-offset: 3px;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .hr-page .hr-sidebar {
        flex: 0 0 235px;
        width: 235px;
        min-width: 235px;
        max-width: 235px;
    }

    .hr-page .hr-main {
        padding: 28px;
    }

    .hr-page .hr-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hr-page .hr-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .hr-page .hr-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 270px;
        min-width: 270px;
        max-width: 270px;
        flex: none;
        height: 100dvh;
        transform: translateX(-100%);
        transition: transform .25s ease;
        box-shadow: 10px 0 40px #00000040;
    }

    .hr-page .hr-sidebar.open {
        transform: translateX(0);
    }

    .hr-page .hr-menu-button {
        display: inline-block;
    }

    .hr-page .hr-topbar {
        min-height: 76px;
        padding: 15px 20px;
    }

    .hr-page .hr-topbar h2 {
        font-size: 17px;
    }

    .hr-page .hr-user-info {
        display: none;
    }

    .hr-page .hr-main {
        padding: 20px 16px;
    }

    .hr-page .hr-hero {
        min-height: 0;
        padding: 29px 24px;
    }

    .hr-page .hr-hero-art {
        display: none;
    }

    .hr-page .hr-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hr-page .hr-section-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .hr-page .hr-detail-grid {
        grid-template-columns: 1fr;
    }

    .hr-page .hr-request-card {
        padding: 18px;
    }

    .hr-page .hr-buttons {
        flex-direction: column;
    }

    .hr-page .hr-buttons button {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hr-page *,
    .hr-page *::before,
    .hr-page *::after {
        transition-duration: .01ms !important;
    }
}

/* REQUEST LIST FILTERS - scoped, no changes to request processing */
.hr-page .hr-filter-bar {
    display: grid;
    grid-template-columns: minmax(155px, 1fr) minmax(220px, 1.5fr) minmax(155px, 1fr) auto;
    align-items: end;
    gap: 12px;
    padding: 17px;
    margin: 18px 0 10px;
    border: 1px solid var(--hr-border);
    border-radius: 15px;
    background: var(--hr-card);
}
.hr-page .hr-filter-field { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.hr-page .hr-filter-field label {
    color: #b7a4ff;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: .35px;
}
.hr-page .hr-filter-field input,
.hr-page .hr-filter-field select {
    width: 100%;
    min-width: 0;
    height: 43px;
    padding: 0 12px;
    border: 1px solid #3a4b65;
    border-radius: 9px;
    background: #0d1b2e;
    color: #eef2ff;
    font: inherit;
    font-size: 12px;
    color-scheme: dark;
}
.hr-page .hr-filter-field input:focus,
.hr-page .hr-filter-field select:focus {
    outline: 2px solid #ac9af9;
    outline-offset: 1px;
}
.hr-page .hr-filter-field input::placeholder { color: #869ab6; }
.hr-page .hr-filter-reset {
    height: 43px;
    padding: 0 16px;
    border: 1px solid #7e70bd;
    border-radius: 9px;
    background: #332b59;
    color: #eee8ff;
    cursor: pointer;
    font-weight: 700;
    font-size: 12px;
    white-space: nowrap;
}
.hr-page .hr-filter-reset:hover { background: #494077; }
.hr-page .hr-filter-meta {
    color: var(--hr-muted);
    font-size: 12px;
    margin: 10px 0 16px;
}
.hr-page .hr-filter-empty {
    padding: 26px 20px;
    margin: 10px 0 16px;
}
.hr-page [hidden] { display: none !important; }
@media (max-width: 950px) {
    .hr-page .hr-filter-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 550px) {
    .hr-page .hr-filter-bar { grid-template-columns: 1fr; }
}

</style>
</head>

<body class="hr-page">

<div class="hr-layout">

    <!-- SIDEBAR -->
    <aside class="hr-sidebar" id="hr-sidebar">

        <a href="dashboard.php" class="hr-brand">
            <span class="hr-brand-logo">
                <svg viewBox="0 0 24 24">
                    <path d="m2 10 10-5 10 5-10 5-10-5Z"/>
                    <path d="M6 12v5c4 3 8 3 12 0v-5"/>
                    <path d="M22 10v7"/>
                </svg>
            </span>
            <span>
                <strong>Smart Faculty</strong>
                <small>ASSISTANT PORTAL</small>
            </span>
        </a>

        <div class="hr-sidebar-label">MAIN MENU</div>

        <nav class="hr-nav" aria-label="Faculty navigation">

            <a href="dashboard.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>

            <a href="timetable.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M7 3v4M17 3v4M3 10h18"/>
                    <path d="M8 14h3M14 14h3"/>
                </svg>
                My Timetable
            </a>

            <a href="mark-attendance.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="4" y="3" width="16" height="18" rx="2"/>
                    <path d="M8 8h8M8 12h8M8 16h5"/>
                </svg>
                 Mark Attendance
            </a>

            <a href="notifications.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M10 21h4"/>
                </svg>
                Notifications
            </a>

            <a href="profile.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
                My Profile
            </a>

            <div class="hr-sidebar-label hr-nav-section">
                FACULTY MANAGEMENT
            </div>

            <a href="availability.php" class="hr-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                Faculty Availability
            </a>

            <a href="requests.php"
               class="hr-nav-link active"
               aria-current="page">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16l-4-4M20 7l-4 4"/>
                    <path d="M20 17H4l4-4M4 17l4 4"/>
                </svg>
                Handover Requests
            </a>
        </nav>

        <div class="hr-sidebar-bottom">
            <div class="hr-side-help">
                <strong>✦ Your teaching hub</strong>
                <p>Coordinate class handovers with your faculty team.</p>
            </div>

            <a href="../auth/logout.php" class="hr-side-logout">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5M15 12H3"/>
                    <path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="hr-content">

        <header class="hr-topbar">
            <div class="hr-topbar-left">
                <button type="button"
                        id="hr-menu-button"
                        class="hr-menu-button"
                        aria-label="Toggle navigation"
                        aria-controls="hr-sidebar"
                        aria-expanded="false">
                    ☰
                </button>

                <div>
                    <span class="hr-page-label">FACULTY / MANAGEMENT</span>
                    <h2>Handover Requests</h2>
                </div>
            </div>

            <div class="hr-user">
                <div class="hr-avatar"><?= reqEscape($initial) ?></div>
                <div class="hr-user-info">
                    <strong><?= $username ?></strong>
                    <span>Faculty Member</span>
                </div>
            </div>
        </header>

        <main class="hr-main">

            <!-- HERO -->
            <section class="hr-hero">
                <div>
                    <span class="hr-eyebrow">
                        ✦ FACULTY MANAGEMENT
                    </span>

                    <h1>Handover Requests</h1>

                    <p>
                        Review incoming handover requests, respond to colleagues,
                        and track accepted or rejected requests you sent.
                    </p>

                    <a href="dashboard.php" class="hr-back">
                        ← Back to Dashboard
                    </a>
                </div>

                <div class="hr-hero-art" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 7h16l-4-4M20 7l-4 4"/>
                        <path d="M20 17H4l4-4M4 17l4 4"/>
                    </svg>
                </div>
            </section>

            <!-- SUMMARY CARDS: incoming requests only -->
            <p class="hr-eyebrow" style="margin: 24px 0 0;">RECEIVED REQUEST SUMMARY</p>
            <section class="hr-summary" aria-label="Request summary">

                <div class="hr-stat">
                    <span class="hr-stat-label">TOTAL REQUESTS</span>
                    <strong><?= $total ?></strong>
                    <small>All received requests</small>
                </div>

                <div class="hr-stat">
                    <span class="hr-stat-label">PENDING</span>
                    <strong><?= $pending ?></strong>
                    <small>Awaiting your response</small>
                </div>

                <div class="hr-stat">
                    <span class="hr-stat-label">ACCEPTED</span>
                    <strong><?= $accepted ?></strong>
                    <small>Accepted requests</small>
                </div>

                <div class="hr-stat">
                    <span class="hr-stat-label">REJECTED</span>
                    <strong><?= $rejected ?></strong>
                    <small>Rejected requests</small>
                </div>

            </section>

            <!-- SENT REQUESTS: visible to original requesting faculty -->
            <section class="hr-section" aria-label="Outgoing handover requests">
                <div class="hr-section-head">
                    <div>
                        <span class="hr-eyebrow">REQUEST TRACKING</span>
                        <h2>Requests You Sent</h2>
                        <p>Track which faculty accepted or rejected your class handover requests.</p>
                    </div>
                    <span class="hr-count-badge"><?= $sentTotal ?> Sent</span>
                </div>

                <div class="hr-summary" aria-label="Sent request status summary">
                    <div class="hr-stat">
                        <span class="hr-stat-label">TOTAL SENT</span>
                        <strong><?= $sentTotal ?></strong>
                        <small>Requests sent to other faculty</small>
                    </div>
                    <div class="hr-stat">
                        <span class="hr-stat-label">AWAITING REPLY</span>
                        <strong><?= $sentPending ?></strong>
                        <small>Pending responses</small>
                    </div>
                    <div class="hr-stat">
                        <span class="hr-stat-label">ACCEPTED</span>
                        <strong><?= $sentAccepted ?></strong>
                        <small>Faculty agreed to take over</small>
                    </div>
                    <div class="hr-stat">
                        <span class="hr-stat-label">REJECTED</span>
                        <strong><?= $sentRejected ?></strong>
                        <small>Faculty declined the request</small>
                    </div>
                </div>

                <!-- SENT REQUEST FILTERS (client-side, display only) -->
                <div class="hr-filter-bar" aria-label="Filter sent requests">
                    <div class="hr-filter-field">
                        <label for="sent-status-filter">Status</label>
                        <select id="sent-status-filter">
                            <option value="ALL">All statuses</option>
                            <option value="PENDING">Pending</option>
                            <option value="ACCEPTED">Accepted</option>
                            <option value="REJECTED">Rejected</option>
                            <option value="EXPIRED">Expired</option>
                        </select>
                    </div>
                    <div class="hr-filter-field">
                        <label for="sent-search-filter">Search faculty / request</label>
                        <input type="search" id="sent-search-filter"
                               placeholder="Faculty name, code, day, period or #" autocomplete="off">
                    </div>
                    <div class="hr-filter-field">
                        <label for="sent-date-filter">Request created on</label>
                        <input type="date" id="sent-date-filter" aria-label="Filter by request creation date">
                    </div>
                    <button type="button" class="hr-filter-reset" id="sent-reset-filter">Clear filters</button>
                </div>
                <p class="hr-filter-meta" id="sent-filter-count" role="status" aria-live="polite">
                    Showing <?= $sentTotal ?> of <?= $sentTotal ?> sent requests (summary cards show all requests).
                </p>
                <div class="hr-empty hr-filter-empty" id="sent-no-matches" hidden>
                    <h3>No matching requests</h3>
                    <p>Try another status, faculty name, or date.</p>
                </div>

                <div class="hr-request-list" id="sent-requests-list" style="margin-top: 20px;">
                    <?php if ($sentTotal === 0): ?>
                        <div class="hr-empty">
                            <h3>No Sent Requests</h3>
                            <p>You have not sent any handover requests yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sentRequests as $sentRequest): ?>
                            <?php
                            $sentStatusText = strtoupper((string)$sentRequest['request_status']);
                            $sentStatusLower = strtolower($sentStatusText);
                            $sentStatusClass = in_array($sentStatusLower,
                                ['pending', 'accepted', 'rejected', 'expired'], true)
                                ? $sentStatusLower : 'other';
                            ?>
                            <article class="hr-request-card"
                                id="sent-request-<?= (int)$sentRequest['request_id'] ?>"
                                data-status="<?= reqEscape($sentStatusText) ?>"
                                data-request-date="<?= reqEscape(substr((string)$sentRequest['requested_at'], 0, 10)) ?>"
                                data-search="<?= reqEscape(implode(' ', [
                                    $sentRequest['request_id'],
                                    $sentRequest['alternate_faculty_name'],
                                    $sentRequest['alternate_faculty_code'],
                                    $sentRequest['day'],
                                    'P' . $sentRequest['period_id'],
                                    $sentRequest['section_id'],
                                    $sentRequest['reason']
                                ])) ?>">
                                <div class="hr-request-header">
                                    <div>
                                        <span class="hr-request-number">SENT HANDOVER REQUEST</span>
                                        <h3>Request #<?= (int)$sentRequest['request_id'] ?></h3>
                                    </div>
                                    <span class="hr-status <?= $sentStatusClass ?>">
                                        <?= reqEscape($sentStatusText) ?>
                                    </span>
                                </div>
                                <div class="hr-detail-grid">
                                    <div class="hr-detail-item">
                                        <span>Sent To Faculty</span>
                                        <strong><?= reqEscape($sentRequest['alternate_faculty_name']) ?></strong>
                                    </div>
                                    <div class="hr-detail-item">
                                        <span>Faculty Code</span>
                                        <strong><?= reqEscape($sentRequest['alternate_faculty_code']) ?></strong>
                                    </div>
                                    <div class="hr-detail-item">
                                        <span>Day / Period</span>
                                        <strong><?= reqEscape($sentRequest['day']) ?> / P<?= (int)$sentRequest['period_id'] ?></strong>
                                    </div>
                                    <div class="hr-detail-item">
                                        <span>Section ID</span>
                                        <strong><?= (int)$sentRequest['section_id'] ?></strong>
                                    </div>
                                    <div class="hr-detail-item">
                                        <span>Requested At</span>
                                        <strong><?= reqEscape($sentRequest['requested_at']) ?></strong>
                                    </div>
                                    <div class="hr-detail-item">
                                        <span>Responded At</span>
                                        <strong><?= reqEscape($sentRequest['responded_at'] ?: 'Awaiting response') ?></strong>
                                    </div>
                                </div>
                                <div class="hr-reason">
                                    <strong>HANDOVER REASON</strong>
                                    <p><?= nl2br(reqEscape($sentRequest['reason'])) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- RESPONSE MESSAGES -->
            <div id="successMessage"
                 class="hr-message hr-success"
                 role="status"></div>

            <div id="errorMessage"
                 class="hr-message hr-error"
                 role="alert"></div>

            <!-- REQUEST LIST -->
            <section class="hr-section">

                <div class="hr-section-head">
                    <div>
                        <span class="hr-eyebrow">REQUEST CENTER</span>
                        <h2>Incoming Handover Requests</h2>
                        <p>
                            Review class details before accepting
                            or rejecting a handover.
                        </p>
                    </div>

                    <span class="hr-count-badge">
                        <?= $total ?> Requests
                    </span>
                </div>

                <!-- INCOMING REQUEST FILTERS (client-side, display only) -->
                <div class="hr-filter-bar" aria-label="Filter incoming requests">
                    <div class="hr-filter-field">
                        <label for="incoming-status-filter">Status</label>
                        <select id="incoming-status-filter">
                            <option value="ALL">All statuses</option>
                            <option value="PENDING">Pending</option>
                            <option value="ACCEPTED">Accepted</option>
                            <option value="REJECTED">Rejected</option>
                            <option value="EXPIRED">Expired</option>
                        </select>
                    </div>
                    <div class="hr-filter-field">
                        <label for="incoming-search-filter">Search faculty / request</label>
                        <input type="search" id="incoming-search-filter"
                               placeholder="Faculty name, code, day, period or #" autocomplete="off">
                    </div>
                    <div class="hr-filter-field">
                        <label for="incoming-date-filter">Request created on</label>
                        <input type="date" id="incoming-date-filter" aria-label="Filter by request creation date">
                    </div>
                    <button type="button" class="hr-filter-reset" id="incoming-reset-filter">Clear filters</button>
                </div>
                <p class="hr-filter-meta" id="incoming-filter-count" role="status" aria-live="polite">
                    Showing <?= $total ?> of <?= $total ?> incoming requests (summary cards show all requests).
                </p>
                <div class="hr-empty hr-filter-empty" id="incoming-no-matches" hidden>
                    <h3>No matching requests</h3>
                    <p>Try another status, faculty name, or date.</p>
                </div>

                <div class="hr-request-list" id="incoming-requests-list">

                    <?php if ($total === 0): ?>

                        <div class="hr-empty">
                            <div class="hr-empty-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M4 7h16l-4-4M20 7l-4 4"/>
                                    <path d="M20 17H4l4-4M4 17l4 4"/>
                                </svg>
                            </div>

                            <h3>No Handover Requests</h3>

                            <p>
                                You currently have no handover requests.
                            </p>
                        </div>

                    <?php else: ?>

                        <?php foreach ($requests as $request): ?>

                            <?php
                            $status = (string)$request['request_status'];

                            $statusLower = strtolower($status);
                            $allowedStatuses = [
                                'pending',
                                'accepted',
                                'rejected',
                                'expired'
                            ];

                            $statusClass = in_array(
                                $statusLower,
                                $allowedStatuses,
                                true
                            ) ? $statusLower : 'other';
                            ?>

                            <article class="hr-request-card"
                                id="request-<?= (int)$request['request_id'] ?>"
                                data-status="<?= reqEscape(strtoupper($status)) ?>"
                                data-request-date="<?= reqEscape(substr((string)$request['requested_at'], 0, 10)) ?>"
                                data-search="<?= reqEscape(implode(' ', [
                                    $request['request_id'],
                                    $request['original_faculty_name'],
                                    $request['original_faculty_code'],
                                    $request['day'],
                                    'P' . $request['period_id'],
                                    $request['section_id'],
                                    $request['reason']
                                ])) ?>">

                                <div class="hr-request-header">
                                    <div>
                                        <span class="hr-request-number">
                                            HANDOVER REQUEST
                                        </span>

                                        <h3>
                                            Request #<?= (int)$request['request_id'] ?>
                                        </h3>
                                    </div>

                                    <span class="hr-status <?= $statusClass ?>">
                                        <?= reqEscape($status) ?>
                                    </span>
                                </div>

                                <div class="hr-detail-grid">

                                    <div class="hr-detail-item">
                                        <span>From Faculty</span>
                                        <strong>
                                            <?= reqEscape($request['original_faculty_name']) ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Faculty Code</span>
                                        <strong>
                                            <?= reqEscape($request['original_faculty_code']) ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Day</span>
                                        <strong>
                                            <?= reqEscape($request['day']) ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Period</span>
                                        <strong>
                                            P<?= (int)$request['period_id'] ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Section ID</span>
                                        <strong>
                                            <?= (int)$request['section_id'] ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Subject ID</span>
                                        <strong>
                                            <?= (int)$request['subject_id'] ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Classroom ID</span>
                                        <strong>
                                            <?= (int)$request['classroom_id'] ?>
                                        </strong>
                                    </div>

                                    <div class="hr-detail-item">
                                        <span>Requested At</span>
                                        <strong>
                                            <?= reqEscape($request['requested_at']) ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="hr-reason">
                                    <strong>REASON FOR HANDOVER</strong>

                                    <p><?= nl2br(reqEscape($request['reason'])) ?></p>
                                </div>

                                <?php if ($status === 'PENDING'): ?>

                                    <div class="hr-buttons">

                                        <button type="button"
                                                class="hr-accept-btn"
                                                onclick="respondRequest(
                                                    <?= (int)$request['request_id'] ?>,
                                                    'ACCEPTED'
                                                )">
                                            ✓ Accept Request
                                        </button>

                                        <button type="button"
                                                class="hr-reject-btn"
                                                onclick="respondRequest(
                                                    <?= (int)$request['request_id'] ?>,
                                                    'REJECTED'
                                                )">
                                            ✕ Reject Request
                                        </button>

                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </section>

            <footer class="hr-footer">
                <span>
                    © <?= date('Y') ?> Smart Faculty Assistant Portal
                </span>
                <span>Faculty Handover Management</span>
            </footer>

        </main>
    </div>
</div>

<script>
/* INDEPENDENT FILTERS FOR SENT AND INCOMING REQUESTS */
(function () {
    function attachRequestFilters(prefix) {
        const list = document.getElementById(prefix + '-requests-list');
        const statusControl = document.getElementById(prefix + '-status-filter');
        const searchControl = document.getElementById(prefix + '-search-filter');
        const dateControl = document.getElementById(prefix + '-date-filter');
        const clearButton = document.getElementById(prefix + '-reset-filter');
        const countText = document.getElementById(prefix + '-filter-count');
        const noMatches = document.getElementById(prefix + '-no-matches');
        if (!list || !statusControl || !searchControl || !dateControl || !clearButton || !countText || !noMatches) return;

        const cards = Array.from(list.querySelectorAll('.hr-request-card'));
        const label = prefix === 'sent' ? 'sent' : 'incoming';

        function applyFilters() {
            const status = statusControl.value;
            const query = searchControl.value.trim().toLocaleLowerCase();
            const date = dateControl.value;
            let visible = 0;

            for (const card of cards) {
                const matchesStatus = status === 'ALL' || card.dataset.status === status;
                const matchesSearch = !query || (card.dataset.search || '').toLocaleLowerCase().includes(query);
                const matchesDate = !date || card.dataset.requestDate === date;
                const show = matchesStatus && matchesSearch && matchesDate;
                card.hidden = !show;
                if (show) visible++;
            }

            countText.textContent = 'Showing ' + visible + ' of ' + cards.length + ' ' + label +
                ' requests (summary cards show all requests).';
            noMatches.hidden = cards.length === 0 || visible > 0;
        }

        statusControl.addEventListener('change', applyFilters);
        searchControl.addEventListener('input', applyFilters);
        dateControl.addEventListener('change', applyFilters);
        clearButton.addEventListener('click', function () {
            statusControl.value = 'ALL';
            searchControl.value = '';
            dateControl.value = '';
            applyFilters();
        });
        applyFilters();
    }

    attachRequestFilters('sent');
    attachRequestFilters('incoming');
})();

/* MOBILE SIDEBAR */

(function () {
    const button = document.getElementById('hr-menu-button');
    const sidebar = document.getElementById('hr-sidebar');

    if (!button || !sidebar) return;

    button.addEventListener('click', function () {
        const open = sidebar.classList.toggle('open');
        button.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            sidebar.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
        }
    });
})();

/* ORIGINAL HANDOVER RESPONSE FUNCTION */

async function respondRequest(requestId, responseStatus) {

    let message;

    if (responseStatus === 'ACCEPTED') {
        message =
            "Are you sure you want to ACCEPT this handover request?";
    } else {
        message =
            "Are you sure you want to REJECT this handover request?";
    }

    const confirmed = confirm(message);

    if (!confirmed) {
        return;
    }

    const formData = new FormData();

    formData.append("request_id", requestId);
    formData.append("status", responseStatus);

    try {
        const response = await fetch(
            "../api/respond_handover.php",
            {
                method: "POST",
                body: formData
            }
        );

        const text = await response.text();

        console.log("Respond Handover API:", text);

        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            showError(
                "Invalid server response:\n\n" + text
            );
            return;
        }

        if (data.success) {

            showSuccess(data.message);

            setTimeout(function () {
                location.reload();
            }, 1200);

        } else {

            showError(
                data.message ||
                "Unable to update request."
            );
        }

    } catch (error) {

        console.error(error);

        showError(
            "Unable to connect to server.\n\n" +
            error.message
        );
    }
}

/* SUCCESS MESSAGE */

function showSuccess(message) {

    const box = document.getElementById("successMessage");
    const errorBox = document.getElementById("errorMessage");

    box.innerText = message;
    box.style.display = "block";
    errorBox.style.display = "none";
}

/* ERROR MESSAGE */

function showError(message) {

    const box = document.getElementById("errorMessage");
    const successBox = document.getElementById("successMessage");

    box.innerText = message;
    box.style.display = "block";
    successBox.style.display = "none";
}
</script>

</body>
</html>
