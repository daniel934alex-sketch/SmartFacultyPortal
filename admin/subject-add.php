<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Subject";

$error = "";


// Get departments
$department_sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    ORDER BY department_name ASC
";

$department_result = $conn->query($department_sql);


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


    // Validation

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

        // Check duplicate subject code

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

            // Insert subject

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

<main>

    <div class="page-header">

        <div>

            <h1>Add Subject</h1>

            <p>
                Create a new subject.
            </p>

        </div>

        <a
            href="subjects.php"
            class="btn btn-secondary"
        >
            Back
        </a>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="form-card">

        <form method="POST">

            <div class="form-group">

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


            <div class="form-group">

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


            <div class="form-group">

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


            <div class="form-group">

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


            <div class="form-group">

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


            <div class="form-group">

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
                                value="<?php
                                    echo (int)$department[
                                        "department_id"
                                    ];
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $department["department_code"]
                                );

                                echo " - ";

                                echo htmlspecialchars(
                                    $department["department_name"]
                                );
                                ?>

                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Subject
            </button>


            <a
                href="subjects.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>