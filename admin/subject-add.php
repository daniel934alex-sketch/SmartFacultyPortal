
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Subject";

$error = "";

/* =========================================
   GET DEPARTMENTS
   ========================================= */

$department_sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    ORDER BY department_name ASC
";

$department_result = $conn->query($department_sql);


/* =========================================
   FORM SUBMISSION
   ========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_code =
        strtoupper(trim($_POST["subject_code"] ?? ""));

    $subject_name =
        trim($_POST["subject_name"] ?? "");

    $subject_type =
        strtoupper(trim($_POST["subject_type"] ?? ""));

    $weekly_periods =
        isset($_POST["weekly_periods"])
        ? (int)$_POST["weekly_periods"]
        : 0;

    $continuous_periods =
        isset($_POST["continuous_periods"])
        ? (int)$_POST["continuous_periods"]
        : 1;

    $department_id =
        isset($_POST["department_id"]) &&
        $_POST["department_id"] !== ""
        ? (int)$_POST["department_id"]
        : null;


    /* VALIDATION */

    if (
        $subject_code === "" ||
        $subject_name === "" ||
        $subject_type === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (
        !in_array(
            $subject_type,
            ["THEORY", "LAB", "PROJECT"],
            true
        )
    ) {

        $error = "Invalid subject type.";

    } elseif ($weekly_periods < 0) {

        $error = "Weekly periods cannot be negative.";

    } elseif ($continuous_periods < 1) {

        $error = "Continuous periods must be at least 1.";

    } elseif (
        $weekly_periods > 0 &&
        $continuous_periods > $weekly_periods
    ) {

        $error =
            "Continuous periods cannot be greater than weekly periods.";

    } else {

        /* CHECK DUPLICATE SUBJECT CODE */

        $sql = "
            SELECT subject_id
            FROM subjects
            WHERE subject_code = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Subject code already exists.";

        } else {

            /* INSERT SUBJECT */

            if ($department_id === null) {

                $sql = "
                    INSERT INTO subjects
                    (
                        subject_code,
                        subject_name,
                        subject_type,
                        weekly_periods,
                        continuous_periods,
                        department_id
                    )
                    VALUES (?, ?, ?, ?, ?, NULL)
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssii",
                    $subject_code,
                    $subject_name,
                    $subject_type,
                    $weekly_periods,
                    $continuous_periods
                );

            } else {

                $sql = "
                    INSERT INTO subjects
                    (
                        subject_code,
                        subject_name,
                        subject_type,
                        weekly_periods,
                        continuous_periods,
                        department_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssiii",
                    $subject_code,
                    $subject_name,
                    $subject_type,
                    $weekly_periods,
                    $continuous_periods,
                    $department_id
                );
            }

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: subjects.php?success=added"
                );

                exit;

            } else {

                $error = "Failed to add subject.";

                $stmt->close();
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ==========================================
   ADD SUBJECT - PREMIUM DARK HOD THEME
   ONLY APPLIES TO THIS PAGE
   ========================================== */

main.subject-add-page {
    color: #edf1fb;
    min-height: 100vh;
    box-sizing: border-box;
}

.subject-add-page *,
.subject-add-page *::before,
.subject-add-page *::after {
    box-sizing: border-box;
}

.subject-add-page .subject-wrap {
    max-width: 1000px;
    margin: 0 auto;
}

/* PAGE HEADER */

.subject-add-page .subject-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.subject-add-page .subject-header h1 {
    margin: 0 0 9px;
    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.25;
}

.subject-add-page .subject-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
}

.subject-add-page .subject-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 20px;
    min-height: 44px;
    border: 1px solid #9183bd;
    border-radius: 10px;
    background: rgba(255,255,255,0.09);
    color: #ffffff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 750;
}

.subject-add-page .subject-back:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM CARD */

.subject-add-page .subject-card {
    width: 100%;
    padding: 34px;
    border: 1px solid #2d3a50;
    border-radius: 18px;
    background: #172235;
    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

/* TWO-COLUMN GRID */

.subject-add-page .subject-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 23px 24px;
}

.subject-add-page .subject-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.subject-add-page .subject-field label {
    display: block;
    margin-bottom: 10px;
    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUTS AND DROPDOWNS */

.subject-add-page .subject-field input,
.subject-add-page .subject-field select {
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
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.subject-add-page .subject-field input::placeholder {
    color: #91a0b9;
}

.subject-add-page .subject-field input:focus,
.subject-add-page .subject-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

.subject-add-page .subject-field select option {
    background: #10192a;
    color: #ffffff;
}

/* HELPER TEXT */

.subject-add-page .subject-field small {
    display: block;
    margin-top: 9px;
    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.5;
}

/* BUTTONS */

.subject-add-page .subject-actions {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 6px;
}

.subject-add-page .subject-submit,
.subject-add-page .subject-cancel {
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

.subject-add-page .subject-submit {
    border: none;
    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );
    color: #191330;
}

.subject-add-page .subject-submit:hover {
    filter: brightness(1.1);
}

.subject-add-page .subject-cancel {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.subject-add-page .subject-cancel:hover {
    background: #34445f;
}

/* ERROR ALERT */

.subject-add-page .subject-error {
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
    .subject-add-page .subject-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .subject-add-page .subject-header h1 {
        font-size: 25px;
    }

    .subject-add-page .subject-card {
        padding: 23px 19px;
    }

    .subject-add-page .subject-form {
        grid-template-columns: 1fr;
        gap: 19px;
    }

    .subject-add-page .subject-actions {
        grid-column: 1;
    }
}
</style>

<main class="subject-add-page">

    <div class="subject-wrap">

        <!-- PAGE HEADER -->

        <div class="subject-header">

            <div>
                <h1>Add Subject</h1>

                <p>
                    Create a new subject.
                </p>
            </div>

            <a
                href="subjects.php"
                class="subject-back"
            >
                ← Back to Subjects
            </a>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($error !== ""): ?>

            <div
                class="subject-error"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <!-- ADD SUBJECT FORM -->

        <div class="subject-card">

            <form
                method="POST"
                action=""
                class="subject-form"
            >

                <!-- SUBJECT CODE -->

                <div class="subject-field">

                    <label for="subject_code">
                        Subject Code
                    </label>

                    <input
                        type="text"
                        id="subject_code"
                        name="subject_code"
                        maxlength="30"
                        placeholder="Example: DBMS"
                        required
                    >

                </div>


                <!-- SUBJECT NAME -->

                <div class="subject-field">

                    <label for="subject_name">
                        Subject Name
                    </label>

                    <input
                        type="text"
                        id="subject_name"
                        name="subject_name"
                        maxlength="100"
                        placeholder="Example: Database Management Systems"
                        required
                    >

                </div>


                <!-- SUBJECT TYPE -->

                <div class="subject-field">

                    <label for="subject_type">
                        Subject Type
                    </label>

                    <select
                        id="subject_type"
                        name="subject_type"
                        required
                    >

                        <option value="">
                            Select Type
                        </option>

                        <option value="THEORY">
                            Theory
                        </option>

                        <option value="LAB">
                            Lab
                        </option>

                        <option value="PROJECT">
                            Project
                        </option>

                    </select>

                </div>


                <!-- WEEKLY PERIODS -->

                <div class="subject-field">

                    <label for="weekly_periods">
                        Weekly Periods
                    </label>

                    <input
                        type="number"
                        id="weekly_periods"
                        name="weekly_periods"
                        min="0"
                        value="0"
                        required
                    >

                </div>


                <!-- CONTINUOUS PERIODS -->

                <div class="subject-field">

                    <label for="continuous_periods">
                        Continuous Periods
                    </label>

                    <input
                        type="number"
                        id="continuous_periods"
                        name="continuous_periods"
                        min="1"
                        value="1"
                        required
                    >

                    <small>
                        Example: Lab = 3 consecutive periods.
                    </small>

                </div>


                <!-- DEPARTMENT -->

                <div class="subject-field">

                    <label for="department_id">
                        Department
                    </label>

                    <select
                        id="department_id"
                        name="department_id"
                    >

                        <option value="">
                            Select Department
                        </option>

                        <?php if ($department_result): ?>

                            <?php while (
                                $department =
                                    $department_result->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= (int)$department['department_id'] ?>"
                                >
                                    <?= htmlspecialchars(
                                        $department['department_code'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                    -
                                    <?= htmlspecialchars(
                                        $department['department_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="subject-actions">

                    <button
                        type="submit"
                        class="subject-submit"
                    >
                        Add Subject
                    </button>

                    <a
                        href="subjects.php"
                        class="subject-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
