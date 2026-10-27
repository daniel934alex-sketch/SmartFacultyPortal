
<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT
        faculty_code,
        faculty_name,
        email,
        phone,
        designation
    FROM faculty
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$faculty = $result->fetch_assoc();

if (!$faculty) {
    die("Faculty profile not found.");
}

$stmt->close();

function profileEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$username = profileEscape($_SESSION['username'] ?? 'Faculty');
$facultyName = (string)($faculty['faculty_name'] ?? 'Faculty');
$initial = strtoupper(substr($facultyName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile | Smart Faculty</title>

<style>
/* =========================================
   PREMIUM DARK FACULTY PROFILE
   All styling belongs to this page only
   ========================================= */

html {
    scrollbar-gutter: stable;
    overflow-y: scroll;
}

body.fp-page {
    --bg: #0c1220;
    --sidebar: #111a2b;
    --panel: #172235;
    --border: #2a364b;
    --text: #f1f5ff;
    --muted: #96a4bc;
    --accent: #b5a3ff;

    margin: 0;
    padding: 0;
    min-height: 100vh;
    background: var(--bg);
    color: var(--text);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.fp-page *,
.fp-page *::before,
.fp-page *::after {
    box-sizing: border-box;
}

.fp-page a {
    text-decoration: none;
}

.fp-page svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* PAGE LAYOUT */

.fp-page .fp-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.fp-page .fp-sidebar {
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
    background: var(--sidebar);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    z-index: 30;
}

.fp-page .fp-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 7px 45px;
    color: #fff;
}

.fp-page .fp-brand-logo {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 13px;
    background: linear-gradient(
        135deg,
        #a58aff,
        #6550cc
    );
}

.fp-page .fp-brand-logo svg {
    width: 27px;
    height: 27px;
}

.fp-page .fp-brand strong {
    display: block;
    font-size: 15px;
    white-space: nowrap;
}

.fp-page .fp-brand small {
    display: block;
    margin-top: 5px;
    color: #9dabc2;
    font-size: 9px;
    letter-spacing: 1.5px;
}

.fp-page .fp-sidebar-label {
    padding: 0 14px;
    margin-bottom: 15px;
    color: #687991;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.8px;
}

.fp-page .fp-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.fp-page .fp-nav-section {
    margin: 27px 0 15px;
}

.fp-page .fp-nav-link {
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

.fp-page .fp-nav-link:hover {
    color: #fff;
    background: #202c41;
}

.fp-page .fp-nav-link.active {
    color: #e2d9ff;
    background: #302b53;
    border-color: #50467c;
    font-weight: 700;
}

.fp-page .fp-nav-link svg {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
}

.fp-page .fp-sidebar-bottom {
    margin-top: auto;
    padding-top: 35px;
}

.fp-page .fp-side-help {
    padding: 18px;
    border: 1px solid #3b375f;
    border-radius: 14px;
    background: linear-gradient(
        145deg,
        #2b294e,
        #1a2440
    );
}

.fp-page .fp-side-help strong {
    display: block;
    color: #fff;
    font-size: 13px;
}

.fp-page .fp-side-help p {
    margin: 9px 0 0;
    color: #a7b0ca;
    font-size: 11px;
    line-height: 1.7;
}

.fp-page .fp-side-logout {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    padding: 13px 14px;
    border-radius: 10px;
    color: #f1a4b1;
    font-size: 13px;
}

.fp-page .fp-side-logout:hover {
    background: #422536;
}

/* MAIN CONTENT */

.fp-page .fp-content {
    flex: 1;
    min-width: 0;
}

.fp-page .fp-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    min-height: 91px;
    padding: 17px 35px;
    background: #111a2b;
    border-bottom: 1px solid var(--border);
}

.fp-page .fp-topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.fp-page .fp-page-label {
    color: #9983ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.fp-page .fp-topbar h2 {
    margin: 5px 0 0;
    color: #f4f5ff;
    font-size: 20px;
}

.fp-page .fp-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.fp-page .fp-avatar-small {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    color: #e8deff;
    background: #393060;
    font-weight: 800;
}

.fp-page .fp-user-info strong {
    display: block;
    font-size: 13px;
}

.fp-page .fp-user-info span {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
}

.fp-page .fp-menu-button {
    display: none;
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: #263248;
    color: #fff;
    cursor: pointer;
}

.fp-page .fp-main {
    width: min(1230px, 100%);
    margin: 0 auto;
    padding: 34px;
    box-sizing: border-box;
}

/* HERO BANNER */

.fp-page .fp-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 220px;
    gap: 25px;
    padding: 36px 42px;
    border: 1px solid #51478c;
    border-radius: 21px;
    overflow: hidden;
    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 55%,
        #5c4da0
    );
}

.fp-page .fp-eyebrow {
    color: #bfb0ff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.7px;
}

.fp-page .fp-hero h1 {
    margin: 14px 0 12px;
    color: #fff;
    font-size: clamp(26px, 3vw, 36px);
}

.fp-page .fp-hero p {
    max-width: 510px;
    margin: 0;
    color: #d2cfea;
    font-size: 13px;
    line-height: 1.8;
}

.fp-page .fp-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 23px;
    padding: 12px 18px;
    border-radius: 10px;
    color: #191331;
    background: #b0a0ff;
    font-size: 12px;
    font-weight: 750;
}

.fp-page .fp-back:hover {
    background: #cec3ff;
}

.fp-page .fp-hero-art {
    display: grid;
    place-items: center;
    flex: 0 0 140px;
    width: 140px;
    height: 140px;
    margin-right: 15px;
    border: 1px solid #ffffff35;
    border-radius: 32px;
    background: #ffffff19;
    transform: rotate(-8deg);
}

.fp-page .fp-hero-art svg {
    width: 75px;
    height: 75px;
    color: #fff;
}

/* PROFILE IDENTITY CARD */

.fp-page .fp-identity {
    display: flex;
    align-items: center;
    gap: 22px;
    margin-top: 26px;
    padding: 27px;
    border: 1px solid var(--border);
    border-radius: 18px;
    background: var(--panel);
}

.fp-page .fp-big-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 82px;
    width: 82px;
    height: 82px;
    border: 2px solid #7160aa;
    border-radius: 23px;
    background: linear-gradient(
        135deg,
        #443b78,
        #302c57
    );
    color: #e6dfff;
    font-size: 34px;
    font-weight: 800;
}

.fp-page .fp-identity-details {
    min-width: 0;
}

.fp-page .fp-identity-details h2 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 23px;
    overflow-wrap: anywhere;
}

.fp-page .fp-identity-details p {
    margin: 0 0 11px;
    color: #a7b5c8;
    font-size: 13px;
}

.fp-page .fp-code-badge {
    display: inline-flex;
    padding: 7px 11px;
    border: 1px solid #50467c;
    border-radius: 8px;
    background: #302b50;
    color: #cbbfff;
    font-size: 11px;
    font-weight: 750;
}

/* SECTION HEADINGS */

.fp-page .fp-section {
    margin-top: 38px;
}

.fp-page .fp-section-head {
    margin-bottom: 21px;
}

.fp-page .fp-section-head h2 {
    margin: 8px 0;
    color: #fff;
    font-size: 22px;
}

.fp-page .fp-section-head p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

/* DETAILS GRID */

.fp-page .fp-details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 19px;
}

.fp-page .fp-detail-card {
    padding: 24px;
    border: 1px solid var(--border);
    border-radius: 17px;
    background: var(--panel);
    transition: .2s ease;
}

.fp-page .fp-detail-card:hover {
    border-color: #655c92;
    background: #1b2940;
}

.fp-page .fp-detail-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.fp-page .fp-detail-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    color: #bba9ff;
    background: #393158;
}

.fp-page .fp-detail-icon svg {
    width: 21px;
    height: 21px;
}

.fp-page .fp-detail-head h3 {
    margin: 0;
    color: #fff;
    font-size: 16px;
}

.fp-page .fp-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    padding: 16px 0;
    border-bottom: 1px solid #2a364b;
}

.fp-page .fp-info-row:last-child {
    border-bottom: 0;
}

.fp-page .fp-info-label {
    color: #96a4bc;
    font-size: 12px;
    white-space: nowrap;
}

.fp-page .fp-info-value {
    color: #f1f5ff;
    font-size: 13px;
    font-weight: 650;
    text-align: right;
    overflow-wrap: anywhere;
}

.fp-page .fp-info-value.email {
    color: #c5b6ff;
}

/* FOOTER */

.fp-page .fp-footer {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 40px;
    padding: 22px 0;
    border-top: 1px solid var(--border);
    color: #77879e;
    font-size: 11px;
}

/* ACCESSIBILITY */

.fp-page a:focus-visible,
.fp-page button:focus-visible {
    outline: 3px solid #b6a5ff;
    outline-offset: 3px;
}

/* TABLET */

@media (max-width: 1100px) {
    .fp-page .fp-sidebar {
        flex: 0 0 235px;
        width: 235px;
        min-width: 235px;
        max-width: 235px;
    }

    .fp-page .fp-main {
        padding: 28px;
    }

    .fp-page .fp-details-grid {
        grid-template-columns: 1fr;
    }
}

/* MOBILE */

@media (max-width: 760px) {
    .fp-page .fp-sidebar {
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

    .fp-page .fp-sidebar.open {
        transform: translateX(0);
    }

    .fp-page .fp-menu-button {
        display: inline-block;
    }

    .fp-page .fp-topbar {
        min-height: 76px;
        padding: 15px 20px;
    }

    .fp-page .fp-topbar h2 {
        font-size: 17px;
    }

    .fp-page .fp-user-info {
        display: none;
    }

    .fp-page .fp-main {
        padding: 20px 16px;
    }

    .fp-page .fp-hero {
        min-height: 0;
        padding: 30px 24px;
    }

    .fp-page .fp-hero-art {
        display: none;
    }

    .fp-page .fp-identity {
        padding: 20px;
        gap: 15px;
    }

    .fp-page .fp-big-avatar {
        flex-basis: 62px;
        width: 62px;
        height: 62px;
        border-radius: 16px;
        font-size: 26px;
    }

    .fp-page .fp-identity-details h2 {
        font-size: 19px;
    }

    .fp-page .fp-details-grid {
        grid-template-columns: 1fr;
    }

    .fp-page .fp-info-row {
        flex-direction: column;
        gap: 7px;
    }

    .fp-page .fp-info-value {
        text-align: left;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fp-page *,
    .fp-page *::before,
    .fp-page *::after {
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body class="fp-page">

<div class="fp-layout">

    <!-- SIDEBAR -->
    <aside class="fp-sidebar" id="fp-sidebar">

        <a href="dashboard.php" class="fp-brand">
            <span class="fp-brand-logo">
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

        <div class="fp-sidebar-label">MAIN MENU</div>

        <nav class="fp-nav" aria-label="Faculty navigation">

            <a href="dashboard.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>

            <a href="timetable.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                    <path d="M7 3v4M17 3v4M3 10h18"/>
                    <path d="M8 14h3M14 14h3"/>
                </svg>
                My Timetable
            </a>
            <a href="mark-attendance.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                   <rect x="4" y="3" width="16" height="18" rx="2"/>
                   <path d="M8 8h8M8 12h8M8 16h5"/>
                </svg>
               Mark Attendance
            </a>

            <a href="notifications.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M10 21h4"/>
                </svg>
                Notifications
            </a>

            <a href="profile.php"
               class="fp-nav-link active"
               aria-current="page">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
                My Profile
            </a>

            <div class="fp-sidebar-label fp-nav-section">
                FACULTY MANAGEMENT
            </div>

            <a href="availability.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                Faculty Availability
            </a>

            <a href="requests.php" class="fp-nav-link">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7h16l-4-4M20 7l-4 4"/>
                    <path d="M20 17H4l4-4M4 17l4 4"/>
                </svg>
                Handover Requests
            </a>

        </nav>

        <div class="fp-sidebar-bottom">

            <div class="fp-side-help">
                <strong>✦ Your teaching hub</strong>
                <p>
                    Manage your faculty information
                    from your personal workspace.
                </p>
            </div>

            <a href="../auth/logout.php" class="fp-side-logout">
                <svg viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5M15 12H3"/>
                    <path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/>
                </svg>
                Logout
            </a>

        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="fp-content">

        <header class="fp-topbar">
            <div class="fp-topbar-left">

                <button type="button"
                        id="fp-menu-button"
                        class="fp-menu-button"
                        aria-label="Toggle navigation"
                        aria-controls="fp-sidebar"
                        aria-expanded="false">
                    ☰
                </button>

                <div>
                    <span class="fp-page-label">FACULTY / ACCOUNT</span>
                    <h2>My Profile</h2>
                </div>

            </div>

            <div class="fp-user">
                <div class="fp-avatar-small">
                    <?= profileEscape($initial) ?>
                </div>
                <div class="fp-user-info">
                    <strong><?= $username ?></strong>
                    <span>Faculty Member</span>
                </div>
            </div>
        </header>

        <main class="fp-main">

            <!-- HEADER -->
            <section class="fp-hero">

                <div>
                    <span class="fp-eyebrow">
                        ✦ YOUR FACULTY ACCOUNT
                    </span>

                    <h1>My Faculty Profile</h1>

                    <p>
                        View your personal and professional
                        faculty details in one convenient place.
                    </p>

                    <a href="dashboard.php" class="fp-back">
                        ← Back to Dashboard
                    </a>
                </div>

                <div class="fp-hero-art" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 21a8 8 0 0 1 16 0"/>
                    </svg>
                </div>

            </section>

            <!-- IDENTITY CARD -->
            <section class="fp-identity">

                <div class="fp-big-avatar">
                    <?= profileEscape($initial) ?>
                </div>

                <div class="fp-identity-details">

                    <h2>
                        <?= profileEscape($faculty['faculty_name']) ?>
                    </h2>

                    <p>
                        <?= profileEscape($faculty['designation']) ?>
                    </p>

                    <span class="fp-code-badge">
                        Faculty ID:
                        <?= profileEscape($faculty['faculty_code']) ?>
                    </span>

                </div>
            </section>

            <!-- DETAILS -->
            <section class="fp-section">

                <div class="fp-section-head">
                    <span class="fp-eyebrow">PROFILE INFORMATION</span>
                    <h2>Faculty Details</h2>
                    <p>
                        Your information as stored in the faculty records.
                    </p>
                </div>

                <div class="fp-details-grid">

                    <!-- PERSONAL INFORMATION -->
                    <div class="fp-detail-card">

                        <div class="fp-detail-head">
                            <span class="fp-detail-icon">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="8" r="4"/>
                                    <path d="M4 21a8 8 0 0 1 16 0"/>
                                </svg>
                            </span>

                            <h3>Personal Information</h3>
                        </div>

                        <div class="fp-info-row">
                            <span class="fp-info-label">Full Name</span>
                            <span class="fp-info-value">
                                <?= profileEscape($faculty['faculty_name']) ?>
                            </span>
                        </div>

                        <div class="fp-info-row">
                            <span class="fp-info-label">Email Address</span>
                            <span class="fp-info-value email">
                                <?= profileEscape($faculty['email']) ?>
                            </span>
                        </div>

                        <div class="fp-info-row">
                            <span class="fp-info-label">Phone Number</span>
                            <span class="fp-info-value">
                                <?= profileEscape($faculty['phone']) ?>
                            </span>
                        </div>

                    </div>

                    <!-- PROFESSIONAL INFORMATION -->
                    <div class="fp-detail-card">

                        <div class="fp-detail-head">
                            <span class="fp-detail-icon">
                                <svg viewBox="0 0 24 24">
                                    <rect x="4" y="7" width="16" height="14" rx="2"/>
                                    <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
                                    <path d="M4 13h16"/>
                                </svg>
                            </span>

                            <h3>Professional Information</h3>
                        </div>

                        <div class="fp-info-row">
                            <span class="fp-info-label">Faculty Code</span>
                            <span class="fp-info-value">
                                <?= profileEscape($faculty['faculty_code']) ?>
                            </span>
                        </div>

                        <div class="fp-info-row">
                            <span class="fp-info-label">Designation</span>
                            <span class="fp-info-value">
                                <?= profileEscape($faculty['designation']) ?>
                            </span>
                        </div>

                    </div>

                </div>
            </section>

            <footer class="fp-footer">
                <span>
                    © <?= date('Y') ?> Smart Faculty Assistant Portal
                </span>
                <span>Faculty Profile</span>
            </footer>

        </main>
    </div>
</div>

<script>
(function () {
    const button = document.getElementById('fp-menu-button');
    const sidebar = document.getElementById('fp-sidebar');

    if (!button || !sidebar) return;

    button.addEventListener('click', function () {
        const open = sidebar.classList.toggle('open');

        button.setAttribute(
            'aria-expanded',
            String(open)
        );
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
