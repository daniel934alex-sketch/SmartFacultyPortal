<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Subject";

$error = "";


if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header("Location: subjects.php?error=invalid_request");
    exit;
}

$subject_id = (int)$_GET["id"];


// Get subject

$sql = "
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
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: subjects.php?error=not_found");
    exit;
}

$subject = $result->fetch_assoc();

$stmt->close();


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


// Update

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

        // Duplicate subject code

        $sql = "
            SELECT subject_id
            FROM subjects
            WHERE subject_code = ?
            AND subject_id != ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "si",
            $subject_code,
            $subject_id
        );

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows > 0) {

            $error = "Subject code already exists.";

        } else {

            if ($department_id === null) {

                $sql = "
                    UPDATE subjects
                    SET
                        subject_code = ?,
                        subject_name = ?,
                        subject_type = ?,
                        weekly_periods = ?,
                        continuous_periods = ?,
                        department_id = NULL
                    WHERE subject_id = ?
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "sssiii",
                    $subject_code,
                    $subject_name,
                    $subject_type,
                    $weekly_periods,
                    $continuous_periods,
                    $subject_id
                );

            } else {

                $sql = "
                    UPDATE subjects
                    SET
                        subject_code = ?,
                        subject_name = ?,
                        subject_type = ?,
                        weekly_periods = ?,
                        continuous_periods = ?,
                        department_id = ?
                    WHERE subject_id = ?
                ";

                $stmt = $conn->prepare($sql);

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
            }


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: subjects.php?success=updated"
                );

                exit;

            } else {

                $error = "Failed to update subject.";

                $stmt->close();
            }
        }
    }


    // Preserve entered values

    $subject["subject_code"] =
        $subject_code;

    $subject["subject_name"] =
        $subject_name;

    $subject["subject_type"] =
        $subject_type;

    $subject["weekly_periods"] =
        $weekly_periods;

    $subject["continuous_periods"] =
        $continuous_periods;

    $subject["department_id"] =
        $department_id;
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>

            <h1>Edit Subject</h1>

            <p>
                Update subject information.
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

                <label>
                    Subject Code
                </label>

                <input
                    type="text"
                    name="subject_code"
                    maxlength="30"
                    value="<?php
                        echo htmlspecialchars(
                            $subject["subject_code"]
                        );
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Subject Name
                </label>

                <input
                    type="text"
                    name="subject_name"
                    maxlength="100"
                    value="<?php
                        echo htmlspecialchars(
                            $subject["subject_name"]
                        );
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Subject Type
                </label>

                <select
                    name="subject_type"
                    required
                >

                    <option
                        value="THEORY"
                        <?php
                        echo $subject["subject_type"] === "THEORY"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Theory
                    </option>

                    <option
                        value="LAB"
                        <?php
                        echo $subject["subject_type"] === "LAB"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Lab
                    </option>

                    <option
                        value="PROJECT"
                        <?php
                        echo $subject["subject_type"] === "PROJECT"
                            ? "selected"
                            : "";
                        ?>
                    >
                        Project
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Weekly Periods
                </label>

                <input
                    type="number"
                    name="weekly_periods"
                    min="0"
                    value="<?php
                        echo (int)$subject[
                            "weekly_periods"
                        ];
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Continuous Periods
                </label>

                <input
                    type="number"
                    name="continuous_periods"
                    min="1"
                    value="<?php
                        echo (int)$subject[
                            "continuous_periods"
                        ];
                    ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Department
                </label>

                <select
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
                                <?php
                                echo (
                                    (int)$subject[
                                        "department_id"
                                    ] ===
                                    (int)$department[
                                        "department_id"
                                    ]
                                )
                                ? "selected"
                                : "";
                                ?>
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
                Update Subject
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