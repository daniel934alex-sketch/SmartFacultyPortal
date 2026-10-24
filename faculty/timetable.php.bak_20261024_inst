<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$faculty_sql = "
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
";

$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();

$faculty_result = $faculty_stmt->get_result();

if ($faculty_result->num_rows === 0) {
    die("Faculty record not found.");
}

$faculty = $faculty_result->fetch_assoc();
$faculty_id = $faculty['faculty_id'];
$faculty_stmt->close();

$sql = "
    SELECT
        t.day,
        t.period_id,
        t.subject_id,
        t.faculty_id,
        s.subject_name,
        t.status
    FROM timetable t
    LEFT JOIN subjects s
        ON t.subject_id = s.subject_id
    WHERE t.faculty_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

$timetable = [];

while ($row = $result->fetch_assoc()) {
    $day = strtoupper($row['day']);
    $period = (int)$row['period_id'];
    $timetable[$day][$period] = $row['subject_name'] ?? "Class";
}

$stmt->close();

$days = [
    "MONDAY",
    "TUESDAY",
    "WEDNESDAY",
    "THURSDAY",
    "FRIDAY",
    "SATURDAY"
];

$username = htmlspecialchars(
    (string)($_SESSION['username'] ?? 'Faculty'),
    ENT_QUOTES,
    'UTF-8'
);

$initial = strtoupper(substr($username, 0, 1));

$totalClasses = 0;
foreach ($days as $day) {
    for ($period = 1; $period <= 9; $period++) {
        $subject = $timetable[$day][$period] ?? '';
        if (trim((string)$subject) !== '') {
            $totalClasses++;
        }
    }
}

$today = strtoupper(date('l'));
$todayClasses = 0;
if (in_array($today, $days, true)) {
    for ($period = 1; $period <= 9; $period++) {
        $subject = $timetable[$today][$period] ?? '';
        if (trim((string)$subject) !== '') {
            $todayClasses++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable | Smart Faculty</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=dark3">

    <style>
        /* ONLY timetable page styling */
        .fd-dark.fd-timetable-page .tt-hero {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 220px;
            padding: 34px 38px;
            border: 1px solid #51478c;
            border-radius: 20px;
            background: linear-gradient(115deg, #28244e 0%, #39316e 55%, #5c4da0 100%);
            box-shadow: 0 18px 45px #05081530;
            overflow: hidden;
        }

        .fd-dark.fd-timetable-page .tt-hero-content {
            max-width: 620px;
            position: relative;
            z-index: 2;
        }

        .fd-dark.fd-timetable-page .tt-eyebrow {
            display: inline-block;
            color: #bfb0ff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.7px;
            margin-bottom: 12px;
        }

        .fd-dark.fd-timetable-page .tt-hero h1 {
            margin: 0 0 12px;
            color: #fff;
            font-size: clamp(28px, 3vw, 40px);
            line-height: 1.2;
        }

        .fd-dark.fd-timetable-page .tt-hero p {
            margin: 0;
            color: #d7d7ee;
            font-size: 14px;
            line-height: 1.8;
            max-width: 540px;
        }

        .fd-dark.fd-timetable-page .tt-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            padding: 12px 18px;
            border-radius: 10px;
            background: #b0a0ff;
            color: #1b1730;
            font-size: 12px;
            font-weight: 750;
            text-decoration: none;
            transition: .2s ease;
        }

        .fd-dark.fd-timetable-page .tt-back-btn:hover {
            background: #cec3ff;
            transform: translateY(-2px);
        }

        .fd-dark.fd-timetable-page .tt-hero-art {
            flex: 0 0 150px;
            width: 150px;
            height: 150px;
            display: grid;
            place-items: center;
            border-radius: 30px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.22);
            transform: rotate(-8deg);
        }

        .fd-dark.fd-timetable-page .tt-hero-art span {
            font-size: 64px;
            color: #fff;
        }

        .fd-dark.fd-timetable-page .tt-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-top: 26px;
        }

        .fd-dark.fd-timetable-page .tt-stat {
            padding: 22px;
            border: 1px solid #2a364b;
            border-radius: 16px;
            background: #172235;
        }

        .fd-dark.fd-timetable-page .tt-stat-label {
            display: block;
            color: #a690ff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            margin-bottom: 10px;
        }

        .fd-dark.fd-timetable-page .tt-stat strong {
            display: block;
            color: #fff;
            font-size: 30px;
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .fd-dark.fd-timetable-page .tt-stat small {
            color: #96a4bc;
            font-size: 12px;
        }

        .fd-dark.fd-timetable-page .tt-section {
            margin-top: 38px;
        }

        .fd-dark.fd-timetable-page .tt-section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 22px;
        }

        .fd-dark.fd-timetable-page .tt-section-head h2 {
            margin: 8px 0 8px;
            color: #fff;
            font-size: 24px;
        }

        .fd-dark.fd-timetable-page .tt-section-head p {
            margin: 0;
            color: #96a4bc;
            font-size: 13px;
        }

        .fd-dark.fd-timetable-page .tt-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid #36465d;
            border-radius: 999px;
            background: #172235;
            color: #cad3e1;
            font-size: 11px;
            font-weight: 650;
            white-space: nowrap;
        }

        .fd-dark.fd-timetable-page .tt-pill-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ed3a4;
            display: inline-block;
        }

        .fd-dark.fd-timetable-page .tt-table-wrap {
            border: 1px solid #2a364b;
            border-radius: 18px;
            background: #172235;
            overflow: hidden;
        }

        .fd-dark.fd-timetable-page .tt-table-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px;
            border-bottom: 1px solid #2a364b;
        }

        .fd-dark.fd-timetable-page .tt-table-top h3 {
            margin: 0 0 6px;
            color: #fff;
            font-size: 17px;
        }

        .fd-dark.fd-timetable-page .tt-table-top p {
            margin: 0;
            color: #96a4bc;
            font-size: 12px;
        }

        .fd-dark.fd-timetable-page .tt-badge {
            padding: 8px 12px;
            border: 1px solid #51467c;
            border-radius: 8px;
            background: #302b50;
            color: #cbbfff;
            font-size: 11px;
            font-weight: 750;
            white-space: nowrap;
        }

        .fd-dark.fd-timetable-page .tt-scroll {
            overflow-x: auto;
            width: 100%;
        }

        .fd-dark.fd-timetable-page .tt-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .fd-dark.fd-timetable-page .tt-table thead th {
            padding: 16px 10px;
            background: #202d43;
            color: #b7c3da;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            border-bottom: 1px solid #34435a;
        }

        .fd-dark.fd-timetable-page .tt-table thead th:first-child {
            min-width: 125px;
            text-align: left;
            padding-left: 20px;
            background: #25324a;
            position: sticky;
            left: 0;
            z-index: 3;
        }

        .fd-dark.fd-timetable-page .tt-table tbody th,
        .fd-dark.fd-timetable-page .tt-table tbody td {
            height: 86px;
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid #29364b;
        }

        .fd-dark.fd-timetable-page .tt-table tbody tr:last-child th,
        .fd-dark.fd-timetable-page .tt-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .fd-dark.fd-timetable-page .tt-day {
            min-width: 125px;
            text-align: left !important;
            padding-left: 20px !important;
            background: #1c293e;
            color: #e6e8f6;
            font-size: 12px;
            font-weight: 750;
            position: sticky;
            left: 0;
            z-index: 2;
        }

        .fd-dark.fd-timetable-page .tt-day span,
        .fd-dark.fd-timetable-page .tt-day small {
            display: block;
        }

        .fd-dark.fd-timetable-page .tt-day small {
            margin-top: 5px;
            color: #b9a7ff;
            font-size: 10px;
        }

        .fd-dark.fd-timetable-page .tt-table tbody tr:hover td {
            background: #1f2d43;
        }

        .fd-dark.fd-timetable-page .tt-today td {
            background: #211f3d;
        }

        .fd-dark.fd-timetable-page .tt-today .tt-day {
            background: #39315b;
            color: #fff;
        }

        .fd-dark.fd-timetable-page .tt-subject {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 150px;
            min-height: 40px;
            padding: 8px 10px;
            border: 1px solid #53457f;
            border-radius: 10px;
            background: #352c53;
            color: #e0d4ff;
            font-size: 11px;
            font-weight: 650;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .fd-dark.fd-timetable-page .tt-empty {
            color: #62728d;
            font-size: 18px;
        }

        .fd-dark.fd-timetable-page .tt-table-foot {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            padding: 16px 22px;
            border-top: 1px solid #2a364b;
        }

        .fd-dark.fd-timetable-page .tt-table-foot span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #a0aec2;
            font-size: 11px;
        }

        .fd-dark.fd-timetable-page .tt-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            background: #9d83ff;
            display: inline-block;
        }

        .fd-dark.fd-timetable-page .tt-box {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            border: 1px solid #6c7a91;
            display: inline-block;
        }

        @media (max-width: 1100px) {
            .fd-dark.fd-timetable-page .tt-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .fd-dark.fd-timetable-page .tt-hero {
                padding: 28px 22px;
                min-height: auto;
            }

            .fd-dark.fd-timetable-page .tt-hero-art {
                display: none;
            }

            .fd-dark.fd-timetable-page .tt-summary {
                grid-template-columns: 1fr;
            }

            .fd-dark.fd-timetable-page .tt-section-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .fd-dark.fd-timetable-page .tt-table-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .fd-dark.fd-timetable-page .tt-table thead th:first-child,
            .fd-dark.fd-timetable-page .tt-day {
                min-width: 105px;
                padding-left: 12px !important;
            }
        }
    </style>
</head>

<body class="fd-dark fd-timetable-page">

<div class="fd-layout">

    <!-- SIDEBAR -->
    <aside class="fd-sidebar" id="faculty-sidebar">

        <a href="dashboard.php" class="fd-brand">
            <span class="fd-brand-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

        <div class="fd-sidebar-label">MAIN MENU</div>

        <nav class="fd-nav" aria-label="Faculty navigation">

            <a href="dashboard.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="timetable.php" class="fd-nav-link active" aria-current="page">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M7 3v4M17 3v4M3 10h18"/>
                    <path d="M8 14h3M14 14h3M8 18h3"/>
                </svg>
                <span>My Timetable</span>
            </a>

            <a href="notifications.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M10 21h4"/>
                </svg>
                <span>Notifications</span>
            </a>

            <a href="profile.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
                <span>My Profile</span>
            </a>

            <div class="fd-sidebar-label fd-nav-section">
                FACULTY MANAGEMENT
            </div>

            <a href="availability.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <span>Faculty Availability</span>
            </a>

            <a href="requests.php" class="fd-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16l-4-4M20 7l-4 4"/>
                    <path d="M20 17H4l4-4M4 17l4 4"/>
                </svg>
                <span>Handover Requests</span>
            </a>
        </nav>

        <div class="fd-sidebar-bottom">
            <div class="fd-side-help">
                <span class="fd-help-icon">✦</span>
                <strong>Your teaching hub</strong>
                <p>Everything you need for your academic day.</p>
            </div>

            <a href="../auth/logout.php" class="fd-side-logout">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5M15 12H3"/>
                    <path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="fd-content">

        <header class="fd-topbar">
            <div class="fd-topbar-left">
                <button type="button" id="fd-menu-button"
                        class="fd-menu-button"
                        aria-label="Toggle navigation"
                        aria-controls="faculty-sidebar"
                        aria-expanded="false">
                    ☰
                </button>
                <div>
                    <span class="fd-page-label">FACULTY / SCHEDULE</span>
                    <h2>My Timetable</h2>
                </div>
            </div>

            <div class="fd-topbar-user">
                <div class="fd-user-avatar">
                    <?= $initial ?>
                </div>
                <div class="fd-user-details">
                    <strong><?= $username ?></strong>
                    <span>Faculty Member</span>
                </div>
            </div>
        </header>

        <main class="fd-main">

            <section class="tt-hero">
                <div class="tt-hero-content">
                    <span class="tt-eyebrow">✦ YOUR TEACHING SCHEDULE</span>
                    <h1>My Weekly Timetable</h1>
                    <p>
                        Stay organized with a clear overview of your classes,
                        subjects and weekly schedule.
                    </p>
                    <a href="dashboard.php" class="tt-back-btn">← Back to Dashboard</a>
                </div>

                <div class="tt-hero-art" aria-hidden="true">
                    <span>🗓</span>
                </div>
            </section>

            <section class="tt-summary">
                <div class="tt-stat">
                    <span class="tt-stat-label">WEEKLY CLASSES</span>
                    <strong><?= $totalClasses ?></strong>
                    <small>Scheduled class periods</small>
                </div>

                <div class="tt-stat">
                    <span class="tt-stat-label">TODAY'S CLASSES</span>
                    <strong><?= $todayClasses ?></strong>
                    <small><?= htmlspecialchars(date('l')) ?></small>
                </div>

                <div class="tt-stat">
                    <span class="tt-stat-label">TIMETABLE DAYS</span>
                    <strong>06</strong>
                    <small>Monday to Saturday</small>
                </div>

                <div class="tt-stat">
                    <span class="tt-stat-label">DAILY PERIODS</span>
                    <strong>09</strong>
                    <small>P1 to P9</small>
                </div>
            </section>

            <section class="tt-section">
                <div class="tt-section-head">
                    <div>
                        <span class="fd-section-eyebrow">ACADEMIC SCHEDULE</span>
                        <h2>Weekly Class Schedule</h2>
                        <p>View your assigned subjects across nine periods each day.</p>
                    </div>

                    <span class="tt-pill">
                        <span class="tt-pill-dot"></span>
                        Faculty Timetable
                    </span>
                </div>

                <div class="tt-table-wrap">
                    <div class="tt-table-top">
                        <div>
                            <h3>Class Timetable</h3>
                            <p>Scroll horizontally to view all periods on smaller screens.</p>
                        </div>
                        <span class="tt-badge">P1 – P9</span>
                    </div>

                    <div class="tt-scroll">
                        <table class="tt-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <?php for ($period = 1; $period <= 9; $period++): ?>
                                        <th>P<?= $period ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $day): ?>
                                    <?php $isToday = ($day === $today); ?>
                                    <tr class="<?= $isToday ? 'tt-today' : '' ?>">
                                        <th class="tt-day">
                                            <span><?= ucfirst(strtolower($day)) ?></span>
                                            <?php if ($isToday): ?>
                                                <small>Today</small>
                                            <?php endif; ?>
                                        </th>

                                        <?php for ($period = 1; $period <= 9; $period++): ?>
                                            <?php
                                            $subject = $timetable[$day][$period] ?? '';
                                            $hasClass = trim((string)$subject) !== '';
                                            ?>
                                            <td>
                                                <?php if ($hasClass): ?>
                                                    <span class="tt-subject">
                                                        <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="tt-empty">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="tt-table-foot">
                        <span><span class="tt-dot"></span> Assigned subject</span>
                        <span><span class="tt-box"></span> Free period</span>
                    </div>
                </div>
            </section>

            <footer class="fd-footer">
                <span>© <?= date('Y') ?> Smart Faculty Assistant Portal</span>
                <span>Faculty Timetable</span>
            </footer>

        </main>
    </div>
</div>

<script>
(function () {
    const menuButton = document.getElementById('fd-menu-button');
    const sidebar = document.getElementById('faculty-sidebar');

    if (!menuButton || !sidebar) return;

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
})();
</script>

</body>
</html>