
<?php
session_start();

require_once "../config/database.php";
require_once "../config/config.php";

/* ==========================================
   REDIRECT ALREADY LOGGED-IN USERS
   ========================================== */

if (isset($_SESSION['user_id'])) {

    if (($_SESSION['role'] ?? '') === 'HOD') {
        header("Location: ../admin/dashboard.php");
        exit;
    }

    if (($_SESSION['role'] ?? '') === 'FACULTY') {
        header("Location: ../faculty/dashboard.php");
        exit;
    }

    if (($_SESSION['role'] ?? '') === 'STUDENT') {
        header("Location: ../student/dashboard.php");
        exit;
    }
}

/* ==========================================
   LOGIN VARIABLES
   ========================================== */

$error = "";
$username = "";

/* ==========================================
   PROCESS LOGIN
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                user_id,
                username,
                password,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database query error.";

        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();

                $user_status = strtoupper(
                    trim($user['status'] ?? '')
                );

                if (
                    $user_status !== 'ACTIVE' &&
                    $user_status !== '1'
                ) {

                    $error = "Your account is inactive.";

                } elseif (
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    /* SUCCESSFUL PASSWORD VERIFICATION */

                    session_regenerate_id(true);

                    $_SESSION['user_id'] =
                        (int)$user['user_id'];

                    $_SESSION['username'] =
                        $user['username'];

                    $_SESSION['role'] =
                        strtoupper($user['role']);

                    /* FACULTY SESSION */

                    if ($_SESSION['role'] === 'FACULTY') {

                        $faculty_stmt = $conn->prepare("
                            SELECT faculty_id
                            FROM faculty
                            WHERE user_id = ?
                            LIMIT 1
                        ");

                        if ($faculty_stmt) {

                            $faculty_stmt->bind_param(
                                "i",
                                $user['user_id']
                            );

                            $faculty_stmt->execute();

                            $faculty_result =
                                $faculty_stmt->get_result();

                            if ($faculty_result->num_rows === 1) {

                                $faculty_row =
                                    $faculty_result->fetch_assoc();

                                $_SESSION['faculty_id'] =
                                    (int)$faculty_row['faculty_id'];

                            } else {

                                unset($_SESSION['faculty_id']);
                                $error = "Faculty record not found.";
                            }

                            $faculty_stmt->close();

                        } else {

                            $error =
                                "Unable to load faculty information.";
                        }
                    }

                    /* STUDENT SESSION */

                    if ($_SESSION['role'] === 'STUDENT') {

                        $student_stmt = $conn->prepare("
                            SELECT student_id
                            FROM students
                            WHERE user_id = ?
                            LIMIT 1
                        ");

                        if ($student_stmt) {

                            $student_stmt->bind_param(
                                "i",
                                $user['user_id']
                            );

                            $student_stmt->execute();

                            $student_result =
                                $student_stmt->get_result();

                            if ($student_result->num_rows === 1) {

                                $student_row =
                                    $student_result->fetch_assoc();

                                $_SESSION['student_id'] =
                                    (int)$student_row['student_id'];

                            } else {

                                unset($_SESSION['student_id']);
                                $error = "Student record not found.";
                            }

                            $student_stmt->close();

                        } else {

                            $error =
                                "Unable to load student information.";
                        }
                    }

                    /* ROLE-BASED REDIRECT */

                    if ($error === "") {

                        if ($_SESSION['role'] === 'HOD') {

                            header(
                                "Location: ../admin/dashboard.php"
                            );
                            exit;

                        } elseif ($_SESSION['role'] === 'FACULTY') {

                            header(
                                "Location: ../faculty/dashboard.php"
                            );
                            exit;

                        } elseif ($_SESSION['role'] === 'STUDENT') {

                            header(
                                "Location: ../student/dashboard.php"
                            );
                            exit;

                        } else {

                            $error = "Invalid user role.";
                        }
                    }

                    /* CLEAR SESSION IF LOGIN CANNOT COMPLETE */

                    if ($error !== "") {
                        $_SESSION = [];
                        session_regenerate_id(true);
                    }

                } else {

                    $error = "Invalid username or password.";
                }

            } else {

                $error = "Invalid username or password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Login | Smart Faculty Portal</title>

<style>

/* =====================================
   GLOBAL
   ===================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    background: #0c1220;
    color: #f1f5ff;
    font-family: "Segoe UI", Arial, sans-serif;
}

a {
    text-decoration: none;
}

.login-page {
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 100vh;
}

/* =====================================
   LEFT BRANDING PANEL
   ===================================== */

.brand-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;

    min-height: 100vh;
    padding: 48px 65px;

    overflow: hidden;

    background: linear-gradient(
        145deg,
        #181934 0%,
        #292451 50%,
        #483782 100%
    );

    border-right: 1px solid #49406c;
}

.brand-panel::before {
    content: "";
    position: absolute;

    width: 460px;
    height: 460px;

    top: 12%;
    right: -230px;

    border-radius: 50%;

    background: #9278ff;
    opacity: 0.12;
    filter: blur(35px);
}

.brand-panel::after {
    content: "";
    position: absolute;

    width: 400px;
    height: 400px;

    bottom: -240px;
    left: -100px;

    border-radius: 50%;

    background: #ac9aff;
    opacity: 0.12;
    filter: blur(45px);
}

.brand-logo,
.brand-content,
.brand-footer {
    position: relative;
    z-index: 1;
}

/* BRAND LOGO */

.brand-logo {
    display: flex;
    align-items: center;
    gap: 14px;
}

.logo-icon {
    display: grid;
    place-items: center;

    width: 52px;
    height: 52px;

    border-radius: 15px;

    background: linear-gradient(
        135deg,
        #b6a4ff,
        #7355d9
    );

    box-shadow: 0 10px 30px #1510264d;
}

.logo-icon svg {
    width: 30px;
    height: 30px;
    color: white;
}

.brand-logo strong {
    display: block;
    font-size: 18px;
    color: white;
}

.brand-logo small {
    display: block;
    margin-top: 5px;

    color: #aaa5d5;
    font-size: 10px;
    letter-spacing: 2px;
}

/* BRAND CONTENT */

.brand-content {
    max-width: 560px;
    padding: 45px 0;
}

.brand-tag {
    display: inline-flex;
    align-items: center;

    padding: 10px 15px;
    margin-bottom: 25px;

    border: 1px solid #71659c;
    border-radius: 999px;

    color: #cbbdff;
    background: #ffffff0a;

    font-size: 11px;
    font-weight: 750;
    letter-spacing: 1.3px;
}

.brand-content h1 {
    margin: 0 0 22px;

    color: #fff;

    font-size: clamp(36px, 4.4vw, 65px);
    line-height: 1.12;
    letter-spacing: -1.5px;
}

.brand-content h1 span {
    display: block;
    color: #beaaff;
}

.brand-content p {
    max-width: 470px;

    color: #c5c2db;

    font-size: 15px;
    line-height: 1.9;
}

/* FEATURES */

.feature-list {
    display: grid;
    gap: 18px;

    margin-top: 42px;
}

.feature {
    display: flex;
    align-items: center;
    gap: 14px;

    color: #e2def2;
    font-size: 13px;
}

.feature-icon {
    display: grid;
    place-items: center;

    width: 38px;
    height: 38px;
    flex: 0 0 38px;

    border: 1px solid #70658c;
    border-radius: 10px;

    background: #ffffff12;
    color: #d3c5ff;

    font-size: 18px;
}

.brand-footer {
    color: #aba5ce;

    font-size: 11px;
    line-height: 1.6;
}

/* =====================================
   RIGHT LOGIN PANEL
   ===================================== */

.login-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;

    min-width: 0;

    padding: 55px 30px;

    background:
        radial-gradient(
            circle at 85% 15%,
            #30264b70,
            transparent 38%
        ),
        #0c1220;
}

.login-box {
    width: min(100%, 430px);
}

.login-heading {
    margin-bottom: 32px;
}

.login-tag {
    display: inline-block;
    margin-bottom: 14px;

    color: #aa94ff;

    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.8px;
}

.login-heading h2 {
    margin: 0 0 12px;

    color: white;

    font-size: clamp(29px, 3vw, 39px);
    letter-spacing: -0.8px;
}

.login-heading p {
    margin: 0;

    color: #96a4bc;

    font-size: 13px;
    line-height: 1.8;
}

/* =====================================
   LOGIN CARD
   ===================================== */

.login-card {
    padding: 34px;

    border: 1px solid #303d52;
    border-radius: 20px;

    background: #172235;

    box-shadow: 0 22px 70px #0508154d;
}

/* FORM */

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;

    margin-bottom: 10px;

    color: #d7deeb;

    font-size: 12px;
    font-weight: 700;
}

.input-wrap {
    position: relative;
}

.input-wrap input {
    display: block;

    width: 100%;
    height: 52px;

    padding: 0 16px;

    outline: none;

    border: 1px solid #39465c;
    border-radius: 10px;

    background: #10192a;
    color: #f7f8ff;

    font-family: inherit;
    font-size: 14px;

    transition: 0.2s ease;
}

.input-wrap input::placeholder {
    color: #6f7f97;
}

.input-wrap input:focus {
    border-color: #a58aff;
    box-shadow: 0 0 0 3px #9b83ff22;
}

.password-wrap input {
    padding-right: 80px;
}

/* PASSWORD TOGGLE */

.toggle-password {
    position: absolute;
    top: 50%;
    right: 11px;

    transform: translateY(-50%);

    padding: 8px;

    border: 0;
    border-radius: 6px;

    background: transparent;
    color: #b8a7ff;

    font-size: 11px;
    font-weight: 750;

    cursor: pointer;
}

.toggle-password:hover {
    background: #2b3450;
}

/* LOGIN BUTTON */

.login-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;

    width: 100%;
    min-height: 52px;

    margin-top: 8px;
    padding: 12px 18px;

    border: none;
    border-radius: 10px;

    background: linear-gradient(
        100deg,
        #b7a5ff,
        #947bfa
    );

    color: #1a1530;

    font-family: inherit;
    font-size: 14px;
    font-weight: 800;

    cursor: pointer;
    transition: 0.2s ease;
}

.login-btn:hover {
    filter: brightness(1.1);
    transform: translateY(-2px);
}

.login-btn svg {
    width: 19px;
    height: 19px;
}

/* ERROR */

.login-error {
    display: flex;
    align-items: flex-start;
    gap: 10px;

    padding: 14px 16px;
    margin-bottom: 23px;

    border: 1px solid #8a4559;
    border-radius: 10px;

    background: #462a3a;
    color: #ffbdc9;

    font-size: 12px;
    line-height: 1.7;
}

.login-note {
    margin: 22px 0 0;

    text-align: center;

    color: #91a0b7;

    font-size: 11px;
    line-height: 1.8;
}

.login-note strong {
    color: #c7baff;
}

.login-footer {
    margin-top: 33px;

    text-align: center;

    color: #64758e;
    font-size: 11px;
}

/* SVG DEFAULT */

svg {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* FOCUS */

button:focus-visible,
input:focus-visible {
    outline: 3px solid #b6a5ff;
    outline-offset: 3px;
}

/* =====================================
   RESPONSIVE
   ===================================== */

@media (max-width: 1000px) {

    .brand-panel {
        padding: 40px 32px;
    }

    .brand-content h1 {
        font-size: 42px;
    }
}

@media (max-width: 760px) {

    .login-page {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        min-height: auto;
        padding: 25px 24px 30px;
    }

    .brand-content {
        padding: 35px 0 0;
    }

    .brand-content h1 {
        font-size: 32px;
        letter-spacing: -0.5px;
    }

    .brand-content p {
        font-size: 13px;
    }

    .feature-list,
    .brand-footer {
        display: none;
    }

    .login-panel {
        padding: 35px 20px 55px;
    }

    .login-heading {
        margin-bottom: 25px;
    }

    .login-heading h2 {
        font-size: 29px;
    }

    .login-card {
        padding: 25px 20px;
    }
}

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {
        transition-duration: 0.01ms !important;
    }
}

</style>
</head>

<body>

<div class="login-page">

    <!-- LEFT PANEL -->

    <section class="brand-panel">

        <div class="brand-logo">

            <span class="logo-icon">
                <svg viewBox="0 0 24 24">
                    <path d="m2 10 10-5 10 5-10 5-10-5Z"/>
                    <path d="M6 12v5c4 3 8 3 12 0v-5"/>
                    <path d="M22 10v7"/>
                </svg>
            </span>

            <div>
                <strong>Smart Faculty</strong>
                <small>ASSISTANT PORTAL</small>
            </div>

        </div>

        <div class="brand-content">

            <span class="brand-tag">
                ✦ YOUR DIGITAL CAMPUS
            </span>

            <h1>
                A smarter way
                <span>to manage</span>
                your campus.
            </h1>

            <p>
                Your unified workspace for academic
                schedules, faculty coordination,
                and period-wise attendance management.
            </p>

            <div class="feature-list">

                <div class="feature">
                    <span class="feature-icon">✓</span>
                    Faculty and academic management
                </div>

                <div class="feature">
                    <span class="feature-icon">✓</span>
                    Timetable and class scheduling
                </div>

                <div class="feature">
                    <span class="feature-icon">✓</span>
                    Attendance and campus coordination
                </div>

            </div>

        </div>

        <div class="brand-footer">
            Smart Faculty Assistant &amp; Period-Wise
            Attendance Management Portal
        </div>

    </section>

    <!-- RIGHT PANEL -->

    <section class="login-panel">

        <div class="login-box">

            <div class="login-heading">

                <span class="login-tag">
                    SECURE PORTAL ACCESS
                </span>

                <h2>Welcome back</h2>

                <p>
                    Enter your credentials to access
                    your Smart Faculty workspace.
                </p>

            </div>

            <div class="login-card">

                <?php if ($error !== ""): ?>

                    <div class="login-error" role="alert">
                        <strong>!</strong>

                        <span>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    </div>

                <?php endif; ?>

                <form method="POST" action="">

                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <div class="input-wrap">

                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Enter your username"
                                value="<?= htmlspecialchars(
                                    $username,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                autocomplete="username"
                                required
                            >

                        </div>
                    </div>

                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrap password-wrap">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                id="togglePassword"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>
                    </div>

                    <!-- LOGIN BUTTON -->

                    <button
                        type="submit"
                        class="login-btn"
                    >
                        Sign In

                        <svg viewBox="0 0 24 24">
                            <path d="M5 12h14"/>
                            <path d="m13 6 6 6-6 6"/>
                        </svg>
                    </button>

                </form>

                <p class="login-note">
                    <strong>Authorized access only.</strong>
                    Use your assigned institutional account.
                </p>

            </div>

            <div class="login-footer">
                © <?= date('Y') ?>
                Smart Faculty Assistant Portal
            </div>

        </div>

    </section>

</div>

<script>
/* SHOW / HIDE PASSWORD */

(function () {

    const passwordInput =
        document.getElementById("password");

    const toggleButton =
        document.getElementById("togglePassword");

    if (!passwordInput || !toggleButton) {
        return;
    }

    toggleButton.addEventListener("click", function () {

        const passwordIsHidden =
            passwordInput.type === "password";

        if (passwordIsHidden) {

            passwordInput.type = "text";
            toggleButton.textContent = "Hide";

        } else {

            passwordInput.type = "password";
            toggleButton.textContent = "Show";
        }

        toggleButton.setAttribute(
            "aria-label",
            passwordIsHidden ? "Hide password" : "Show password"
        );

        toggleButton.setAttribute(
            "aria-pressed",
            String(passwordIsHidden)
        );

    });

})();
</script>

</body>
</html>
