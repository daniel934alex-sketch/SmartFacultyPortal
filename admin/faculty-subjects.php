<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "Faculty Subject Assignment";

$error = "";
$success = "";

function assignmentEscape($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* ==========================================
   CSRF TOKEN
   ========================================== */

if (empty($_SESSION["faculty_subjects_csrf"])) {
    $_SESSION["faculty_subjects_csrf"] =
        bin2hex(random_bytes(32));
}

/* ==========================================
   GET CURRENT SELECTION
   ========================================== */

$faculty_id = isset($_GET["faculty_id"])
    ? (int)$_GET["faculty_id"]
    : 0;

$section_id = isset($_GET["section_id"])
    ? (int)$_GET["section_id"]
    : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $faculty_id = (int)($_POST["faculty_id"] ?? 0);
    $section_id = (int)($_POST["section_id"] ?? 0);
}

/* ==========================================
   HANDLE SAVE
   ========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedSubjects = $_POST["subjects"] ?? [];

    if (!hash_equals(
        $_SESSION["faculty_subjects_csrf"],
        (string)($_POST["csrf_token"] ?? "")
    )) {

        $error = "Invalid form request. Please refresh.";

    } elseif ($faculty_id <= 0) {

        $error = "Please select a faculty member.";

    } elseif ($section_id <= 0) {

        $error = "Please select a section.";

    } elseif (!is_array($postedSubjects)) {

        $error = "Invalid subject selection.";

    } else {

        try {

            /* VERIFY FACULTY */

            $stmt = $conn->prepare("
                SELECT faculty_id
                FROM faculty
                WHERE faculty_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();

            $validFaculty =
                $stmt->get_result()->num_rows === 1;

            $stmt->close();

            if (!$validFaculty) {
                throw new RuntimeException(
                    "Invalid faculty member."
                );
            }

            /* VERIFY SECTION */

            $stmt = $conn->prepare("
                SELECT section_id
                FROM sections
                WHERE section_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $section_id);
            $stmt->execute();

            $validSection =
                $stmt->get_result()->num_rows === 1;

            $stmt->close();

            if (!$validSection) {
                throw new RuntimeException(
                    "Invalid section."
                );
            }

            /*
             * VALIDATE SELECTED SUBJECT IDS
             * Only THEORY and LAB are assignable.
             */

            $selectedSubjects = [];

            foreach ($postedSubjects as $subjectId) {

                if (
                    !is_scalar($subjectId) ||
                    !ctype_digit((string)$subjectId) ||
                    (int)$subjectId <= 0
                ) {
                    throw new RuntimeException(
                        "Invalid subject ID submitted."
                    );
                }

                $selectedSubjects[] = (int)$subjectId;
            }

            $selectedSubjects = array_values(
                array_unique($selectedSubjects)
            );

            if ($selectedSubjects) {

                $checkStmt = $conn->prepare("
                    SELECT subject_id
                    FROM subjects
                    WHERE subject_id = ?
                      AND subject_type IN ('THEORY', 'LAB')
                    LIMIT 1
                ");

                foreach ($selectedSubjects as $subjectId) {

                    $checkStmt->bind_param("i", $subjectId);
                    $checkStmt->execute();

                    if (
                        $checkStmt->get_result()->num_rows !== 1
                    ) {
                        $checkStmt->close();

                        throw new RuntimeException(
                            "One selected subject is not available."
                        );
                    }
                }

                $checkStmt->close();
            }

            /* SAVE ALL CHANGES AS ONE TRANSACTION */

            $conn->begin_transaction();

            try {

                /* REMOVE EXISTING ASSIGNMENTS */

                $stmt = $conn->prepare("
                    DELETE FROM faculty_subjects
                    WHERE faculty_id = ?
                      AND section_id = ?
                ");

                if (!$stmt) {
                    throw new RuntimeException(
                        "Could not prepare assignment update."
                    );
                }

                $stmt->bind_param(
                    "ii",
                    $faculty_id,
                    $section_id
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        "Failed to clear old assignments."
                    );
                }

                $stmt->close();

                /* INSERT CURRENT SELECTION */

                if ($selectedSubjects) {

                    $stmt = $conn->prepare("
                        INSERT INTO faculty_subjects
                        (
                            faculty_id,
                            subject_id,
                            section_id
                        )
                        VALUES (?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Could not prepare subject assignments."
                        );
                    }

                    foreach ($selectedSubjects as $subjectId) {

                        $stmt->bind_param(
                            "iii",
                            $faculty_id,
                            $subjectId,
                            $section_id
                        );

                        if (!$stmt->execute()) {
                            throw new RuntimeException(
                                "Failed to assign a subject."
                            );
                        }
                    }

                    $stmt->close();
                }

                $conn->commit();

                $_SESSION["faculty_subjects_csrf"] =
                    bin2hex(random_bytes(32));

                $success =
                    "Subject assignments saved successfully.";

            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

        } catch (Throwable $e) {

            $error = $e->getMessage();
        }
    }
}

/* ==========================================
   LOAD FACULTY OPTIONS
   ========================================== */

$facultyResult = $conn->query("
    SELECT
        faculty_id,
        faculty_code,
        faculty_name,
        designation
    FROM faculty
    ORDER BY faculty_name ASC
");

/* ==========================================
   LOAD SECTION OPTIONS
   ========================================== */

$sectionResult = $conn->query("
    SELECT section_id, section_name
    FROM sections
    ORDER BY section_name ASC
");

/* ==========================================
   LOAD SELECTED FACULTY
   ========================================== */

$selectedFaculty = null;

if ($faculty_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            faculty_id,
            faculty_code,
            faculty_name,
            designation
        FROM faculty
        WHERE faculty_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();

    $selectedFaculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ==========================================
   LOAD SELECTED SECTION
   ========================================== */

$selectedSection = null;

if ($section_id > 0) {

    $stmt = $conn->prepare("
        SELECT section_id, section_name
        FROM sections
        WHERE section_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $section_id);
    $stmt->execute();

    $selectedSection = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ==========================================
   LOAD EXISTING ASSIGNMENTS
   ========================================== */

$assignedSubjects = [];

if ($selectedFaculty && $selectedSection) {

    $stmt = $conn->prepare("
        SELECT subject_id
        FROM faculty_subjects
        WHERE faculty_id = ?
          AND section_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $faculty_id,
        $section_id
    );

    $stmt->execute();
    $assignedResult = $stmt->get_result();

    while ($row = $assignedResult->fetch_assoc()) {
        $assignedSubjects[] = (int)$row["subject_id"];
    }

    $stmt->close();
}

/* ==========================================
   LOAD THEORY AND LAB SUBJECTS
   ========================================== */

$subjectResult = $conn->query("
    SELECT
        subject_id,
        subject_code,
        subject_name,
        subject_type,
        weekly_periods,
        continuous_periods
    FROM subjects
    WHERE subject_type IN ('THEORY', 'LAB')
    ORDER BY subject_type, subject_name
");

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ==========================================
   HOD FACULTY SUBJECT ASSIGNMENTS
   PREMIUM DARK UI - PAGE SPECIFIC
   ========================================== */

main.fs-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.fs-page *,
.fs-page *::before,
.fs-page *::after {
    box-sizing: border-box;
}

.fs-page .fs-wrap {
    width: 100%;
    max-width: 1250px;
    margin: 0 auto;
}

.fs-page .fs-header {
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

.fs-page .fs-header h1 {
    margin: 0 0 8px;
    color: #fff;
    font-size: 30px;
    font-weight: 800;
}

.fs-page .fs-header p {
    margin: 0;
    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

.fs-page .fs-selection {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.fs-page .fs-card {
    min-width: 0;
    padding: 27px;
    margin-bottom: 24px;
    border: 1px solid #2d3a50;
    border-radius: 16px;
    background: #172235;
    box-shadow: 0 12px 28px rgba(0,0,0,.12);
}

.fs-page .fs-selection .fs-card {
    margin-bottom: 0;
}

.fs-page .fs-card h2 {
    margin: 0 0 16px;
    color: #fff;
    font-size: 19px;
    font-weight: 800;
}

.fs-page .fs-card p {
    color: #a8b6ce;
    font-size: 13px;
    line-height: 1.7;
}

.fs-page .fs-label {
    display: block;
    margin-bottom: 11px;
    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

.fs-page .fs-select {
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
    cursor: pointer;
}

.fs-page .fs-select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,.14);
}

.fs-page .fs-select option {
    background: #10192a;
    color: #fff;
}

.fs-page .fs-detail {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 16px;
}

.fs-page .fs-detail-item {
    padding: 18px;
    border: 1px solid #3a4962;
    border-radius: 11px;
    background: #111b2d;
}

.fs-page .fs-detail-item small {
    display: block;
    margin-bottom: 8px;
    color: #b3a4eb;
    font-size: 12px;
}

.fs-page .fs-detail-item strong {
    color: #fff;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.fs-page .fs-table-scroll {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #2d3a50;
    border-radius: 12px;
}

.fs-page .fs-table {
    width: 100%;
    min-width: 750px;
    margin: 0;
    border-collapse: collapse;
    color: #edf1fb;
    font-size: 13px;
}

.fs-page .fs-table th,
.fs-page .fs-table td {
    padding: 15px 17px;
    text-align: left;
    vertical-align: middle;
    border: none;
    border-bottom: 1px solid #29374d;
}

.fs-page .fs-table thead th {
    background: #23334c;
    color: #e2d9ff;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.fs-page .fs-table tbody tr {
    background: #172235;
}

.fs-page .fs-table tbody tr:nth-child(even) {
    background: #1b293e;
}

.fs-page .fs-table tbody tr:hover {
    background: #24344e;
}

.fs-page .fs-table tbody tr:last-child td {
    border-bottom: none;
}

.fs-page .fs-table td {
    color: #e3ebf8;
}

.fs-page .fs-table td strong {
    color: #fff;
}

.fs-page .fs-check {
    width: 19px;
    height: 19px;
    accent-color: #aa94ff;
    cursor: pointer;
}

.fs-page .fs-type {
    display: inline-block;
    padding: 6px 10px;
    border: 1px solid #4c4a8d;
    border-radius: 20px;
    background: #2b2852;
    color: #cabbff;
    font-size: 11px;
    font-weight: 800;
}

.fs-page .fs-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.fs-page .fs-submit,
.fs-page .fs-back {
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

.fs-page .fs-submit {
    border: none;
    background: linear-gradient(115deg,#b8a6ff,#947cfa);
    color: #191330;
}

.fs-page .fs-submit:hover {
    filter: brightness(1.1);
}

.fs-page .fs-back {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.fs-page .fs-back:hover {
    background: #34445f;
}

.fs-page .fs-error,
.fs-page .fs-success {
    padding: 15px 18px;
    margin-bottom: 22px;
    border-radius: 11px;
    font-size: 13px;
    line-height: 1.6;
}

.fs-page .fs-error {
    border: 1px solid #915067;
    background: #482a38;
    color: #ffd1da;
}

.fs-page .fs-success {
    border: 1px solid #2f786a;
    background: #173e36;
    color: #9df0d4;
}

.fs-page .fs-info {
    text-align: center;
    padding: 32px 20px;
}

.fs-page .fs-info p {
    margin: 0;
}

@media (max-width: 760px) {
    .fs-page .fs-header {
        padding: 24px 20px;
    }

    .fs-page .fs-header h1 {
        font-size: 25px;
    }

    .fs-page .fs-selection,
    .fs-page .fs-detail {
        grid-template-columns: 1fr;
    }

    .fs-page .fs-card {
        padding: 20px 17px;
    }
}
</style>

<main class="fs-page">

    <div class="fs-wrap">

        <!-- PAGE HEADER -->

        <div class="fs-header">

            <h1>Faculty Subject Assignment</h1>

            <p>
                Assign Theory and Lab subjects to a
                faculty member for a specific section.
            </p>

        </div>

        <!-- MESSAGES -->

        <?php if ($error !== ""): ?>
            <div class="fs-error" role="alert">
                <?= assignmentEscape($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="fs-success" role="status">
                <?= assignmentEscape($success) ?>
            </div>
        <?php endif; ?>


        <!-- SELECT FACULTY + SECTION -->

        <div class="fs-selection">

            <section class="fs-card">

                <h2>Select Faculty</h2>

                <form method="GET" action="faculty-subjects.php">

                    <input
                        type="hidden"
                        name="section_id"
                        value="<?= (int)$section_id ?>"
                    >

                    <label
                        for="faculty_id"
                        class="fs-label"
                    >
                        Faculty Member
                    </label>

                    <select
                        id="faculty_id"
                        name="faculty_id"
                        class="fs-select"
                        onchange="this.form.submit()"
                    >

                        <option value="0">
                            -- Select Faculty --
                        </option>

                        <?php if ($facultyResult): ?>

                            <?php while (
                                $faculty = $facultyResult->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= (int)$faculty["faculty_id"] ?>"
                                    <?= $faculty_id ===
                                        (int)$faculty["faculty_id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= assignmentEscape(
                                        $faculty["faculty_code"] .
                                        " - " .
                                        $faculty["faculty_name"]
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </form>

            </section>


            <section class="fs-card">

                <h2>Select Section</h2>

                <form method="GET" action="faculty-subjects.php">

                    <input
                        type="hidden"
                        name="faculty_id"
                        value="<?= (int)$faculty_id ?>"
                    >

                    <label
                        for="section_id"
                        class="fs-label"
                    >
                        Section
                    </label>

                    <select
                        id="section_id"
                        name="section_id"
                        class="fs-select"
                        onchange="this.form.submit()"
                    >

                        <option value="0">
                            -- Select Section --
                        </option>

                        <?php if ($sectionResult): ?>

                            <?php while (
                                $section = $sectionResult->fetch_assoc()
                            ): ?>

                                <option
                                    value="<?= (int)$section["section_id"] ?>"
                                    <?= $section_id ===
                                        (int)$section["section_id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= assignmentEscape(
                                        $section["section_name"]
                                    ) ?>
                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </form>

            </section>

        </div>


        <?php if ($selectedFaculty && $selectedSection): ?>

            <!-- SELECTED DETAILS -->

            <section class="fs-card">

                <h2>Assignment Details</h2>

                <div class="fs-detail">

                    <div class="fs-detail-item">

                        <small>Faculty Member</small>

                        <strong>
                            <?= assignmentEscape(
                                $selectedFaculty["faculty_code"] .
                                " - " .
                                $selectedFaculty["faculty_name"]
                            ) ?>
                        </strong>

                    </div>

                    <div class="fs-detail-item">

                        <small>Section</small>

                        <strong>
                            <?= assignmentEscape(
                                $selectedSection["section_name"]
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- ASSIGN SUBJECTS -->

            <section class="fs-card">

                <h2>Assign Subjects</h2>

                <p>
                    Select every subject this faculty member
                    should teach for the selected section.
                    Unchecking a subject removes that assignment
                    when you save.
                </p>

                <form
                    method="POST"
                    action="faculty-subjects.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= assignmentEscape(
                            $_SESSION["faculty_subjects_csrf"]
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="faculty_id"
                        value="<?= (int)$faculty_id ?>"
                    >

                    <input
                        type="hidden"
                        name="section_id"
                        value="<?= (int)$section_id ?>"
                    >

                    <div class="fs-table-scroll">

                        <table class="fs-table">

                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Code</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Weekly Periods</th>
                                    <th>Continuous</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (
                                    $subjectResult &&
                                    $subjectResult->num_rows > 0
                                ): ?>

                                    <?php while (
                                        $subject = $subjectResult->fetch_assoc()
                                    ): ?>

                                        <tr>

                                            <td>

                                                <input
                                                    type="checkbox"
                                                    class="fs-check"
                                                    name="subjects[]"
                                                    value="<?= (int)$subject["subject_id"] ?>"
                                                    aria-label="Assign <?= assignmentEscape(
                                                        $subject["subject_code"]
                                                    ) ?>"
                                                    <?= in_array(
                                                        (int)$subject["subject_id"],
                                                        $assignedSubjects,
                                                        true
                                                    ) ? "checked" : "" ?>
                                                >

                                            </td>

                                            <td>
                                                <strong>
                                                    <?= assignmentEscape(
                                                        $subject["subject_code"]
                                                    ) ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= assignmentEscape(
                                                    $subject["subject_name"]
                                                ) ?>
                                            </td>

                                            <td>

                                                <span class="fs-type">
                                                    <?= assignmentEscape(
                                                        $subject["subject_type"]
                                                    ) ?>
                                                </span>

                                            </td>

                                            <td>
                                                <?= (int)$subject["weekly_periods"] ?>
                                            </td>

                                            <td>
                                                <?= (int)$subject["continuous_periods"] ?>
                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="6">
                                            No Theory or Lab subjects found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="fs-actions">

                        <button
                            type="submit"
                            class="fs-submit"
                        >
                            Save Assignments
                        </button>

                        <a
                            href="faculty.php"
                            class="fs-back"
                        >
                            Back to Faculty
                        </a>

                    </div>

                </form>

            </section>

        <?php else: ?>

            <section class="fs-card fs-info">

                <h2>Select Faculty and Section</h2>

                <p>
                    Choose a faculty member and a section
                    above to view and manage their
                    subject assignments.
                </p>

            </section>

        <?php endif; ?>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
