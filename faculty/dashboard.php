
<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('FACULTY');

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
    <title>Faculty Dashboard | Smart Faculty</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=dark3">
</head>

<body class="fd-dark">

<div class="fd-layout">

    <!-- SIDEBAR -->
    <aside class="fd-sidebar" id="faculty-sidebar">

        <a href="dashboard.php" class="fd-brand">
            <span class="fd-brand-logo">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2">
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

            <a href="dashboard.php" class="fd-nav-link active"
               aria-current="page">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="timetable.php" class="fd-nav-link">
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
                <span class="fd-help-icon"></span>
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
                    <span class="fd-page-label">OVERVIEW</span>
                    <h2>Faculty Dashboard</h2>
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

            <!-- WELCOME -->
            <section class="fd-hero">
                <div class="fd-hero-content">
                    <span class="fd-hero-eyebrow">
                        YOUR FACULTY WORKSPACE
                    </span>

                    <h1>Welcome back, <?= $username ?>!</h1>

                    <p>
                        Stay organized, manage your classes,
                        and make every teaching day productive.
                        Everything you need is right here.
                    </p>

                    <div class="fd-hero-actions">
                        <a href="timetable.php" class="fd-btn-primary">
                            View Timetable <span>→</span>
                        </a>
                        <a href="requests.php" class="fd-btn-outline">
                            Handover Requests
                        </a>
                    </div>
                </div>

                <div class="fd-hero-art" aria-hidden="true">
                    <div class="fd-orbit fd-orbit-one"></div>
                    <div class="fd-orbit fd-orbit-two"></div>
                    <div class="fd-art-center">
                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.3">
                            <rect x="4" y="3" width="16" height="18" rx="3"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                    </div>
                </div>
            </section>

            <!-- QUICK ACCESS -->
            <section class="fd-section">
                <div class="fd-section-header">
                    <div>
                        <span class="fd-section-eyebrow">YOUR MODULES</span>
                        <h2>Quick Access</h2>
                        <p>Jump straight into your faculty tools.</p>
                    </div>
                </div>

                <div class="fd-card-grid">

                    <a href="timetable.php" class="fd-tool-card">
                        <span class="fd-tool-icon purple">
                            <svg viewBox="0 0 24 24">
                                <rect x="3" y="5" width="18" height="16" rx="2"/>
                                <path d="M7 3v4M17 3v4M3 10h18M8 14h3M14 14h3"/>
                            </svg>
                        </span>
                        <span class="fd-card-arrow">↗</span>
                        <h3>My Timetable</h3>
                        <p>View your teaching schedule and class periods.</p>
                        <span class="fd-card-action">Open Timetable →</span>
                    </a>

                    <a href="notifications.php" class="fd-tool-card">
                        <span class="fd-tool-icon blue">
                            <svg viewBox="0 0 24 24">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                                <path d="M10 21h4"/>
                            </svg>
                        </span>
                        <span class="fd-card-arrow">↗</span>
                        <h3>Notifications</h3>
                        <p>Check reminders, alerts and important updates.</p>
                        <span class="fd-card-action">View Notifications →</span>
                    </a>

                    <a href="profile.php" class="fd-tool-card">
                        <span class="fd-tool-icon cyan">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="4"/>
                                <path d="M4 21a8 8 0 0 1 16 0"/>
                            </svg>
                        </span>
                        <span class="fd-card-arrow">↗</span>
                        <h3>My Profile</h3>
                        <p>Access your personal faculty information.</p>
                        <span class="fd-card-action">View Profile →</span>
                    </a>

                    <a href="availability.php" class="fd-tool-card">
                        <span class="fd-tool-icon amber">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 7v5l3 2"/>
                            </svg>
                        </span>
                        <span class="fd-card-arrow">↗</span>
                        <h3>Faculty Availability</h3>
                        <p>Manage your schedule and availability details.</p>
                        <span class="fd-card-action">Manage Availability →</span>
                    </a>

                    <a href="requests.php" class="fd-tool-card">
                        <span class="fd-tool-icon rose">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 7h16l-4-4M20 7l-4 4"/>
                                <path d="M20 17H4l4-4M4 17l4 4"/>
                            </svg>
                        </span>
                        <span class="fd-card-arrow">↗</span>
                        <h3>Handover Requests</h3>
                        <p>Manage class handovers and related requests.</p>
                        <span class="fd-card-action">View Requests →</span>
                    </a>

                </div>
            </section>

            <!-- WORKSPACE PANELS -->
            <section class="fd-section fd-bottom-section">
                <div class="fd-section-header">
                    <div>
                        <span class="fd-section-eyebrow">STAY ORGANIZED</span>
                        <h2>Workspace Overview</h2>
                        <p>Useful shortcuts for your daily activities.</p>
                    </div>
                </div>

                <div class="fd-panel-grid">

                    <div class="fd-panel">
                        <div class="fd-panel-heading">
                            <span class="fd-panel-icon">◷</span>
                            <h3>Today's Focus</h3>
                        </div>

                        <div class="fd-date-display">
                            <?= date('l, d F Y') ?>
                        </div>

                        <p>
                            Ready for today's classes?
                            Check your timetable before you begin.
                        </p>

                        <a href="timetable.php" class="fd-panel-link">
                            Check Schedule →
                        </a>
                    </div>

                    <div class="fd-panel">
                        <div class="fd-panel-heading">
                            <span class="fd-panel-icon"></span>
                            <h3>Stay Connected</h3>
                        </div>

                        <p>
                            Keep up with academic notifications
                            and coordinate faculty handover requests.
                        </p>

                        <div class="fd-panel-actions">
                            <a href="notifications.php">Notifications ↗</a>
                            <a href="requests.php">Requests ↗</a>
                        </div>
                    </div>

                </div>
            </section>

            <footer class="fd-footer">
                <span>© <?= date('Y') ?> Smart Faculty Assistant Portal</span>
                <span>Faculty Workspace</span>
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
