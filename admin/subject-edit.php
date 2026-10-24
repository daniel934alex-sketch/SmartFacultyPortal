
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Subject";
$error = "";

function subjectEditEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ==========================================
   VALIDATE SUBJECT ID
   ========================================== */

if (
    !isset($_GET["id"]) ||
    !ctype_digit((string)$_GET["id"]) ||
    (int)$_GET["id"] <= 0
) {
    header("Location: subjects.php?error=invalid_request");
    exit;
}

$subject_id = (int)$_GET["id"];

/* ==========================================
   LOAD SUBJECT
   ========================================== */

$stmt = $conn->prepare("
    SELECT
        subject_id,
        subject_code,
        subject_name,
        subject_type,
        weekly_periods,
        continuous_periods,
        department_id
    FROM subjects
    WHERE subject_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to load subject.");
}

$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    header("Location: subjects.php?error=not_found");
    exit;
}

$subject = $result->fetch_assoc();
$stmt->close();

/* ==========================================
   LOAD DEPARTMENTS
   ========================================== */

$departments = [];

$departmentResult = $conn->query("
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    ORDER BY department_name ASC
");

if ($departmentResult) {
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = $row;
    }
    $departmentResult->free();
}

/* ==========================================
   CSRF PROTECTION
   ========================================== */

if (empty($_SESSION["subject_edit_csrf"])) {
    $_SESSION["subject_edit_csrf"] =
        bin2hex(random_bytes(32));
}

/* ==========================================
   HANDLE UPDATE
   ========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_code = strtoupper(
        trim($_POST["subject_code"] ?? "")
    );

    $subject_name = trim(
        $_POST["subject_name"] ?? ""
    );

    $subject_type = strtoupper(
        trim($_POST["subject_type"] ?? "")
    );

    $weeklyInput = (string)(
        $_POST["weekly_periods"] ?? ""
    );

    $continuousInput = (string)(
        $_POST["continuous_periods"] ?? ""
    );

    $departmentInput = (string)(
        $_POST["department_id"] ?? ""
    );

    $weekly_periods = ctype_digit($weeklyInput)
        ? (int)$weeklyInput
        : -1;

    $continuous_periods = ctype_digit($continuousInput)
        ? (int)$continuousInput
        : 0;

    $department_id = (
        $departmentInput !== "" &&
        ctype_digit($departmentInput)
    )
        ? (int)$departmentInput
        : null;

    /* KEEP ENTERED VALUES AFTER AN ERROR */

    $subject["subject_code"] = $subject_code;
    $subject["subject_name"] = $subject_name;
    $subject["subject_type"] = $subject_type;
    $subject["weekly_periods"] = $weeklyInput;
    $subject["continuous_periods"] = $continuousInput;
    $subject["department_id"] = $departmentInput;

    /* VALIDATION */

    if (!hash_equals(
        $_SESSION["subject_edit_csrf"],
        (string)($_POST["csrf_token"] ?? "")
    )) {

        $error = "Invalid request. Refresh the page and try again.";

    } elseif (
        $subject_code === "" ||
        $subject_name === "" ||
        $subject_type === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (strlen($subject_code) > 30) {

        $error = "Subject code must not exceed 30 characters.";

    } elseif (strlen($subject_name) > 100) {

        $error = "Subject name must not exceed 100 characters.";

    } elseif (!in_array(
        $subject_type,
        ["THEORY", "LAB", "PROJECT"],
        true
    )) {

        $error = "Invalid subject type.";

    } elseif (
        !ctype_digit($weeklyInput) ||
        $weekly_periods < 0
    ) {

        $error = "Weekly periods must be zero or greater.";

    } elseif (
        !ctype_digit($continuousInput) ||
        $continuous_periods < 1
    ) {

        $error = "Continuous periods must be at least 1.";

    } elseif (
        $weekly_periods > 0 &&
        $continuous_periods > $weekly_periods
    ) {

        $error =
            "Continuous periods cannot be greater than weekly periods.";

    } elseif (
        $departmentInput !== "" &&
        (
            !ctype_digit($departmentInput) ||
            $department_id <= 0
        )
    ) {

        $error = "Please select a valid department.";
    }

    /* VALIDATE DEPARTMENT EXISTENCE */

    if ($error === "" && $department_id !== null) {

        $departmentExists = false;

        foreach ($departments as $department) {
            if (
                (int)$department["department_id"] === $department_id
            ) {
                $departmentExists = true;
                break;
            }
        }

        if (!$departmentExists) {
            $error = "Selected department does not exist.";
        }
    }

    /* DUPLICATE CODE + DATABASE UPDATE */

    if ($error === "") {

        try {

            $stmt = $conn->prepare("
                SELECT subject_id
                FROM subjects
                WHERE subject_code = ?
                  AND subject_id != ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    "Unable to check subject code."
                );
            }

            $stmt->bind_param(
                "si",
                $subject_code,
                $subject_id
            );

            $stmt->execute();

            $duplicateFound =
                $stmt->get_result()->num_rows > 0;

            $stmt->close();

            if ($duplicateFound) {
                throw new RuntimeException(
                    "Subject code already exists."
                );
            }

            /*
             * department_id may be NULL.
             * mysqli supports binding a null variable.
             */

            $stmt = $conn->prepare("
                UPDATE subjects
                SET
                    subject_code = ?,
                    subject_name = ?,
                    subject_type = ?,
                    weekly_periods = ?,
                    continuous_periods = ?,
                    department_id = ?
                WHERE subject_id = ?
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    "Unable to prepare subject update."
                );
            }

            $stmt->bind_param(
                "sssiiii",
                $subject_code,
                $subject_name,
                $subject_type,
                $weekly_periods,
                $continuous_periods,
                $department_id,
                $subject_id
            );

            if (!$stmt->execute()) {
                throw new RuntimeException(
                    "Failed to update subject."
                );
            }

            $stmt->close();

            $_SESSION["subject_edit_csrf"] =
                bin2hex(random_bytes(32));

            header(
                "Location: subjects.php?success=updated"
            );
            exit;

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
/* ==========================================
   SUBJECT EDIT — PREMIUM HOD DARK THEME
   Only applies to this page.
   ========================================== */

main.subject-edit-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.subject-edit-page *,
.subject-edit-page *::before,
.subject-edit-page *::after {
    box-sizing: border-box;
}

.subject-edit-page .se-wrap {
    width: 100%;
    max-width: 1050px;
    margin: 0 auto;
}

/* HEADER */

.subject-edit-page .se-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;

    margin-bottom: 25px;
    padding: 28px 32px;

    border: 1px solid #51478c;
    border-radius: 18px;

    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 60%,
        #574898
    );
}

.subject-edit-page .se-header h1 {
    margin: 0 0 9px;
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
}

.subject-edit-page .se-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

.subject-edit-page .se-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 44px;
    padding: 11px 20px;

    border: 1px solid #9183bd;
    border-radius: 10px;

    background: rgba(255,255,255,0.09);
    color: #ffffff;

    text-decoration: none;
    font-size: 13px;
    font-weight: 750;
}

.subject-edit-page .se-back:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM CARD */

.subject-edit-page .se-card {
    width: 100%;
    padding: 35px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;
    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

.subject-edit-page .se-form {
    display: block;
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;

    background: transparent;
    box-shadow: none;
    border: none;
}

.subject-edit-page .se-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

.subject-edit-page .se-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.subject-edit-page .se-field label {
    display: block;
    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

.subject-edit-page .se-field input,
.subject-edit-page .se-field select {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 51px;

    margin: 0;
    padding: 12px 15px;

    border: 1px solid #3a4962;
    border-radius: 10px;
    outline: none;

    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;
}

.subject-edit-page .se-field select option {
    background: #10192a;
    color: #ffffff;
}

.subject-edit-page .se-field input:focus,
.subject-edit-page .se-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

.subject-edit-page .se-field small {
    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.6;
}

/* ACTIONS */

.subject-edit-page .se-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;

    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #344158;
}

.subject-edit-page .se-submit,
.subject-edit-page .se-cancel {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    min-height: 46px;
    padding: 12px 24px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;

    cursor: pointer;
}

.subject-edit-page .se-submit {
    border: none;
    background: linear-gradient(115deg, #b8a6ff, #947cfa);
    color: #191330;
}

.subject-edit-page .se-submit:hover {
    filter: brightness(1.1);
}

.subject-edit-page .se-cancel {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.subject-edit-page .se-cancel:hover {
    background: #34445f;
}

/* ERROR */

.subject-edit-page .se-error {
    margin-bottom: 22px;
    padding: 15px 18px;

    border: 1px solid #915067;
    border-radius: 11px;

    background: #482a38;
    color: #ffd1da;

    font-size: 13px;
    line-height: 1.7;
}

/* RESPONSIVE */

@media (max-width: 760px) {

    .subject-edit-page .se-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .subject-edit-page .se-header h1 {
        font-size: 25px;
    }

    .subject-edit-page .se-card {
        padding: 23px 19px;
    }

    .subject-edit-page .se-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .subject-edit-page .se-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .subject-edit-page .se-submit,
    .subject-edit-page .se-cancel {
        width: 100%;
    }
}
</style>

<main class="subject-edit-page">

    <div class="se-wrap">

        <!-- PAGE HEADER -->

        <div class="se-header">

            <div>
                <h1>Edit Subject</h1>

                <p>
                    Update subject information and scheduling details.
                </p>
            </div>

            <a
                href="subjects.php"
                class="se-back"
            >
                ← Back to Subjects
            </a>

        </div>

        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="se-error" role="alert">
                <?= subjectEditEscape($error) ?>
            </div>

        <?php endif; ?>

        <!-- EDIT FORM -->

        <div class="se-card">

            <form
                method="POST"
                action=""
                class="se-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= subjectEditEscape(
                        $_SESSION["subject_edit_csrf"]
                    ) ?>"
                >

                <div class="se-grid">

                    <!-- SUBJECT CODE -->

                    <div class="se-field">

                        <label for="subject_code">
                            Subject Code
                        </label>

                        <input
                            type="text"
                            id="subject_code"
                            name="subject_code"
                            maxlength="30"
                            value="<?= subjectEditEscape(
                                $subject["subject_code"]
                            ) ?>"
                            placeholder="Example: CS301"
                            required
                        >

                    </div>

                    <!-- SUBJECT NAME -->

                    <div class="se-field">

                        <label for="subject_name">
                            Subject Name
                        </label>

                        <input
                            type="text"
                            id="subject_name"
                            name="subject_name"
                            maxlength="100"
                            value="<?= subjectEditEscape(
                                $subject["subject_name"]
                            ) ?>"
                            placeholder="Enter subject name"
                            required
                        >

                    </div>

                    <!-- SUBJECT TYPE -->

                    <div class="se-field">

                        <label for="subject_type">
                            Subject Type
                        </label>

                        <select
                            id="subject_type"
                            name="subject_type"
                            required
                        >

                            <option
                                value="THEORY"
                                <?= $subject["subject_type"] === "THEORY"
                                    ? "selected"
                                    : "" ?>
                            >
                                Theory
                            </option>

                            <option
                                value="LAB"
                                <?= $subject["subject_type"] === "LAB"
                                    ? "selected"
                                    : "" ?>
                            >
                                Lab
                            </option>

                            <option
                                value="PROJECT"
                                <?= $subject["subject_type"] === "PROJECT"
                                    ? "selected"
                                    : "" ?>
                            >
                                Project
                            </option>

                        </select>

                    </div>

                    <!-- DEPARTMENT -->

                    <div class="se-field">

                        <label for="department_id">
                            Department
                        </label>

                        <select
                            id="department_id"
                            name="department_id"
                        >

                            <option value="">
                                No Department
                            </option>

                            <?php foreach (
                                $departments as $department
                            ): ?>

                                <option
                                    value="<?= (int)$department["department_id"] ?>"
                                    <?= (int)($subject["department_id"] ?? 0) ===
                                        (int)$department["department_id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= subjectEditEscape(
                                        $department["department_code"] .
                                        " - " .
                                        $department["department_name"]
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- WEEKLY PERIODS -->

                    <div class="se-field">

                        <label for="weekly_periods">
                            Weekly Periods
                        </label>

                        <input
                            type="number"
                            id="weekly_periods"
                            name="weekly_periods"
                            min="0"
                            step="1"
                            value="<?= subjectEditEscape(
                                $subject["weekly_periods"]
                            ) ?>"
                            required
                        >

                        <small>
                            Total periods allocated to this
                            subject each week.
                        </small>

                    </div>

                    <!-- CONTINUOUS PERIODS -->

                    <div class="se-field">

                        <label for="continuous_periods">
                            Continuous Periods
                        </label>

                        <input
                            type="number"
                            id="continuous_periods"
                            name="continuous_periods"
                            min="1"
                            step="1"
                            value="<?= subjectEditEscape(
                                $subject["continuous_periods"]
                            ) ?>"
                            required
                        >

                        <small>
                            Number of consecutive periods
                            required for a single session.
                        </small>

                    </div>

                </div>

                <!-- BUTTONS -->

                <div class="se-actions">

                    <button
                        type="submit"
                        class="se-submit"
                    >
                        Update Subject
                    </button>

                    <a
                        href="subjects.php"
                        class="se-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
