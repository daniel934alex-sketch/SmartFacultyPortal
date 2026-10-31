<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strtoupper((string)$_SESSION['role']) !== 'FACULTY'
) {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$username = htmlspecialchars(
    (string)($_SESSION['username'] ?? 'Faculty'),
    ENT_QUOTES,
    'UTF-8'
);

$initial = htmlspecialchars(
    strtoupper(substr((string)($_SESSION['username'] ?? 'F'), 0, 1)),
    ENT_QUOTES,
    'UTF-8'
);

$periods = [];
$pQuery = $conn->query("SELECT period_id, start_time, end_time FROM periods ORDER BY period_id ASC");
if ($pQuery && $pQuery->num_rows > 0) {
    while ($pRow = $pQuery->fetch_assoc()) {
        $pId = (int)$pRow['period_id'];
        $start = date("h:i A", strtotime($pRow['start_time']));
        $end = date("h:i A", strtotime($pRow['end_time']));
        $periods[$pId] = "{$start} - {$end}";
    }
} else {
    $periods = [
        1 => '08:30 AM - 09:15 AM',
        2 => '09:15 AM - 10:00 AM',
        3 => '10:00 AM - 10:45 AM',
        4 => '10:45 AM - 11:30 AM',
        5 => '11:30 AM - 12:15 PM',
        6 => '12:15 PM - 01:00 PM',
        7 => '02:00 PM - 02:45 PM',
        8 => '02:45 PM - 03:30 PM',
        9 => '03:30 PM - 04:15 PM',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<title>Faculty Availability | Smart Faculty</title>

<style>
html {
    scrollbar-gutter: stable;
    overflow-y: scroll;
}

* {
    box-sizing: border-box;
}

body.av-page {
    margin: 0;
    min-height: 100vh;
    background: #0c1220;
    color: #eff2ff;
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.av-page button,
.av-page input {
    font: inherit;
}

.av-page a {
    text-decoration: none;
    color: inherit;
}

.av-page [hidden] {
    display: none !important;
}

/* LAYOUT */

.av-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.av-sidebar {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    flex-direction: column;
    flex: 0 0 264px;
    width: 264px;
    min-width: 264px;
    max-width: 264px;
    box-sizing: border-box;
    height: 100vh;
    overflow-y: auto;
    padding: 28px 17px 22px;
    background: #111a2b;
    border-right: 1px solid #2a364b;
}

.av-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 7px 45px;
    color: #fff;
}

.av-logo {
    display: grid;
    place-items: center;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: linear-gradient(135deg, #a58aff, #6550cc);
    font-size: 24px;
    font-weight: 800;
}

.av-brand strong {
    display: block;
    font-size: 15px;
}

.av-brand small {
    display: block;
    margin-top: 5px;
    color: #9dabc2;
    font-size: 9px;
    letter-spacing: 1.5px;
}

.av-nav-label {
    margin: 0 14px 15px;
    color: #687991;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
}

.av-nav-label.av-spaced {
    margin-top: 27px;
}

.av-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.av-nav a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    border: 1px solid transparent;
    border-radius: 11px;
    color: #98a6bc;
    font-size: 13px;
    transition: background .2s, color .2s;
}

.av-nav a:hover {
    background: #202c41;
    color: #fff;
}

.av-nav a.active {
    background: #302b53;
    border-color: #50467c;
    color: #e2d9ff;
    font-weight: 700;
}

.av-nav-icon {
    width: 20px;
    text-align: center;
    font-size: 18px;
}

.av-side-bottom {
    margin-top: auto;
    padding-top: 35px;
}

.av-side-note {
    padding: 18px;
    border: 1px solid #3b375f;
    border-radius: 14px;
    background: linear-gradient(145deg, #2b294e, #1a2440);
}

.av-side-note strong {
    font-size: 13px;
}

.av-side-note p {
    margin: 8px 0 0;
    color: #a7b0ca;
    font-size: 11px;
    line-height: 1.7;
}

.av-logout {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-top: 17px;
    padding: 13px 14px;
    border-radius: 10px;
    color: #f1a4b1 !important;
    font-size: 13px;
}

.av-logout:hover {
    background: #422536;
}

/* TOPBAR */

.av-content {
    min-width: 0;
    flex: 1;
}

.av-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 91px;
    gap: 20px;
    padding: 17px 35px;
    box-sizing: border-box;
    border-bottom: 1px solid #2a364b;
    background: #111a2b;
}

.av-topbar-label,
.av-eyebrow {
    display: block;
    color: #ac98ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
}

.av-topbar h2 {
    margin: 5px 0 0;
    font-size: 20px;
}

.av-account {
    display: flex;
    align-items: center;
    gap: 12px;
}

.av-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #393060;
    color: #e8deff;
    font-weight: 800;
}

.av-account strong {
    display: block;
    font-size: 13px;
}

.av-account small {
    display: block;
    margin-top: 5px;
    color: #96a4bc;
    font-size: 11px;
}

.av-menu {
    display: none;
    cursor: pointer;
    border: 1px solid #3d4860;
    border-radius: 9px;
    background: #263248;
    color: #fff;
    padding: 9px 12px;
}

/* MAIN CONTENT */

.av-main {
    width: min(1230px, 100%);
    margin: 0 auto;
    padding: 34px;
    box-sizing: border-box;
}

/* WELCOME BANNER */

.av-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    overflow: hidden;
    gap: 25px;
    min-height: 228px;
    padding: 36px 42px;
    border: 1px solid #51478c;
    border-radius: 21px;
    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 55%,
        #5c4da0
    );
}

.av-hero > div:first-child {
    position: relative;
    z-index: 1;
}

.av-hero h1 {
    margin: 12px 0;
    font-size: clamp(26px, 3vw, 36px);
}

.av-hero p {
    max-width: 565px;
    margin: 0;
    color: #d2cfea;
    font-size: 13px;
    line-height: 1.85;
}

.av-back {
    display: inline-flex;
    margin-top: 22px;
    padding: 12px 18px;
    border-radius: 10px;
    background: #b0a0ff;
    color: #191331 !important;
    font-size: 12px;
    font-weight: 800;
}

.av-hero-art {
    display: grid;
    place-items: center;
    flex: 0 0 130px;
    width: 130px;
    height: 130px;
    border: 1px solid #ffffff45;
    border-radius: 30px;
    background: #ffffff1a;
    font-size: 65px;
    transform: rotate(-8deg);
}

/* DATE SELECTOR */

.av-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin: 25px 0 30px;
    padding: 20px 23px;
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 15px;
}

.av-date-label {
    display: block;
    margin-bottom: 10px;
    color: #a8b6cb;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: .5px;
}

#availabilityDate {
    color: #f3f4ff;
    color-scheme: dark;
    background: #101a2b;
    border: 1px solid #3c4b65;
    border-radius: 9px;
    padding: 11px 13px;
    font-size: 14px;
}

.av-toolbar-hint {
    color: #97a8c3;
    font-size: 12px;
    line-height: 1.7;
}

.av-toolbar-hint strong {
    display: block;
    color: #f3f4ff;
    font-size: 16px;
}

/* PERIOD SECTION */

.av-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 19px;
}

.av-heading h2 {
    margin: 8px 0 6px;
    font-size: 23px;
}

.av-heading p {
    margin: 0;
    color: #96a4bc;
    font-size: 13px;
}

.av-count {
    padding: 9px 12px;
    border: 1px solid #35435c;
    border-radius: 9px;
    color: #bcc6da;
    font-size: 11px;
    white-space: nowrap;
}

.av-periods {
    display: grid;
    gap: 13px;
}

.av-period {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    padding: 18px 22px;
    border: 1px solid #2a364b;
    border-radius: 14px;
    background: #172235;
    transition: border-color .2s, background .2s;
}

.av-period:hover {
    border-color: #62588d;
    background: #1a2740;
}

.av-period-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.av-period-icon {
    display: grid;
    place-items: center;
    flex: 0 0 45px;
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: #393155;
    color: #c0adff;
    font-size: 22px;
}

.av-period-number {
    font-size: 15px;
    font-weight: 750;
}

.av-period-time {
    margin-top: 6px;
    color: #9caac0;
    font-size: 12px;
}

.av-selected {
    display: inline-block;
    margin-top: 7px;
    color: #a7bbd8;
    font-size: 11px;
}

.av-selected:empty {
    display: none;
}

/* AVAILABILITY BUTTONS */

.av-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
}

.av-actions button {
    padding: 11px 13px;
    border-radius: 9px;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 11px;
    font-weight: 800;
    transition: filter .15s, transform .15s;
}

.av-actions button:hover {
    filter: brightness(1.12);
    transform: translateY(-1px);
}

.av-actions button:disabled {
    opacity: .6;
    cursor: wait;
    transform: none;
}

.av-attend {
    background: #184d3b;
    color: #88edbd;
    border-color: #296d55 !important;
}

.av-cant {
    background: #502d3d;
    color: #ffb7c8;
    border-color: #76445b !important;
}

.av-leave {
    background: #514027;
    color: #ffd38d;
    border-color: #775b35 !important;
}

.av-actions button.is-selected {
    outline: 2px solid #d3c7ff;
    outline-offset: 2px;
}

/* HANDOVER PANELS */

.av-panel {
    margin-top: 25px;
    padding: 24px;
    border: 1px solid #354360;
    border-radius: 16px;
    background: #172235;
}

.av-panel h3 {
    margin: 0 0 16px;
    font-size: 18px;
}

.av-panel p {
    font-size: 13px;
    line-height: 1.75;
}

#classBox,
#facultyBox {
    display: none;
}

.av-details-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 11px;
}

.av-detail {
    min-width: 0;
    padding: 13px 15px;
    background: #1d2b43;
    border: 1px solid #32425b;
    border-radius: 10px;
}

.av-detail span {
    display: block;
    margin-bottom: 7px;
    color: #9cadc6;
    font-size: 11px;
}

.av-detail strong {
    font-size: 13px;
    overflow-wrap: anywhere;
}

.av-primary,
.av-send {
    margin-top: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 10px;
    padding: 12px 18px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 800;
}

.av-primary {
    background: #b5a3ff;
    color: #211a44;
}

.av-send {
    background: #70dbad;
    color: #102f26;
}

.av-send:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.av-faculty-count {
    margin: 0 0 16px;
    color: #abbad0;
    font-size: 13px;
}

.av-faculty-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.av-faculty-item {
    padding: 17px;
    border: 1px solid #34445e;
    border-radius: 11px;
    background: #1c2a40;
    overflow-wrap: anywhere;
}

.av-faculty-item > strong {
    display: block;
    margin-bottom: 10px;
    color: #f0eaff;
    font-size: 15px;
}

.av-faculty-item p {
    margin: 5px 0;
    color: #aab9d0;
    font-size: 12px;
}

.av-faculty-item b {
    color: #d6dff1;
}

.av-pill {
    display: inline-block;
    margin-top: 9px;
    padding: 5px 9px;
    border-radius: 20px;
    background: #1d493c;
    color: #8ce8b5;
    font-size: 11px;
}

/* MESSAGES */

.av-notice {
    margin: 0 0 20px;
    padding: 13px 16px;
    border: 1px solid #4b5480;
    border-radius: 11px;
    background: #242e50;
    color: #e5e5ff;
    font-size: 13px;
    line-height: 1.6;
}

.av-notice.error {
    background: #402936;
    border-color: #854455;
    color: #ffc2cf;
}

.av-notice.success {
    background: #1a3b32;
    border-color: #2d775d;
    color: #8ee4bd;
}

.av-info {
    padding: 12px;
    border-radius: 9px;
    border: 1px solid #765f37;
    background: #382f26;
    color: #f5d79a;
    font-size: 12px;
}

/* FOOTER */

.av-footer {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 40px;
    padding: 22px 0;
    border-top: 1px solid #2a364b;
    color: #77879e;
    font-size: 11px;
}

.av-page a:focus-visible,
.av-page button:focus-visible,
.av-page input:focus-visible {
    outline: 3px solid #b6a5ff;
    outline-offset: 3px;
}

/* RESPONSIVE DESIGN */

@media (max-width: 1100px) {
    .av-sidebar {
        flex: 0 0 235px;
        width: 235px;
        min-width: 235px;
        max-width: 235px;
    }

    .av-main {
        padding: 28px;
    }

    .av-toolbar {
        gap: 25px;
    }
}

@media (max-width: 760px) {
    .av-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 270px;
        min-width: 270px;
        max-width: 270px;
        flex: none;
        height: 100dvh;
        transform: translateX(-100%);
        transition: transform .25s;
    }

    .av-sidebar.open {
        transform: translateX(0);
    }

    .av-menu {
        display: inline-block;
    }

    .av-topbar {
        min-height: 76px;
        padding: 15px 20px;
    }

    .av-account-text {
        display: none;
    }

    .av-main {
        padding: 20px 16px;
    }

    .av-hero {
        min-height: 0;
        padding: 29px 24px;
    }

    .av-hero-art {
        display: none;
    }

    .av-period {
        align-items: flex-start;
    }

    .av-actions {
        width: 100%;
    }

    .av-actions button {
        flex: 1;
    }

    .av-details-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .av-faculty-list {
        grid-template-columns: 1fr;
    }

    .av-overlay:not([hidden]) {
        display: block;
        position: fixed;
        z-index: 25;
        inset: 0;
        border: 0;
        background: #000b;
    }
}

@media (max-width: 450px) {
    .av-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .av-detail {
        padding: 11px;
    }

    .av-details-grid {
        grid-template-columns: 1fr;
    }

    .av-actions {
        gap: 6px;
    }

    .av-actions button {
        font-size: 10px;
        padding: 11px 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .av-page *,
    .av-page *::before,
    .av-page *::after {
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body class="av-page">

<div class="av-layout">

    <!-- SIDEBAR -->
    <aside class="av-sidebar" id="avSidebar">

        <a class="av-brand" href="dashboard.php">
            <span class="av-logo">S</span>
            <span>
                <strong>Smart Faculty</strong>
                <small>ASSISTANT PORTAL</small>
            </span>
        </a>

        <div class="av-nav-label">MAIN MENU</div>

        <nav class="av-nav" aria-label="Faculty navigation">
            <a href="dashboard.php">
                <span class="av-nav-icon">▦</span>
                Dashboard
            </a>

            <a href="timetable.php">
                <span class="av-nav-icon">▤</span>
                My Timetable
            </a>

            <a href="mark-attendance.php">
                <span class="av-nav-icon">✓</span>
                Mark Attendance
            </a>

            <a href="notifications.php">
                <span class="av-nav-icon">♧</span>
                Notifications
            </a>

            <a href="profile.php">
                <span class="av-nav-icon">♙</span>
                My Profile
            </a>

            <div class="av-nav-label av-spaced">
                FACULTY MANAGEMENT
            </div>

            <a href="availability.php"
               class="active"
               aria-current="page">
                <span class="av-nav-icon">◷</span>
                Faculty Availability
            </a>

            <a href="requests.php">
                <span class="av-nav-icon">⇄</span>
                Handover Requests
            </a>
        </nav>

        <div class="av-side-bottom">
            <div class="av-side-note">
                <strong> Your teaching hub</strong>
                <p>
                    Update availability and coordinate
                    class coverage in a few clicks.
                </p>
            </div>

            <a class="av-logout" href="../auth/logout.php">
                ↗ Logout
            </a>
        </div>
    </aside>

    <button class="av-overlay"
            id="avOverlay"
            type="button"
            hidden
            aria-label="Close navigation"></button>

    <!-- CONTENT -->
    <div class="av-content">

        <header class="av-topbar">

            <div style="display:flex;align-items:center;gap:14px">
                <button class="av-menu"
                        id="avMenu"
                        type="button"
                        aria-label="Open navigation"
                        aria-controls="avSidebar"
                        aria-expanded="false">
                    ☰
                </button>

                <div>
                    <span class="av-topbar-label">
                        FACULTY / MANAGEMENT
                    </span>
                    <h2>Faculty Availability</h2>
                </div>
            </div>

            <div class="av-account">
                <div class="av-avatar">
                    <?= $initial ?>
                </div>
                <div class="av-account-text">
                    <strong><?= $username ?></strong>
                    <small>Faculty Member</small>
                </div>
            </div>
        </header>

        <main class="av-main">

            <!-- HERO -->
            <section class="av-hero">
                <div>
                    <span class="av-eyebrow">
                         FACULTY WORKSPACE
                    </span>
                    <h1>Manage your availability</h1>
                    <p>
                        Keep your class availability up to date.
                        If you can't attend a scheduled class,
                        easily find colleagues who can take over.
                    </p>
                    <a class="av-back" href="dashboard.php">
                        ← Back to Dashboard
                    </a>
                </div>

                <div class="av-hero-art" aria-hidden="true">◷</div>
            </section>

            <!-- DATE SELECTOR -->
            <section class="av-toolbar"
                     aria-label="Choose availability date">

                <div>
                    <label class="av-date-label" for="availabilityDate">
                        SELECT DATE
                    </label>
                    <input type="date"
                           id="availabilityDate"
                           value="<?= date('Y-m-d') ?>">
                </div>

                <div class="av-toolbar-hint">
                    <strong>9 teaching periods</strong>
                    Select a status for each period below.
                </div>
            </section>

            <div id="avNotice"
                 class="av-notice"
                 role="status"
                 aria-live="polite"
                 hidden></div>

            <!-- PERIODS -->
            <section aria-labelledby="avPeriodsHeading">

                <div class="av-heading">
                    <div>
                        <span class="av-eyebrow">DAILY SCHEDULE</span>
                        <h2 id="avPeriodsHeading">
                            Your teaching periods
                        </h2>
                        <p>
                            Mark whether you can attend,
                            can't go, or are on leave.
                        </p>
                    </div>
                    <span class="av-count">09 PERIODS</span>
                </div>

                <div class="av-periods">

                    <?php foreach ($periods as $number => $time): ?>

                        <article class="av-period"
                                 id="period-<?= $number ?>">

                            <div class="av-period-left">
                                <div class="av-period-icon"
                                     aria-hidden="true">◷</div>

                                <div>
                                    <div class="av-period-number">
                                        Period <?= $number ?>
                                    </div>
                                    <div class="av-period-time">
                                        <?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <span class="av-selected"
                                          aria-live="polite"></span>
                                </div>
                            </div>

                            <div class="av-actions">
                                <button type="button"
                                        class="av-attend"
                                        onclick="setAvailability(<?= $number ?>, 'AVAILABLE')">
                                    ✓ CAN ATTEND
                                </button>

                                <button type="button"
                                        class="av-cant"
                                        onclick="setAvailability(<?= $number ?>, 'UNAVAILABLE')">
                                    ✕ CAN'T GO
                                </button>

                                <button type="button"
                                        class="av-leave"
                                        onclick="setAvailability(<?= $number ?>, 'LEAVE')">
                                    LEAVE
                                </button>
                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>
            </section>

            <!-- CLASS FOUND -->
            <section id="classBox"
                     class="av-panel"
                     aria-labelledby="classBoxHeading">

                <h3 id="classBoxHeading">
                    Class found for this period
                </h3>

                <div id="classDetails"
                     class="av-details-grid"></div>

                <button type="button"
                        class="av-primary"
                        onclick="startHandover()">
                    Find available faculty →
                </button>
            </section>

            <!-- AVAILABLE FACULTY -->
            <section id="facultyBox"
                     class="av-panel"
                     aria-labelledby="facultyBoxHeading">

                <h3 id="facultyBoxHeading">
                    Available faculty — same department
                </h3>

                <p id="facultyCount"
                   class="av-faculty-count"></p>

                <div id="facultyList"
                     class="av-faculty-list"></div>

                <button id="sendRequestButton"
                        type="button"
                        class="av-send"
                        style="display:none"
                        onclick="sendHandoverRequest()">
                    SEND HANDOVER REQUEST TO ALL
                </button>
            </section>

            <footer class="av-footer">
                <span>
                    © <?= date('Y') ?> Smart Faculty Assistant Portal
                </span>
                <span>Faculty Availability Management</span>
            </footer>

        </main>
    </div>
</div>

<script>
let selectedTimetableId = null;
let selectedPeriodId = null;
let selectedDate = null;

const $ = id => document.getElementById(id);
const dateInput = $('availabilityDate');

/* SHOW MESSAGES */

function notice(message, type = '') {
    const el = $('avNotice');
    el.textContent = message;
    el.className = 'av-notice' + (type ? ' ' + type : '');
    el.hidden = false;
}

/* CLEAR OLD HANDOVER SELECTION */

function resetHandover() {
    selectedTimetableId = null;
    selectedPeriodId = null;
    selectedDate = null;

    $('classBox').style.display = 'none';
    $('facultyBox').style.display = 'none';
    $('facultyList').replaceChildren();
    $('facultyCount').textContent = '';

    const sendButton = $('sendRequestButton');
    sendButton.style.display = 'none';
    sendButton.disabled = false;
    sendButton.textContent = 'SEND HANDOVER REQUEST TO ALL';
}

/* DATE CHANGE */

dateInput.addEventListener('change', () => {
    resetHandover();
    $('avNotice').hidden = true;

    document.querySelectorAll('.av-period').forEach(row => {
        row.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('is-selected');
        });

        row.querySelector('.av-selected').textContent = '';
    });

    loadSavedAvailability(dateInput.value);
});

/* MOBILE NAVIGATION */

(function() {
    const menu = $('avMenu');
    const sidebar = $('avSidebar');
    const overlay = $('avOverlay');

    const setOpen = open => {
        sidebar.classList.toggle('open', open);
        overlay.hidden = !open;
        menu.setAttribute('aria-expanded', String(open));
    };

    menu.addEventListener('click', () => {
        setOpen(!sidebar.classList.contains('open'));
    });

    overlay.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') setOpen(false);
    });
})();

/* CSRF TOKEN */
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/* SHARED POST REQUEST HELPER */

async function postJson(endpoint, values) {
    const formData = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
            formData.append(key, value);
        }
    });

    if (csrfToken && !formData.has('csrf_token')) {
        formData.append('csrf_token', csrfToken);
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        body: formData
    });

    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Invalid API response (' + response.status + '):', text);
        throw new Error(
            'Invalid server response (HTTP ' + response.status + '). Check PHP server logs.'
        );
    }
}

/* LOAD SAVED AVAILABILITY ON DATE SELECTION */

async function loadSavedAvailability(date) {
    if (!date) return;

    try {
        const response = await fetch('../api/availability.php?date=' + encodeURIComponent(date));
        if (!response.ok) return;

        const data = await response.json();
        if (data.success && data.availability) {
            Object.entries(data.availability).forEach(([pId, info]) => {
                if (info && info.status) {
                    showSelected(pId, info.status);
                }
            });
        }
    } catch (e) {
        console.warn('Unable to load saved availability:', e);
    }
}

/* HIGHLIGHT SAVED STATUS */

function showSelected(periodId, status) {
    const row = $('period-' + periodId);
    if (!row) return;

    row.querySelectorAll('button').forEach(btn => {
        btn.classList.remove('is-selected');
    });

    const cls = status === 'AVAILABLE'
        ? 'av-attend'
        : status === 'UNAVAILABLE'
            ? 'av-cant'
            : 'av-leave';

    const targetBtn = row.querySelector('.' + cls);
    if (targetBtn) {
        targetBtn.classList.add('is-selected');
    }

    const labels = {
        AVAILABLE: 'Can attend',
        UNAVAILABLE: "Can't go",
        LEAVE: 'Leave'
    };

    const selLabel = row.querySelector('.av-selected');
    if (selLabel) {
        selLabel.textContent = 'Saved: ' + (labels[status] || status);
    }
}

/* SAVE AVAILABILITY */

async function setAvailability(periodId, status) {
    const date = dateInput.value;

    if (!date) {
        alert('Please select a date.');
        return;
    }

    let reason = '';

    if (status === 'UNAVAILABLE' || status === 'LEAVE') {
        const promptMsg = status === 'UNAVAILABLE'
            ? "Why can't you attend this period? (Required)"
            : "Please enter the reason for leave: (Required)";

        const inputReason = prompt(promptMsg);

        if (inputReason === null) {
            // User cancelled prompt
            return;
        }

        reason = inputReason.trim();

        if (!reason) {
            alert('Please provide a reason.');
            return;
        }
    }

    const row = $('period-' + periodId);
    const buttons = row ? [...row.querySelectorAll('button')] : [];

    buttons.forEach(btn => btn.disabled = true);

    try {
        const data = await postJson('../api/availability.php', {
            date,
            period_id: periodId,
            status,
            reason
        });

        if (!data.success) {
            notice(
                data.message || 'Unable to save availability.',
                'error'
            );
            return;
        }

        notice(
            data.message || 'Availability saved successfully.',
            'success'
        );

        showSelected(periodId, status);

        if (status === 'UNAVAILABLE' || status === 'LEAVE') {
            await checkClass(date, periodId);
        } else {
            resetHandover();
        }

    } catch (e) {
        notice('Unable to save: ' + e.message, 'error');

    } finally {
        buttons.forEach(btn => btn.disabled = false);
    }
}

/* CHECK CLASS FOR SELECTED PERIOD */

async function checkClass(date, periodId) {
    try {
        const data = await postJson('../api/check_class.php', {
            date,
            period_id: periodId
        });

        if (!data.success || !data.class_found) {
            resetHandover();
            notice(
                data.message ||
                'No class is scheduled for this faculty in this period.'
            );
            return;
        }

        const c = data.class;

        selectedTimetableId = c.timetable_id;
        selectedPeriodId = periodId;
        selectedDate = date;

        const fields = [
            ['Date', date],
            ['Day', data.day],
            ['Period', 'Period ' + (c.period_id || periodId)],
            ['Subject', (c.subject_name ? (c.subject_name + ' (' + (c.subject_code || '') + ')') : ('Subject ' + (c.subject_id ?? '')))],
            ['Section', c.section_name ?? ('Section ' + (c.section_id ?? ''))],
            ['Classroom', c.room_number ?? (c.classroom_id ? 'Room ' + c.classroom_id : 'N/A')],
            ['Timetable ID', '#' + c.timetable_id]
        ];

        const details = $('classDetails');
        details.replaceChildren();

        fields.forEach(([label, value]) => {
            const cell = document.createElement('div');
            cell.className = 'av-detail';

            const name = document.createElement('span');
            name.textContent = label;

            const val = document.createElement('strong');
            val.textContent = value ?? '';

            cell.append(name, val);
            details.appendChild(cell);
        });

        $('classBox').style.display = 'block';
        $('facultyBox').style.display = 'none';

        $('classBox').scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });

    } catch (e) {
        notice(
            'Unable to check class: ' + e.message,
            'error'
        );
    }
}

/* FIND AVAILABLE FACULTY */

async function startHandover() {
    if (
        !selectedTimetableId ||
        !selectedPeriodId ||
        !selectedDate ||
        dateInput.value !== selectedDate
    ) {
        alert('Please select an unavailable class first.');
        return;
    }

    const box = $('facultyBox');
    const list = $('facultyList');
    const count = $('facultyCount');
    const btn = $('sendRequestButton');

    box.style.display = 'block';
    list.textContent = 'Checking available faculty...';
    count.textContent = '';
    btn.style.display = 'none';
    btn.disabled = false;
    btn.textContent = 'SEND HANDOVER REQUEST TO ALL';

    try {
        const data = await postJson(
            '../api/find_alternate_faculty.php',
            {
                date: selectedDate,
                period_id: selectedPeriodId
            }
        );

        if (!data.success) {
            list.textContent =
                data.message || 'Unable to find faculty.';
            return;
        }

        const facultyList = Array.isArray(data.faculty)
            ? data.faculty
            : [];

        count.textContent =
            facultyList.length +
            ' available faculty member' +
            (facultyList.length === 1 ? '' : 's') +
            ' found in the same department.';

        list.replaceChildren();

        if (!facultyList.length) {
            const msg = document.createElement('div');
            msg.className = 'av-info';

            msg.textContent =
                'No available faculty members were found ' +
                'in the same department for this period.';

            list.appendChild(msg);
            return;
        }

        facultyList.forEach(faculty => {
            const card = document.createElement('div');
            card.className = 'av-faculty-item';

            const name = document.createElement('strong');
            name.textContent = faculty.faculty_name || 'Faculty';
            card.appendChild(name);

            const fields = [
                ['Faculty code', faculty.faculty_code],
                ['Email', faculty.email],
                ['Designation', faculty.designation || 'N/A']
            ];

            fields.forEach(([label, value]) => {
                const row = document.createElement('p');

                const strong = document.createElement('b');
                strong.textContent = label + ': ';

                row.append(
                    strong,
                    document.createTextNode(value ?? '')
                );

                card.appendChild(row);
            });

            const pill = document.createElement('span');
            pill.className = 'av-pill';
            pill.textContent = '● Available';
            card.appendChild(pill);

            list.appendChild(card);
        });

        btn.style.display = 'inline-flex';

    } catch (e) {
        list.textContent =
            'Unable to find faculty: ' + e.message;
    }
}

/* SEND HANDOVER REQUEST */

async function sendHandoverRequest() {
    if (
        !selectedTimetableId ||
        !selectedPeriodId ||
        !selectedDate ||
        selectedDate !== dateInput.value
    ) {
        alert('No valid class selected.');
        return;
    }

    let reason = prompt('Enter the reason for handover:');

    if (reason === null || !reason.trim()) {
        alert('Please provide a handover reason.');
        return;
    }

    reason = reason.trim();

    const confirmed = confirm(
        'Send the handover request to ALL available faculty members?\n\n' +
        $('facultyCount').textContent
    );

    if (!confirmed) return;

    const btn = $('sendRequestButton');
    btn.disabled = true;
    btn.textContent = 'SENDING REQUESTS...';

    try {
        const data = await postJson(
            '../api/create_handover.php',
            {
                date: selectedDate,
                timetable_id: selectedTimetableId,
                reason
            }
        );

        if (!data.success) {
            alert(
                data.message || 'Unable to create handover request.'
            );

            btn.disabled = false;
            btn.textContent = 'SEND HANDOVER REQUEST TO ALL';
            return;
        }

        const count = data.request_count || 0;

        alert(
            'Handover request sent successfully!\n\n' +
            'Requests sent to ' + count +
            ' available faculty member' +
            (count === 1 ? '.' : 's.')
        );

        notice(
            'Handover request sent successfully.',
            'success'
        );

        btn.disabled = true;
        btn.textContent = 'REQUESTS SENT';

    } catch (e) {
        alert('Unable to send handover request: ' + e.message);

        btn.disabled = false;
        btn.textContent = 'SEND HANDOVER REQUEST TO ALL';
    }
}

// Initial load of saved availability for the selected date
if (dateInput && dateInput.value) {
    loadSavedAvailability(dateInput.value);
}
</script>

</body>
</html>
