
<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('FACULTY');

$username = htmlspecialchars(
    $_SESSION['username'] ?? 'Faculty',
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | Smart Faculty Assistant</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13">
</head>

<body class="faculty-dashboard">

<header class="fd-topbar">
    <a href="dashboard.php" class="fd-brand">
        <span class="fd-brand-name">Smart Faculty</span>
        <span class="fd-brand-subtitle">ASSISTANT PORTAL</span>
    </a>

    <div class="fd-user">
        <div class="fd-avatar">
            <?= strtoupper(substr($username, 0, 1)) ?>
        </div>

        <div class="fd-user-info">
            <strong><?= $username ?></strong>
            <span>Faculty Member</span>
        </div>

        <a class="fd-logout" href="../auth/logout.php">
            <span>Logout</span>
            <span aria-hidden="true">↗</span>
        </a>
    </div>
</header>

<main class="fd-main">

    <section class="fd-welcome">
        <div class="fd-welcome-content">
            <span class="fd-eyebrow">FACULTY WORKSPACE</span>

            <h1>Welcome back, <?= $username ?>!</h1>

            <p>
                Manage your timetable, stay updated with notifications,
                and organize your faculty activities in one place.
            </p>

            <a href="timetable.php" class="fd-primary-btn">
                View My Timetable <span>→</span>
            </a>
        </div>

        <div class="fd-welcome-art" aria-hidden="true">
            <div class="fd-art-circle">
                <span>✦</span>
                <div class="fd-art-book">▤</div>
                <span class="fd-art-star">✧</span>
            </div>
        </div>
    </section>

    <section class="fd-section">
        <div class="fd-section-heading">
            <div>
                <span class="fd-eyebrow">QUICK ACCESS</span>
                <h2>Your Faculty Workspace</h2>
                <p>Choose a module to get started.</p>
            </div>

            <span class="fd-module-count">05 MODULES</span>
        </div>

        <div class="fd-grid">

            <a href="timetable.php" class="fd-card">
                <div class="fd-card-top">
                    <span class="fd-card-icon">▦</span>
                    <span class="fd-card-arrow">↗</span>
                </div>
                <h3>My Timetable</h3>
                <p>View your weekly class schedule and periods.</p>
                <span class="fd-card-link">Open timetable →</span>
            </a>

            <a href="notifications.php" class="fd-card">
                <div class="fd-card-top">
                    <span class="fd-card-icon">♧</span>
                    <span class="fd-card-arrow">↗</span>
                </div>
                <h3>Notifications</h3>
                <p>Stay informed about reminders and important updates.</p>
                <span class="fd-card-link">View notifications →</span>
            </a>

            <a href="profile.php" class="fd-card">
                <div class="fd-card-top">
                    <span class="fd-card-icon">♙</span>
                    <span class="fd-card-arrow">↗</span>
                </div>
                <h3>My Profile</h3>
                <p>View and manage your faculty profile information.</p>
                <span class="fd-card-link">View profile →</span>
            </a>

            <a href="availability.php" class="fd-card">
                <div class="fd-card-top">
                    <span class="fd-card-icon">◷</span>
                    <span class="fd-card-arrow">↗</span>
                </div>
                <h3>Faculty Availability</h3>
                <p>Manage your availability and schedule information.</p>
                <span class="fd-card-link">Manage availability →</span>
            </a>

            <a href="requests.php" class="fd-card">
                <div class="fd-card-top">
                    <span class="fd-card-icon">⇄</span>
                    <span class="fd-card-arrow">↗</span>
                </div>
                <h3>Handover Requests</h3>
                <p>Access your faculty class handover requests.</p>
                <span class="fd-card-link">View requests →</span>
            </a>

        </div>
    </section>

    <footer class="fd-footer">
        <span>© <?= date('Y') ?> Smart Faculty Assistant Portal</span>
        <span>Designed for smarter faculty management</span>
    </footer>

</main>

</body>
</html>
