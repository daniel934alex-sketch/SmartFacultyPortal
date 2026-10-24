
<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT
        notification_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];
$unreadCount = 0;

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;

    if (!$row['is_read']) {
        $unreadCount++;
    }
}

$stmt->close();

$totalCount = count($notifications);

$username = htmlspecialchars(
    (string)($_SESSION['username'] ?? 'Faculty'),
    ENT_QUOTES,
    'UTF-8'
);

$initial = strtoupper(substr($username, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Notifications | Smart Faculty</title>

    <link rel="stylesheet" href="../assets/css/style.css?v=dark3">

    <style>
        /* NOTIFICATIONS PAGE ONLY */

        html {
            scrollbar-gutter: stable;
            overflow-y: scroll;
        }

        body.fn-page {
            --fn-bg: #0c1220;
            --fn-sidebar: #111a2b;
            --fn-card: #172235;
            --fn-border: #2a364b;
            --fn-text: #f1f5ff;
            --fn-muted: #96a4bc;
            --fn-accent: #b5a3ff;

            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #0c1220;
            color: var(--fn-text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .fn-page *,
        .fn-page *::before,
        .fn-page *::after {
            box-sizing: border-box;
        }

        .fn-page a {
            text-decoration: none;
        }

        .fn-page svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* MAIN LAYOUT */

        .fn-page .fn-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .fn-page .fn-sidebar {
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
            background: var(--fn-sidebar);
            border-right: 1px solid var(--fn-border);
            overflow-y: auto;
            z-index: 30;
        }

        .fn-page .fn-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 7px 45px;
            color: white;
        }

        .fn-page .fn-brand-logo {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #a58aff, #6550cc);
        }

        .fn-page .fn-brand-logo svg {
            width: 27px;
            height: 27px;
            color: white;
        }

        .fn-page .fn-brand strong {
            display: block;
            font-size: 15px;
            white-space: nowrap;
        }

        .fn-page .fn-brand small {
            display: block;
            margin-top: 5px;
            color: #9dabc2;
            font-size: 9px;
            letter-spacing: 1.5px;
        }

        .fn-page .fn-sidebar-label {
            padding: 0 14px;
            margin-bottom: 15px;
            color: #687991;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.8px;
        }

        .fn-page .fn-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .fn-page .fn-nav-section {
            margin: 27px 0 15px;
        }

        .fn-page .fn-nav-link {
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

        .fn-page .fn-nav-link:hover {
            color: white;
            background: #202c41;
        }

        .fn-page .fn-nav-link.active {
            color: #e2d9ff;
            background: #302b53;
            border-color: #50467c;
            font-weight: 700;
        }

        .fn-page .fn-nav-link svg {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        .fn-page .fn-sidebar-bottom {
            margin-top: auto;
            padding-top: 35px;
        }

        .fn-page .fn-side-help {
            padding: 18px;
            border: 1px solid #3b375f;
            border-radius: 14px;
            background: linear-gradient(145deg, #2b294e, #1a2440);
        }

        .fn-page .fn-side-help strong {
            display: block;
            color: white;
            font-size: 13px;
        }

        .fn-page .fn-side-help p {
            margin: 9px 0 0;
            color: #a7b0ca;
            font-size: 11px;
            line-height: 1.7;
        }

        .fn-page .fn-side-logout {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 13px 14px;
            border-radius: 10px;
            color: #f1a4b1;
            font-size: 13px;
        }

        .fn-page .fn-side-logout:hover {
            background: #422536;
        }

        /* TOP HEADER */

        .fn-page .fn-content {
            flex: 1;
            min-width: 0;
        }

        .fn-page .fn-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 91px;
            padding: 17px 35px;
            background: #111a2b;
            border-bottom: 1px solid var(--fn-border);
        }

        .fn-page .fn-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .fn-page .fn-page-label {
            color: #9983ff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .fn-page .fn-topbar h2 {
            margin: 5px 0 0;
            color: #f4f5ff;
            font-size: 20px;
        }

        .fn-page .fn-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fn-page .fn-avatar {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            color: #e8deff;
            background: #393060;
            font-weight: 800;
        }

        .fn-page .fn-user-info strong {
            display: block;
            color: white;
            font-size: 13px;
        }

        .fn-page .fn-user-info span {
            display: block;
            margin-top: 5px;
            color: var(--fn-muted);
            font-size: 11px;
        }

        .fn-page .fn-menu-button {
            display: none;
            padding: 9px 12px;
            border: 1px solid #2a364b;
            border-radius: 9px;
            background: #263248;
            color: white;
            cursor: pointer;
        }

        /* PAGE CONTAINER */

        .fn-page .fn-main {
            width: min(1230px, 100%);
            margin: 0 auto;
            padding: 34px;
        }

        /* WELCOME BANNER */

        .fn-page .fn-hero {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 230px;
            padding: 37px 42px;
            overflow: hidden;
            border: 1px solid #51478c;
            border-radius: 21px;
            background: linear-gradient(
                115deg,
                #28244e,
                #39316e 55%,
                #5c4da0
            );
            box-shadow: 0 18px 45px #05081530;
        }

        .fn-page .fn-hero-content {
            position: relative;
            z-index: 1;
            max-width: 630px;
        }

        .fn-page .fn-eyebrow {
            color: #bfb0ff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.7px;
        }

        .fn-page .fn-hero h1 {
            margin: 14px 0 12px;
            color: white;
            font-size: clamp(26px, 3vw, 36px);
            line-height: 1.25;
        }

        .fn-page .fn-hero p {
            margin: 0;
            max-width: 530px;
            color: #cfcee7;
            font-size: 13px;
            line-height: 1.9;
        }

        .fn-page .fn-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 23px;
            padding: 12px 18px;
            border-radius: 10px;
            background: #b0a0ff;
            color: #191331;
            font-size: 12px;
            font-weight: 750;
        }

        .fn-page .fn-back:hover {
            background: #cec3ff;
        }

        .fn-page .fn-hero-art {
            flex: 0 0 140px;
            display: grid;
            place-items: center;
            width: 140px;
            height: 140px;
            margin-right: 20px;
            border-radius: 32px;
            border: 1px solid #ffffff35;
            background: #ffffff19;
            transform: rotate(-8deg);
        }

        .fn-page .fn-hero-art svg {
            width: 76px;
            height: 76px;
            color: #f6f2ff;
        }

        /* SUMMARY */

        .fn-page .fn-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-top: 25px;
        }

        .fn-page .fn-stat {
            padding: 22px;
            border: 1px solid #2a364b;
            border-radius: 16px;
            background: #172235;
        }

        .fn-page .fn-stat-label {
            display: block;
            color: #ac9af9;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .fn-page .fn-stat strong {
            display: block;
            margin: 12px 0 7px;
            color: white;
            font-size: 30px;
        }

        .fn-page .fn-stat small {
            color: var(--fn-muted);
            font-size: 11px;
        }

        /* NOTIFICATION SECTION */

        .fn-page .fn-section {
            margin-top: 38px;
        }

        .fn-page .fn-section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 22px;
        }

        .fn-page .fn-section-head h2 {
            margin: 8px 0;
            color: white;
            font-size: 22px;
        }

        .fn-page .fn-section-head p {
            margin: 0;
            color: var(--fn-muted);
            font-size: 13px;
        }

        .fn-page .fn-count-label {
            padding: 9px 12px;
            border: 1px solid #36465d;
            border-radius: 9px;
            background: #172235;
            color: #c4cbe0;
            font-size: 11px;
        }

        .fn-page .fn-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* NOTIFICATION CARDS */

        .fn-page .fn-notification {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 23px;
            border: 1px solid #2a364b;
            border-radius: 17px;
            background: #172235;
            transition: border-color .2s ease, background .2s ease;
        }

        .fn-page .fn-notification:hover {
            border-color: #5d5689;
            background: #1b2940;
        }

        .fn-page .fn-notification.unread {
            border-color: #6553a4;
            background: linear-gradient(110deg, #211f3b, #172235);
        }

        .fn-page .fn-icon {
            flex: 0 0 46px;
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border-radius: 13px;
            color: #b8a5ff;
            background: #393158;
        }

        .fn-page .fn-icon svg {
            width: 23px;
            height: 23px;
        }

        .fn-page .fn-icon.handover {
            color: #8ed9ff;
            background: #253b55;
        }

        .fn-page .fn-message-content {
            flex: 1;
            min-width: 0;
        }

        .fn-page .fn-message-heading {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-bottom: 9px;
        }

        .fn-page .fn-message-heading h3 {
            margin: 0;
            color: #f5f6ff;
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .fn-page .fn-new-badge {
            padding: 5px 9px;
            border-radius: 7px;
            color: #e0d5ff;
            background: #4b3b7c;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
        }

        .fn-page .fn-message {
            margin: 0 0 16px;
            color: #b3bed0;
            font-size: 13px;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .fn-page .fn-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 11px;
            align-items: center;
        }

        .fn-page .fn-type {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 8px;
            border: 1px solid #3a4560;
            color: #c5b6ff;
            background: #272b49;
            font-size: 11px;
            font-weight: 650;
        }

        .fn-page .fn-date {
            color: #8998b1;
            font-size: 11px;
        }

        .fn-page .fn-handover-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-top: 17px;
            padding: 11px 16px;
            border-radius: 9px;
            background: #b0a0ff;
            color: #211836;
            font-size: 12px;
            font-weight: 750;
        }

        .fn-page .fn-handover-btn:hover {
            background: #cec3ff;
        }

        /* EMPTY STATE */

        .fn-page .fn-empty {
            padding: 55px 25px;
            border: 1px solid #2a364b;
            border-radius: 17px;
            background: #172235;
            text-align: center;
        }

        .fn-page .fn-empty-icon {
            display: grid;
            place-items: center;
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 20px;
            background: #353054;
            color: #baaaff;
        }

        .fn-page .fn-empty-icon svg {
            width: 35px;
            height: 35px;
        }

        .fn-page .fn-empty h3 {
            margin: 0 0 9px;
            color: white;
            font-size: 18px;
        }

        .fn-page .fn-empty p {
            margin: 0;
            color: #96a4bc;
            font-size: 13px;
        }

        /* FOOTER */

        .fn-page .fn-footer {
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

        .fn-page a:focus-visible,
        .fn-page button:focus-visible {
            outline: 3px solid #b6a5ff;
            outline-offset: 3px;
        }

        /* TABLET */

        @media (max-width: 1100px) {
            .fn-page .fn-sidebar {
                flex: 0 0 235px;
                width: 235px;
                min-width: 235px;
                max-width: 235px;
            }

            .fn-page .fn-main {
                padding: 28px;
            }
        }

        /* MOBILE */

        @media (max-width: 760px) {
            .fn-page .fn-sidebar {
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

            .fn-page .fn-sidebar.open {
                transform: translateX(0);
            }

            .fn-page .fn-menu-button {
                display: inline-block;
            }

            .fn-page .fn-topbar {
                min-height: 76px;
                padding: 15px 20px;
            }

            .fn-page .fn-topbar h2 {
                font-size: 17px;
            }

            .fn-page .fn-user-info {
                display: none;
            }

            .fn-page .fn-main {
                padding: 20px 16px;
            }

            .fn-page .fn-hero {
                padding: 30px 24px;
                min-height: 0;
            }

            .fn-page .fn-hero-art {
                display: none;
            }

            .fn-page .fn-summary {
                grid-template-columns: 1fr;
            }

            .fn-page .fn-section-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .fn-page .fn-notification {
                gap: 12px;
                padding: 17px;
            }

            .fn-page .fn-icon {
                flex-basis: 40px;
                width: 40px;
                height: 40px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .fn-page *,
            .fn-page *::before,
            .fn-page *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body class="fn-page">

<div class="fn-layout">

    <!-- SIDEBAR -->
    <aside class="fn-sidebar" id="fn-sidebar">

        <a href="dashboard.php" class="fn-brand">
            <span class="fn-brand-logo">
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

        <div class="fn-sidebar-label">MAIN MENU</div>

        <nav class="fn-nav" aria-label="Faculty navigation">

            <a href="dashboard.php" class="fn-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>

            <a href="timetable.php" class="fn-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M7 3v4M17 3v4M3 10h18"/>
                    <path d="M8 14h3M14 14h3"/>
                </svg>
                My Timetable
            </a>

            <a href="notifications.php"
               class="fn-nav-link active"
               aria-current="page">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M10 21h4"/>
                </svg>
                Notifications
            </a>

            <a href="profile.php" class="fn-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
                My Profile
            </a>

            <div class="fn-sidebar-label fn-nav-section">
                FACULTY MANAGEMENT
            </div>

            <a href="availability.php" class="fn-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                Faculty Availability
            </a>

            <a href="requests.php" class="fn-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16l-4-4M20 7l-4 4"/>
                    <path d="M20 17H4l4-4M4 17l4 4"/>
                </svg>
                Handover Requests
            </a>
        </nav>

        <div class="fn-sidebar-bottom">
            <div class="fn-side-help">
                <strong>✦ Your teaching hub</strong>
                <p>Stay connected with important faculty updates.</p>
            </div>

            <a href="../auth/logout.php" class="fn-side-logout">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5M15 12H3"/>
                    <path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="fn-content">

        <header class="fn-topbar">
            <div class="fn-topbar-left">

                <button type="button"
                        id="fn-menu-button"
                        class="fn-menu-button"
                        aria-label="Toggle navigation"
                        aria-controls="fn-sidebar"
                        aria-expanded="false">
                    ☰
                </button>

                <div>
                    <span class="fn-page-label">FACULTY / UPDATES</span>
                    <h2>Notifications</h2>
                </div>
            </div>

            <div class="fn-user">
                <div class="fn-avatar"><?= $initial ?></div>

                <div class="fn-user-info">
                    <strong><?= $username ?></strong>
                    <span>Faculty Member</span>
                </div>
            </div>
        </header>

        <main class="fn-main">

            <!-- WELCOME BANNER -->
            <section class="fn-hero">

                <div class="fn-hero-content">
                    <span class="fn-eyebrow">
                        ✦ YOUR FACULTY UPDATES
                    </span>

                    <h1>My Notifications</h1>

                    <p>
                        Stay informed with faculty announcements,
                        important reminders and handover updates.
                        Everything in one place.
                    </p>

                    <a href="dashboard.php" class="fn-back">
                        ← Back to Dashboard
                    </a>
                </div>

                <div class="fn-hero-art" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                        <path d="M10 21h4"/>
                    </svg>
                </div>
            </section>

            <!-- SUMMARY -->
            <section class="fn-summary">

                <div class="fn-stat">
                    <span class="fn-stat-label">
                        TOTAL NOTIFICATIONS
                    </span>
                    <strong><?= $totalCount ?></strong>
                    <small>All received notifications</small>
                </div>

                <div class="fn-stat">
                    <span class="fn-stat-label">
                        UNREAD NOTIFICATIONS
                    </span>
                    <strong><?= $unreadCount ?></strong>
                    <small>Not yet marked as read</small>
                </div>

                <div class="fn-stat">
                    <span class="fn-stat-label">
                        READ NOTIFICATIONS
                    </span>
                    <strong><?= $totalCount - $unreadCount ?></strong>
                    <small>Previously read notifications</small>
                </div>

            </section>

            <!-- NOTIFICATIONS LIST -->
            <section class="fn-section">

                <div class="fn-section-head">
                    <div>
                        <span class="fn-eyebrow">MESSAGE CENTER</span>
                        <h2>Recent Notifications</h2>
                        <p>
                            Your notifications, ordered from newest to oldest.
                        </p>
                    </div>

                    <span class="fn-count-label">
                        <?= $totalCount ?> Notifications
                    </span>
                </div>

                <div class="fn-list">

                    <?php if ($totalCount === 0): ?>

                        <div class="fn-empty">
                            <div class="fn-empty-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                    <path d="M10 21h4"/>
                                </svg>
                            </div>

                            <h3>You're all caught up!</h3>

                            <p>No notifications available right now.</p>
                        </div>

                    <?php else: ?>

                        <?php foreach ($notifications as $notification): ?>

                            <?php
                            $isUnread = !$notification['is_read'];

                            $type = (string)(
                                $notification['notification_type']
                                ?? 'General'
                            );

                            $isHandover = strtoupper($type) === 'HANDOVER';
                            ?>

                            <article class="fn-notification <?= $isUnread ? 'unread' : '' ?>">

                                <div class="fn-icon <?= $isHandover ? 'handover' : '' ?>">
                                    <?php if ($isHandover): ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M4 7h16l-4-4M20 7l-4 4"/>
                                            <path d="M20 17H4l4-4M4 17l4 4"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24">
                                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                            <path d="M10 21h4"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>

                                <div class="fn-message-content">

                                    <div class="fn-message-heading">
                                        <h3>
                                            <?= htmlspecialchars(
                                                (string)$notification['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </h3>

                                        <?php if ($isUnread): ?>
                                            <span class="fn-new-badge">
                                                NEW
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <p class="fn-message"><?= htmlspecialchars(
                                        (string)$notification['message'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?></p>

                                    <div class="fn-meta">
                                        <span class="fn-type">
                                            <?= htmlspecialchars(
                                                $type,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                        <span class="fn-date">
                                            <?= htmlspecialchars(
                                                (string)$notification['created_at'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </div>

                                    <?php if ($isHandover): ?>

                                        <a href="requests.php"
                                           class="fn-handover-btn">
                                            View Handover Request →
                                        </a>

                                    <?php endif; ?>

                                </div>
                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </section>

            <footer class="fn-footer">
                <span>
                    © <?= date('Y') ?> Smart Faculty Assistant Portal
                </span>
                <span>Faculty Notifications</span>
            </footer>

        </main>
    </div>
</div>

<script>
(function () {
    const button = document.getElementById('fn-menu-button');
    const sidebar = document.getElementById('fn-sidebar');

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
</script>

</body>
</html>
