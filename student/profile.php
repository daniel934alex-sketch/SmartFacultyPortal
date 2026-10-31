<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole("STUDENT");

$pageTitle = "Student Profile";

function profileEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function profileDisplay($value)
{
    $value = trim((string)($value ?? ""));

    return $value !== "" ? $value : "Not provided";
}

/* ==========================================
   LOGGED-IN STUDENT
   ========================================== */

$username = $_SESSION["username"] ?? "";

if ($username === "") {
    die("Student session not found.");
}

$sql = "
    SELECT
        u.user_id,
        u.username,
        s.student_id,
        s.register_number,
        s.student_name,
        s.email,
        s.phone,
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

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Student profile query preparation failed.");
}

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("Student record not found.");
}

$student = $result->fetch_assoc();
$stmt->close();

$studentName = $student["student_name"];
$registerNumber = $student["register_number"];
$sectionName = $student["section_name"];
$email = profileDisplay($student["email"]);
$phone = profileDisplay($student["phone"]);

$firstLetter = strtoupper(
    substr(trim($studentName), 0, 1)
);

if ($firstLetter === "") {
    $firstLetter = "S";
}

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
        Student Profile | SmartFacultyPortal
    </title>

    <style>
        /* ======================================
           STUDENT PROFILE PREMIUM DARK THEME
           ALL STYLES LOCAL TO THIS FILE
           ====================================== */

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

        /* ======================================
           SIDEBAR
           ====================================== */

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
            flex-shrink: 0;
            width: 44px;
            height: 44px;
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
            transition: background .2s, color .2s;
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

        /* ======================================
           MAIN AND TOPBAR
           ====================================== */

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
            flex-shrink: 0;
            width: 43px;
            height: 43px;
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

        /* ======================================
           PAGE BANNER
           ====================================== */

        .profile-banner {
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

        .profile-banner::after {
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

        .profile-eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            color: #d0c3ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .profile-banner h1 {
            margin: 0 0 10px;
            color: #ffffff;
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 800;
        }

        .profile-banner p {
            margin: 0;
            color: #e2dbf9;
            font-size: 13px;
            line-height: 1.75;
        }

        .profile-view-badge {
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

        /* ======================================
           PROFILE CARD
           ====================================== */

        .profile-card {
            min-width: 0;
            padding: 28px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: var(--surface);
            box-shadow: 0 10px 28px rgba(0,0,0,.1);
        }

        .profile-identity {
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 5px 0 28px;
            margin-bottom: 27px;
            border-bottom: 1px solid var(--border);
        }

        .profile-large-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 88px;
            height: 88px;
            border: 2px solid #8d7bcf;
            border-radius: 24px;
            background: linear-gradient(
                135deg,
                #3b3065,
                #6650a6
            );
            color: #ffffff;
            font-size: 35px;
            font-weight: 800;
        }

        .profile-identity h2 {
            margin: 0 0 7px;
            color: #ffffff;
            font-size: 23px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .profile-identity p {
            margin: 0;
            color: #aebbd2;
            font-size: 13px;
            line-height: 1.7;
        }

        .profile-role-tag {
            display: inline-block;
            padding: 5px 11px;
            margin-top: 11px;
            border: 1px solid #685b9d;
            border-radius: 100px;
            background: #312c58;
            color: #dfd5ff;
            font-size: 11px;
            font-weight: 800;
        }

        /* ======================================
           DETAILS
           ====================================== */

        .profile-section-heading {
            margin-bottom: 21px;
        }

        .profile-section-heading h3 {
            margin: 0 0 7px;
            color: #ffffff;
            font-size: 19px;
            font-weight: 800;
        }

        .profile-section-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(
                2,
                minmax(0,1fr)
            );
            gap: 16px;
        }

        .profile-detail {
            min-width: 0;
            padding: 19px;
            border: 1px solid #34435e;
            border-radius: 12px;
            background: #111d30;
            transition: border-color .2s;
        }

        .profile-detail:hover {
            border-color: #645891;
        }

        .profile-detail-label {
            display: block;
            margin-bottom: 10px;
            color: #9facC6;
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .3px;
        }

        .profile-detail-value {
            display: block;
            color: #ffffff;
            font-size: 15px;
            font-weight: 750;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        /* ======================================
           PRIVACY NOTICE
           ====================================== */

        .profile-privacy {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-top: 25px;
            padding: 20px;
            border: 1px solid #4a4579;
            border-radius: 12px;
            background: #22213f;
        }

        .profile-privacy-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: #39315f;
            color: #ded1ff;
            font-size: 18px;
        }

        .profile-privacy strong {
            display: block;
            margin-bottom: 6px;
            color: #ffffff;
            font-size: 13px;
        }

        .profile-privacy p {
            margin: 0;
            color: #cec7e8;
            font-size: 12px;
            line-height: 1.8;
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 23px;
        }

        .profile-back {
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

        .profile-back:hover {
            background: #423765;
        }

        /* ======================================
           RESPONSIVE
           ====================================== */

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

        @media (max-width: 700px) {
            .profile-details {
                grid-template-columns: 1fr;
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
                grid-template-columns: repeat(
                    2,
                    minmax(0,1fr)
                );
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

            .profile-banner {
                padding: 24px 20px;
            }

            .profile-card {
                padding: 19px;
            }

            .profile-identity {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-identity h2 {
                font-size: 20px;
            }

            .profile-privacy {
                padding: 15px;
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

            <a href="attendance.php">
                <span class="student-menu-icon">◫</span>
                Attendance
            </a>

            <a href="announcements.php">
                <span class="student-menu-icon">◉</span>
                Announcements
            </a>

            <a
                href="profile.php"
                class="active"
                aria-current="page"
            >
                <span class="student-menu-icon">♙</span>
                My Profile
            </a>

        </nav>

        <div class="student-sidebar-footer">

            <div class="student-sidebar-profile">

                <strong>
                    <?= profileEscape($studentName) ?>
                </strong>

                <span>
                    <?= profileEscape($registerNumber) ?>
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
                    Student Profile
                </h2>

                <span class="student-topbar-subtitle">
                    Your personal academic information
                </span>
            </div>

            <div class="student-account">

                <div>
                    <div class="student-account-name">
                        <?= profileEscape($username) ?>
                    </div>

                    <div class="student-account-role">
                        Student Account
                    </div>
                </div>

                <div class="student-avatar">
                    <?= profileEscape($firstLetter) ?>
                </div>

            </div>

        </header>


        <div class="student-content">

            <!-- PROFILE BANNER -->

            <section class="profile-banner">

                <div>
                    <span class="profile-eyebrow">
                        Student Account
                    </span>

                    <h1>
                        My Profile
                    </h1>

                    <p>
                        View your personal details,
                        account information and academic section.
                    </p>
                </div>

                <span class="profile-view-badge">
                    View-Only Profile
                </span>

            </section>


            <!-- PROFILE CONTENT -->

            <section class="profile-card">

                <!-- IDENTITY -->

                <div class="profile-identity">

                    <div class="profile-large-avatar">
                        <?= profileEscape($firstLetter) ?>
                    </div>

                    <div>

                        <h2>
                            <?= profileEscape($studentName) ?>
                        </h2>

                        <p>
                            Register No:
                            <?= profileEscape($registerNumber) ?>

                            &nbsp; • &nbsp;

                            Section
                            <?= profileEscape($sectionName) ?>
                        </p>

                        <span class="profile-role-tag">
                            Student
                        </span>

                    </div>

                </div>


                <!-- INFORMATION -->

                <div class="profile-section-heading">

                    <h3>
                        Student Information
                    </h3>

                    <p>
                        Details associated with your
                        registered student account.
                    </p>

                </div>


                <div class="profile-details">

                    <!-- USERNAME -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Username
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape(
                                $student["username"]
                            ) ?>
                        </span>

                    </div>


                    <!-- REGISTER NUMBER -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Register Number
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape($registerNumber) ?>
                        </span>

                    </div>


                    <!-- STUDENT NAME -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Full Name
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape($studentName) ?>
                        </span>

                    </div>


                    <!-- SECTION -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Academic Section
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape($sectionName) ?>
                        </span>

                    </div>


                    <!-- EMAIL -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Email Address
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape($email) ?>
                        </span>

                    </div>


                    <!-- PHONE -->

                    <div class="profile-detail">

                        <span class="profile-detail-label">
                            Phone Number
                        </span>

                        <span class="profile-detail-value">
                            <?= profileEscape($phone) ?>
                        </span>

                    </div>

                </div>


                <!-- PRIVACY NOTICE -->

                <div class="profile-privacy">

                    <div class="profile-privacy-icon">
                        ◈
                    </div>

                    <div>

                        <strong>
                            Privacy & Account Information
                        </strong>

                        <p>
                            This page displays only the
                            academic and contact details
                            associated with your own
                            student account.

                            Internal faculty information,
                            faculty absence details,
                            handover requests and alternate
                            faculty information are not
                            displayed here.

                            Contact your administrator if
                            your registered details need
                            correction.
                        </p>

                    </div>

                </div>


                <!-- BACK -->

                <div class="profile-actions">

                    <a
                        href="dashboard.php"
                        class="profile-back"
                    >
                        ← Back to Dashboard
                    </a>

                </div>

            </section>

        </div>

    </main>

</body>
</html>
