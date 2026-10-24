
<?php

date_default_timezone_set("Asia/Kolkata");

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "Student Announcements";

/* ==========================================
   SAFE OUTPUT HELPERS
   ========================================== */

function announcementEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function announcementDate($value)
{
    $timestamp = strtotime((string)$value);

    return $timestamp === false
        ? "Date unavailable"
        : date("d M Y, h:i A", $timestamp);
}

/* ==========================================
   STUDENT SESSION
   ========================================== */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

/* ==========================================
   LOAD CURRENT STUDENT
   ========================================== */

$studentSql = "
    SELECT
        s.student_name,
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
$sectionName = $student["section_name"];

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

/* ==========================================
   LOAD ANNOUNCEMENTS
   Keep original query and visibility logic.
   ========================================== */

$announcementSql = "
    SELECT
        announcement_id,
        title,
        message,
        created_at
    FROM announcements
    ORDER BY created_at DESC
";

$announcementStmt = $conn->prepare($announcementSql);

if (!$announcementStmt) {
    die("Announcement query preparation failed.");
}

$announcementStmt->execute();

$announcementResult = $announcementStmt->get_result();

$announcements = [];

while ($row = $announcementResult->fetch_assoc()) {
    $announcements[] = $row;
}

$announcementStmt->close();

$announcementCount = count($announcements);

/* ==========================================
   DISPLAY INFORMATION
   ========================================== */

$latestAnnouncement = $announcementCount > 0
    ? $announcements[0]
    : null;

$latestDate = $latestAnnouncement
    ? announcementDate($latestAnnouncement["created_at"])
    : "No announcements yet";

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
        Announcements | SmartFacultyPortal
    </title>

    <style>
    /* ==========================================
       STUDENT ANNOUNCEMENTS
       PREMIUM NAVY + VIOLET THEME
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
       MATCHES STUDENT DASHBOARD
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

    .ann-header {
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

    .ann-header::after {
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

    .ann-eyebrow {
        display: inline-block;
        margin-bottom: 12px;

        color: #d0c3ff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    .ann-header h1 {
        margin: 0 0 10px;

        color: #ffffff;
        font-size: clamp(25px, 3vw, 34px);
        font-weight: 800;
        line-height: 1.25;
    }

    .ann-header p {
        margin: 0;
        color: #e2dbf9;
        font-size: 13px;
        line-height: 1.75;
    }

    .ann-header-tag {
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

    .ann-stats {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));

        gap: 17px;
        margin-bottom: 26px;
    }

    .ann-stat {
        min-width: 0;
        padding: 23px;

        border: 1px solid var(--border);
        border-radius: 16px;

        background: var(--surface);
        box-shadow: 0 10px 25px rgba(0,0,0,.1);
    }

    .ann-stat-label {
        margin-bottom: 13px;
        color: #b0bed5;
        font-size: 12px;
        font-weight: 700;
    }

    .ann-stat-value {
        color: #ffffff;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ann-stat-value.date {
        font-size: 17px;
    }

    .ann-stat-foot {
        margin-top: 8px;
        color: #91a1bb;
        font-size: 11px;
        line-height: 1.6;
    }

    /* ==========================================
       ANNOUNCEMENTS PANEL
       ========================================== */

    .ann-panel {
        padding: 26px;
        margin-bottom: 25px;

        border: 1px solid var(--border);
        border-radius: 17px;

        background: var(--surface);
        box-shadow: 0 10px 28px rgba(0,0,0,.1);
    }

    .ann-panel-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;

        margin-bottom: 24px;
    }

    .ann-panel-heading h2 {
        margin: 0;
        color: #ffffff;
        font-size: 20px;
        font-weight: 800;
    }

    .ann-panel-heading p {
        margin: 6px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    .ann-count {
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
       ANNOUNCEMENT CARDS
       ========================================== */

    .ann-list {
        display: flex;
        flex-direction: column;
        gap: 17px;
    }

    .ann-card {
        position: relative;
        overflow: hidden;
        min-width: 0;

        padding: 24px;

        border: 1px solid #34445e;
        border-radius: 14px;

        background: #111d30;

        transition:
            background .2s ease,
            border-color .2s ease;
    }

    .ann-card:hover {
        border-color: #645891;
        background: #1b2943;
    }

    .ann-card.featured {
        border-color: #685b9d;

        background: linear-gradient(
            120deg,
            #231f43,
            #1c2941
        );
    }

    .ann-card::before {
        content: "";

        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;

        width: 4px;

        background: #8273c9;
    }

    .ann-card.featured::before {
        background: #c5b5ff;
    }

    .ann-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;

        margin-bottom: 12px;
    }

    .ann-card-title {
        min-width: 0;
        margin: 0;

        color: #ffffff;
        font-size: 17px;
        font-weight: 800;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .ann-latest-badge {
        display: inline-flex;
        align-items: center;

        flex-shrink: 0;

        padding: 6px 11px;

        border: 1px solid #7160a6;
        border-radius: 100px;

        background: #3c326b;
        color: #e2d7ff;

        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .ann-message {
        margin: 0;

        color: #cad5e8;
        font-size: 13px;
        line-height: 1.9;

        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .ann-card-footer {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-wrap: wrap;

        margin-top: 20px;
        padding-top: 15px;

        border-top: 1px solid #34435c;
    }

    .ann-calendar-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        width: 27px;
        height: 27px;

        border-radius: 8px;
        background: #2d294f;

        color: #d5c8ff;
        font-size: 13px;
    }

    .ann-date {
        color: #a6b5cd;
        font-size: 11px;
        font-weight: 650;
    }

    /* ==========================================
       EMPTY STATE
       ========================================== */

    .ann-empty {
        padding: 60px 20px;
        text-align: center;
    }

    .ann-empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;

        width: 75px;
        height: 75px;

        margin: 0 auto 19px;

        border: 1px solid #554982;
        border-radius: 22px;

        background: #302a55;
        color: #d6caff;

        font-size: 35px;
    }

    .ann-empty h3 {
        margin: 0 0 10px;

        color: #ffffff;
        font-size: 20px;
    }

    .ann-empty p {
        max-width: 470px;
        margin: 0 auto;

        color: #a8b6ce;
        font-size: 13px;
        line-height: 1.8;
    }

    /* ==========================================
       RESPONSIVE LAYOUT
       ========================================== */

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

        .ann-header {
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

        .ann-header {
            padding: 24px 20px;
        }

        .ann-stats {
            grid-template-columns: 1fr;
        }

        .ann-panel {
            padding: 18px;
        }

        .ann-card {
            padding: 19px;
        }

        .ann-card-title {
            font-size: 15px;
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

            <a href="attendance.php">
                <span class="student-menu-icon">◫</span>
                Attendance
            </a>

            <a
                href="announcements.php"
                class="active"
                aria-current="page"
            >
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
                    <?= announcementEscape($studentName) ?>
                </strong>

                <span>
                    Section
                    <?= announcementEscape($sectionName) ?>
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
         MAIN CONTENT
         ===================================== -->

    <main class="student-main">

        <!-- TOPBAR -->

        <header class="student-topbar">

            <div>

                <h2 class="student-topbar-title">
                    Student Announcements
                </h2>

                <span class="student-topbar-subtitle">
                    College updates and important notices
                </span>

            </div>

            <div class="student-account">

                <div>

                    <div class="student-account-name">
                        <?= announcementEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>

                </div>

                <div class="student-avatar">
                    <?= announcementEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <div class="student-content">

            <!-- =================================
                 PAGE BANNER
                 ================================= -->

            <section class="ann-header">

                <div>

                    <span class="ann-eyebrow">
                        Student Notice Board
                    </span>

                    <h1>
                        Announcements
                    </h1>

                    <p>
                        Stay informed with the latest
                        notices and announcements.

                        <br>

                        <?= announcementEscape($studentName) ?>

                        &nbsp; • &nbsp;

                        Section
                        <?= announcementEscape($sectionName) ?>
                    </p>

                </div>

                <span class="ann-header-tag">
                    Student Updates
                </span>

            </section>


            <!-- =================================
                 SUMMARY
                 ================================= -->

            <section
                class="ann-stats"
                aria-label="Announcement summary"
            >

                <div class="ann-stat">

                    <div class="ann-stat-label">
                        Total Announcements
                    </div>

                    <div class="ann-stat-value">
                        <?= $announcementCount ?>
                    </div>

                    <div class="ann-stat-foot">
                        Notices available on the portal
                    </div>

                </div>

                <div class="ann-stat">

                    <div class="ann-stat-label">
                        Latest Announcement
                    </div>

                    <div class="ann-stat-value date">
                        <?= announcementEscape($latestDate) ?>
                    </div>

                    <div class="ann-stat-foot">
                        Most recently posted notice
                    </div>

                </div>

            </section>


            <!-- =================================
                 ANNOUNCEMENT LIST
                 ================================= -->

            <section class="ann-panel">

                <div class="ann-panel-heading">

                    <div>

                        <h2>
                            Notice Board
                        </h2>

                        <p>
                            Announcements are displayed
                            with the newest posts first.
                        </p>

                    </div>

                    <span class="ann-count">
                        <?= $announcementCount ?>
                        <?= $announcementCount === 1
                            ? "Announcement"
                            : "Announcements" ?>
                    </span>

                </div>

                <?php if ($announcementCount === 0): ?>

                    <!-- EMPTY STATE -->

                    <div class="ann-empty">

                        <div class="ann-empty-icon">
                            ◉
                        </div>

                        <h3>
                            No Announcements Yet
                        </h3>

                        <p>
                            New notices will appear here
                            when announcements are added
                            to the portal.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="ann-list">

                        <?php foreach (
                            $announcements as $index => $announcement
                        ): ?>

                            <article
                                class="ann-card <?= $index === 0
                                    ? "featured"
                                    : "" ?>"
                            >

                                <div class="ann-card-top">

                                    <h3 class="ann-card-title">
                                        <?= announcementEscape(
                                            $announcement["title"]
                                        ) ?>
                                    </h3>

                                    <?php if ($index === 0): ?>

                                        <span class="ann-latest-badge">
                                            Latest
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- MESSAGE -->

                                <p class="ann-message"><?= announcementEscape(
                                    $announcement["message"]
                                ) ?></p>


                                <!-- POSTED DATE -->

                                <div class="ann-card-footer">

                                    <span class="ann-calendar-icon">
                                        ◷
                                    </span>

                                    <span class="ann-date">
                                        Posted:
                                        <?= announcementEscape(
                                            announcementDate(
                                                $announcement["created_at"]
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</body>
</html>
