<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "Faculty Subject Assignment";

$error = "";
$success = "";


// ---------------------------------------------------------
// Get Faculty ID
// ---------------------------------------------------------

$faculty_id = 0;

if (isset($_GET['faculty_id']) && is_numeric($_GET['faculty_id'])) {
    $faculty_id = (int) $_GET['faculty_id'];
}

if (isset($_POST['faculty_id']) && is_numeric($_POST['faculty_id'])) {
    $faculty_id = (int) $_POST['faculty_id'];
}


// ---------------------------------------------------------
// Get Section ID
// ---------------------------------------------------------

$section_id = 0;

if (isset($_GET['section_id']) && is_numeric($_GET['section_id'])) {
    $section_id = (int) $_GET['section_id'];
}

if (isset($_POST['section_id']) && is_numeric($_POST['section_id'])) {
    $section_id = (int) $_POST['section_id'];
}


// ---------------------------------------------------------
// Save Assignments
// ---------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_subjects = $_POST['subjects'] ?? [];

    $selected_subjects = array_map(
        'intval',
        $selected_subjects
    );

    $selected_subjects = array_unique(
        $selected_subjects
    );


    if ($faculty_id <= 0) {

        $error = "Please select a faculty member.";

    } elseif ($section_id <= 0) {

        $error = "Please select a section.";

    } else {

        // Check faculty
        $stmt = $conn->prepare("
            SELECT faculty_id
            FROM faculty
            WHERE faculty_id = ?
        ");

        $stmt->bind_param(
            "i",
            $faculty_id
        );

        $stmt->execute();

        $facultyCheck = $stmt->get_result();


        // Check section
        $stmt = $conn->prepare("
            SELECT section_id
            FROM sections
            WHERE section_id = ?
        ");

        $stmt->bind_param(
            "i",
            $section_id
        );

        $stmt->execute();

        $sectionCheck = $stmt->get_result();


        if ($facultyCheck->num_rows !== 1) {

            $error = "Invalid faculty member.";

        } elseif ($sectionCheck->num_rows !== 1) {

            $error = "Invalid section.";

        } else {

            $conn->begin_transaction();

            try {

                // -------------------------------------------------
                // Delete existing assignments for this
                // faculty + section
                // -------------------------------------------------

                $stmt = $conn->prepare("
                    DELETE FROM faculty_subjects
                    WHERE faculty_id = ?
                    AND section_id = ?
                ");

                $stmt->bind_param(
                    "ii",
                    $faculty_id,
                    $section_id
                );

                $stmt->execute();


                // -------------------------------------------------
                // Insert selected subjects
                // -------------------------------------------------

                if (count($selected_subjects) > 0) {

                    $stmt = $conn->prepare("
                        INSERT INTO faculty_subjects
                        (
                            faculty_id,
                            subject_id,
                            section_id
                        )
                        VALUES (?, ?, ?)
                    ");

                    foreach ($selected_subjects as $subject_id) {

                        if ($subject_id <= 0) {
                            continue;
                        }

                        $stmt->bind_param(
                            "iii",
                            $faculty_id,
                            $subject_id,
                            $section_id
                        );

                        $stmt->execute();
                    }
                }


                $conn->commit();

                $success =
                    "Subject assignments saved successfully.";

            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    "Failed to save assignments: "
                    . $e->getMessage();
            }
        }
    }
}


// ---------------------------------------------------------
// Get all Faculty
// ---------------------------------------------------------

$facultyResult = $conn->query("
    SELECT
        faculty_id,
        faculty_code,
        faculty_name,
        designation
    FROM faculty
    ORDER BY faculty_name ASC
");


// ---------------------------------------------------------
// Get all Sections
// ---------------------------------------------------------

$sectionResult = $conn->query("
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_name ASC
");


// ---------------------------------------------------------
// Get selected Faculty
// ---------------------------------------------------------

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
    ");

    $stmt->bind_param(
        "i",
        $faculty_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $selectedFaculty =
            $result->fetch_assoc();
    }
}


// ---------------------------------------------------------
// Get selected Section
// ---------------------------------------------------------

$selectedSection = null;

if ($section_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            section_id,
            section_name
        FROM sections
        WHERE section_id = ?
    ");

    $stmt->bind_param(
        "i",
        $section_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $selectedSection =
            $result->fetch_assoc();
    }
}


// ---------------------------------------------------------
// Get Assigned Subjects
// ---------------------------------------------------------

$assigned_subjects = [];

if ($faculty_id > 0 && $section_id > 0) {

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

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $assigned_subjects[] =
            (int) $row['subject_id'];
    }
}


// ---------------------------------------------------------
// Get Subjects
// ---------------------------------------------------------

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
    ORDER BY
        subject_type,
        subject_name
");

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<main>

    <h1>Faculty Subject Assignment</h1>

    <p>
        Assign subjects to a faculty member for a specific section.
    </p>

    <br>


    <?php if ($error): ?>

        <div class="form-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="form-success">

            <?php
            echo htmlspecialchars($success);
            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FACULTY
         ===================================================== -->

    <div class="table-container">

        <h2>Select Faculty</h2>

        <br>

        <form method="GET">

            <input
                type="hidden"
                name="section_id"
                value="<?php
                    echo $section_id;
                ?>"
            >

            <select
                name="faculty_id"
                onchange="this.form.submit()"
                style="
                    width:100%;
                    padding:12px;
                    border:1px solid #cbd5e1;
                    border-radius:5px;
                    font-size:15px;
                "
            >

                <option value="">
                    -- Select Faculty --
                </option>


                <?php while ($faculty = $facultyResult->fetch_assoc()): ?>

                    <option
                        value="<?php
                            echo $faculty['faculty_id'];
                        ?>"
                        <?php
                        echo (
                            $faculty_id ==
                            $faculty['faculty_id']
                        )
                            ? 'selected'
                            : '';
                        ?>
                    >

                        <?php
                        echo htmlspecialchars(
                            $faculty['faculty_code']
                            . " - "
                            . $faculty['faculty_name']
                        );
                        ?>

                    </option>

                <?php endwhile; ?>

            </select>

        </form>

    </div>


    <!-- =====================================================
         SECTION
         ===================================================== -->

    <div class="table-container">

        <h2>Select Section</h2>

        <br>

        <form method="GET">

            <input
                type="hidden"
                name="faculty_id"
                value="<?php
                    echo $faculty_id;
                ?>"
            >

            <select
                name="section_id"
                onchange="this.form.submit()"
                style="
                    width:100%;
                    padding:12px;
                    border:1px solid #cbd5e1;
                    border-radius:5px;
                    font-size:15px;
                "
            >

                <option value="">
                    -- Select Section --
                </option>


                <?php while ($section = $sectionResult->fetch_assoc()): ?>

                    <option
                        value="<?php
                            echo $section['section_id'];
                        ?>"
                        <?php
                        echo (
                            $section_id ==
                            $section['section_id']
                        )
                            ? 'selected'
                            : '';
                        ?>
                    >

                        <?php
                        echo htmlspecialchars(
                            $section['section_name']
                        );
                        ?>

                    </option>

                <?php endwhile; ?>

            </select>

        </form>

    </div>


    <?php if ($selectedFaculty && $selectedSection): ?>


        <!-- =================================================
             CURRENT SELECTION
             ================================================= -->

        <div class="table-container">

            <h2>Assignment Details</h2>

            <br>

            <p>
                <strong>Faculty:</strong>

                <?php
                echo htmlspecialchars(
                    $selectedFaculty['faculty_code']
                );
                ?>

                -

                <?php
                echo htmlspecialchars(
                    $selectedFaculty['faculty_name']
                );
                ?>

            </p>

            <p>
                <strong>Section:</strong>

                <?php
                echo htmlspecialchars(
                    $selectedSection['section_name']
                );
                ?>

            </p>

        </div>


        <!-- =================================================
             SUBJECTS
             ================================================= -->

        <div class="table-container">

            <h2>Assign Subjects</h2>

            <p>
                Select all subjects this faculty member
                will teach for this section.
            </p>

            <br>


            <form method="POST">

                <input
                    type="hidden"
                    name="faculty_id"
                    value="<?php
                        echo $faculty_id;
                    ?>"
                >

                <input
                    type="hidden"
                    name="section_id"
                    value="<?php
                        echo $section_id;
                    ?>"
                >


                <table>

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

                    <?php while ($subject = $subjectResult->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <input
                                    type="checkbox"
                                    name="subjects[]"
                                    value="<?php
                                        echo $subject['subject_id'];
                                    ?>"
                                    <?php
                                    echo in_array(
                                        (int) $subject['subject_id'],
                                        $assigned_subjects,
                                        true
                                    )
                                        ? 'checked'
                                        : '';
                                    ?>
                                >

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $subject['subject_code']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $subject['subject_name']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $subject['subject_type']
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $subject['weekly_periods'];
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $subject['continuous_periods'];
                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>


                <br>


                <button
                    type="submit"
                    class="btn btn-success"
                >
                    Save Assignments
                </button>


                <a
                    href="faculty.php"
                    class="btn"
                >
                    Back to Faculty
                </a>

            </form>

        </div>


    <?php elseif ($faculty_id > 0 || $section_id > 0): ?>


        <div class="table-container">

            <p>
                Please select both a faculty member and a section.
            </p>

        </div>


    <?php else: ?>


        <div class="table-container">

            <p>
                Select a faculty member and section to manage
                subject assignments.
            </p>

        </div>


    <?php endif; ?>

</main>


<?php require_once "../includes/footer.php"; ?>