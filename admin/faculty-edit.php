
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Faculty";
$error = "";

function facultyEditEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* VALIDATE ID */

if (
    !isset($_GET["id"]) ||
    !ctype_digit((string)$_GET["id"]) ||
    (int)$_GET["id"] <= 0
) {
    header("Location: faculty.php?error=invalid_request");
    exit;
}

$faculty_id = (int)$_GET["id"];

/* LOAD FACULTY */

$stmt = $conn->prepare("
    SELECT
        f.faculty_id,
        f.user_id,
        f.faculty_code,
        f.faculty_name,
        f.email,
        f.phone,
        f.department_id,
        f.designation,
        f.is_hod,
        u.username,
        u.role
    FROM faculty f
    INNER JOIN users u ON f.user_id = u.user_id
    WHERE f.faculty_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to load faculty information.");
}

$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    header("Location: faculty.php?error=not_found");
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$user_id = (int)$row["user_id"];
$faculty_code = $row["faculty_code"];
$faculty_name = $row["faculty_name"];
$email = $row["email"] ?? "";
$phone = $row["phone"] ?? "";
$designation = $row["designation"];
$is_hod = (int)$row["is_hod"];
$username = $row["username"];

/* FORM SECURITY */

if (empty($_SESSION["faculty_edit_csrf"])) {
    $_SESSION["faculty_edit_csrf"] =
        bin2hex(random_bytes(32));
}

/* UPDATE FACULTY */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_code = trim($_POST["faculty_code"] ?? "");
    $faculty_name = trim($_POST["faculty_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $designation = trim(
        $_POST["designation"] ?? "Assistant Professor"
    );
    $new_password = (string)($_POST["password"] ?? "");
    $is_hod = isset($_POST["is_hod"]) ? 1 : 0;

    $token = (string)($_POST["csrf_token"] ?? "");

    if (!hash_equals(
        $_SESSION["faculty_edit_csrf"],
        $token
    )) {

        $error = "Session validation failed. Refresh and try again.";

    } elseif ($faculty_code === "") {

        $error = "Please enter the faculty code.";

    } elseif (strlen($faculty_code) > 20) {

        $error = "Faculty code cannot exceed 20 characters.";

    } elseif ($faculty_name === "") {

        $error = "Please enter the faculty name.";

    } elseif (strlen($faculty_name) > 100) {

        $error = "Faculty name cannot exceed 100 characters.";

    } elseif (
        $email !== "" &&
        (
            strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        )
    ) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($phone) > 20) {

        $error = "Phone number cannot exceed 20 characters.";

    } elseif ($designation === "") {

        $error = "Please enter the designation.";

    } elseif (strlen($designation) > 50) {

        $error = "Designation cannot exceed 50 characters.";

    } elseif (
        $new_password !== "" &&
        strlen($new_password) < 6
    ) {

        $error = "New password must contain at least 6 characters.";

    } else {

        try {

            /* DUPLICATE FACULTY CODE */

            $stmt = $conn->prepare("
                SELECT faculty_id
                FROM faculty
                WHERE faculty_code = ?
                  AND faculty_id != ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    "Unable to check faculty code."
                );
            }

            $stmt->bind_param(
                "si",
                $faculty_code,
                $faculty_id
            );

            $stmt->execute();
            $duplicate = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($duplicate) {
                throw new RuntimeException(
                    "Faculty code already exists."
                );
            }

            /*
             * IMPORTANT:
             * The existing application uses one HOD role.
             * If a new HOD is chosen, only existing HOD
             * records are demoted, not all faculty records.
             */

            $conn->begin_transaction();

            try {

                if ($is_hod === 1) {

                    /* DEMOTE PREVIOUS HOD USER ACCOUNTS */

                    $stmt = $conn->prepare("
                        UPDATE users u
                        INNER JOIN faculty f
                            ON u.user_id = f.user_id
                        SET u.role = 'FACULTY'
                        WHERE f.is_hod = 1
                          AND f.faculty_id != ?
                          AND u.role = 'HOD'
                    ");

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to update previous HOD role."
                        );
                    }

                    $stmt->bind_param("i", $faculty_id);

                    if (!$stmt->execute()) {
                        throw new RuntimeException(
                            "Failed to update previous HOD role."
                        );
                    }

                    $stmt->close();

                    /* DEMOTE PREVIOUS HOD FACULTY RECORDS */

                    $stmt = $conn->prepare("
                        UPDATE faculty
                        SET is_hod = 0
                        WHERE is_hod = 1
                          AND faculty_id != ?
                    ");

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to update previous HOD."
                        );
                    }

                    $stmt->bind_param("i", $faculty_id);

                    if (!$stmt->execute()) {
                        throw new RuntimeException(
                            "Failed to update previous HOD."
                        );
                    }

                    $stmt->close();
                }

                /* UPDATE FACULTY INFORMATION */

                $stmt = $conn->prepare("
                    UPDATE faculty
                    SET
                        faculty_code = ?,
                        faculty_name = ?,
                        email = ?,
                        phone = ?,
                        designation = ?,
                        is_hod = ?
                    WHERE faculty_id = ?
                ");

                if (!$stmt) {
                    throw new RuntimeException(
                        "Unable to prepare faculty update."
                    );
                }

                $stmt->bind_param(
                    "sssssii",
                    $faculty_code,
                    $faculty_name,
                    $email,
                    $phone,
                    $designation,
                    $is_hod,
                    $faculty_id
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        "Failed to update faculty."
                    );
                }

                $stmt->close();

                /* UPDATE LOGIN ROLE */

                $role = $is_hod === 1 ? "HOD" : "FACULTY";

                $stmt = $conn->prepare("
                    UPDATE users
                    SET role = ?
                    WHERE user_id = ?
                ");

                if (!$stmt) {
                    throw new RuntimeException(
                        "Unable to prepare user role update."
                    );
                }

                $stmt->bind_param(
                    "si",
                    $role,
                    $user_id
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        "Failed to update user role."
                    );
                }

                $stmt->close();

                /* OPTIONAL PASSWORD UPDATE */

                if ($new_password !== "") {

                    $hashedPassword = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE user_id = ?
                    ");

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to prepare password update."
                        );
                    }

                    $stmt->bind_param(
                        "si",
                        $hashedPassword,
                        $user_id
                    );

                    if (!$stmt->execute()) {
                        throw new RuntimeException(
                            "Failed to update password."
                        );
                    }

                    $stmt->close();
                }

                $conn->commit();

                $_SESSION["faculty_edit_csrf"] =
                    bin2hex(random_bytes(32));

                header(
                    "Location: faculty.php?success=updated"
                );
                exit;

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* HOD EDIT FACULTY - PAGE SPECIFIC */

main.fe-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.fe-page *,
.fe-page *::before,
.fe-page *::after {
    box-sizing: border-box;
}

.fe-page .fe-wrap {
    width: 100%;
    max-width: 1050px;
    margin: 0 auto;
}

.fe-page .fe-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    padding: 28px 32px;
    margin-bottom: 25px;
    border: 1px solid #51478c;
    border-radius: 18px;
    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 60%,
        #574898
    );
}

.fe-page .fe-header h1 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 30px;
    font-weight: 800;
}

.fe-page .fe-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

.fe-page .fe-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 11px 20px;
    border: 1px solid #9183bd;
    border-radius: 10px;
    background: rgba(255,255,255,.09);
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.fe-page .fe-back:hover {
    background: rgba(255,255,255,.18);
}

.fe-page .fe-card {
    padding: 34px;
    border: 1px solid #2d3a50;
    border-radius: 18px;
    background: #172235;
    box-shadow: 0 15px 35px rgba(0,0,0,.16);
}

.fe-page .fe-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 23px 24px;
}

.fe-page .fe-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 10px;
}

.fe-page .fe-field label {
    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

.fe-page .fe-field input:not([type="checkbox"]) {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 50px;
    padding: 11px 15px;
    border: 1px solid #3a4962;
    border-radius: 10px;
    outline: none;
    background: #10192a;
    color: #f4f6ff;
    font-family: inherit;
    font-size: 14px;
}

.fe-page .fe-field input:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,.14);
}

.fe-page .fe-field input:disabled {
    background: #253149;
    color: #afbdd1;
    cursor: not-allowed;
}

.fe-page .fe-field small {
    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.6;
}

.fe-page .fe-hod {
    grid-column: 1 / -1;
    padding: 20px;
    border: 1px solid #4d4478;
    border-radius: 12px;
    background: #211f3a;
}

.fe-page .fe-check-label {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #fff;
    font-size: 14px;
    font-weight: 750;
    cursor: pointer;
}

.fe-page .fe-check-label input {
    width: 19px;
    height: 19px;
    accent-color: #aa94ff;
}

.fe-page .fe-hod small {
    display: block;
    margin-top: 10px;
}

.fe-page .fe-actions {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 8px;
}

.fe-page .fe-submit,
.fe-page .fe-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 12px 23px;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.fe-page .fe-submit {
    border: none;
    background: linear-gradient(115deg,#b8a6ff,#947cfa);
    color: #191330;
}

.fe-page .fe-submit:hover {
    filter: brightness(1.1);
}

.fe-page .fe-cancel {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.fe-page .fe-cancel:hover {
    background: #34445f;
}

.fe-page .fe-error {
    margin-bottom: 20px;
    padding: 15px 18px;
    border: 1px solid #915067;
    border-radius: 11px;
    background: #482a38;
    color: #ffd1da;
    font-size: 13px;
}

@media (max-width: 760px) {
    .fe-page .fe-header {
        padding: 24px 20px;
    }

    .fe-page .fe-header h1 {
        font-size: 25px;
    }

    .fe-page .fe-card {
        padding: 23px 19px;
    }

    .fe-page .fe-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .fe-page .fe-hod,
    .fe-page .fe-actions {
        grid-column: 1;
    }
}
</style>

<main class="fe-page">

    <div class="fe-wrap">

        <div class="fe-header">

            <div>
                <h1>Edit Faculty</h1>
                <p>
                    Update faculty details and account settings.
                </p>
            </div>

            <a href="faculty.php" class="fe-back">
                ← Back to Faculty
            </a>

        </div>

        <?php if ($error !== ""): ?>
            <div class="fe-error" role="alert">
                <?= facultyEditEscape($error) ?>
            </div>
        <?php endif; ?>

        <div class="fe-card">

            <form method="POST" class="fe-grid">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= facultyEditEscape(
                        $_SESSION["faculty_edit_csrf"]
                    ) ?>"
                >

                <div class="fe-field">
                    <label for="faculty_code">
                        Faculty Code
                    </label>
                    <input
                        type="text"
                        id="faculty_code"
                        name="faculty_code"
                        maxlength="20"
                        value="<?= facultyEditEscape($faculty_code) ?>"
                        required
                    >
                </div>

                <div class="fe-field">
                    <label for="faculty_name">
                        Faculty Name
                    </label>
                    <input
                        type="text"
                        id="faculty_name"
                        name="faculty_name"
                        maxlength="100"
                        value="<?= facultyEditEscape($faculty_name) ?>"
                        required
                    >
                </div>

                <div class="fe-field">
                    <label for="username">
                        Username
                    </label>
                    <input
                        type="text"
                        id="username"
                        value="<?= facultyEditEscape($username) ?>"
                        disabled
                    >
                    <small>
                        Username cannot be changed here.
                    </small>
                </div>

                <div class="fe-field">
                    <label for="email">
                        Email Address
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="100"
                        value="<?= facultyEditEscape($email) ?>"
                    >
                </div>

                <div class="fe-field">
                    <label for="phone">
                        Phone Number
                    </label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        maxlength="20"
                        value="<?= facultyEditEscape($phone) ?>"
                    >
                </div>

                <div class="fe-field">
                    <label for="designation">
                        Designation
                    </label>
                    <input
                        type="text"
                        id="designation"
                        name="designation"
                        maxlength="50"
                        value="<?= facultyEditEscape($designation) ?>"
                        required
                    >
                </div>

                <div class="fe-field">
                    <label for="password">
                        New Password
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Leave blank to keep password"
                    >
                    <small>
                        Enter a password only if you want to change it.
                    </small>
                </div>

                <div class="fe-hod">
                    <label class="fe-check-label">
                        <input
                            type="checkbox"
                            name="is_hod"
                            value="1"
                            <?= $is_hod === 1 ? "checked" : "" ?>
                        >
                        Assign HOD Role
                    </label>
                    <small>
                        Enabling this makes the selected faculty
                        member the HOD and demotes the previous HOD.
                        Use carefully.
                    </small>
                </div>

                <div class="fe-actions">
                    <button
                        type="submit"
                        class="fe-submit"
                    >
                        Update Faculty
                    </button>

                    <a
                        href="faculty.php"
                        class="fe-cancel"
                    >
                        Cancel
                    </a>
                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
