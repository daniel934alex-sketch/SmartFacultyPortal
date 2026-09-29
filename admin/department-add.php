<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Department";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $department_code = strtoupper(trim($_POST["department_code"] ?? ""));
    $department_name = trim($_POST["department_name"] ?? "");

    if ($department_code === "" || $department_name === "") {

        $error = "All fields are required.";

    } else {

        // Check duplicate department code
        $sql = "
            SELECT department_id
            FROM departments
            WHERE department_code = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $department_code);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Department code already exists.";

        } else {

            // Check duplicate department name
            $sql = "
                SELECT department_id
                FROM departments
                WHERE department_name = ?
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $department_name);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "Department name already exists.";

            } else {

                $sql = "
                    INSERT INTO departments
                    (
                        department_code,
                        department_name
                    )
                    VALUES (?, ?)
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    $error = "Database error.";

                } else {

                    $stmt->bind_param(
                        "ss",
                        $department_code,
                        $department_name
                    );

                    if ($stmt->execute()) {

                        header(
                            "Location: departments.php?success=added"
                        );

                        exit;

                    } else {

                        $error = "Failed to add department.";
                    }
                }
            }
        }

        $stmt->close();
    }
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Add Department</h1>
            <p>Create a new department.</p>
        </div>

        <a
            href="departments.php"
            class="btn btn-secondary"
        >
            Back
        </a>

    </div>

    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <div class="form-card">

        <form method="POST">

            <div class="form-group">

                <label for="department_code">
                    Department Code
                </label>

                <input
                    type="text"
                    id="department_code"
                    name="department_code"
                    maxlength="20"
                    placeholder="Example: CSE"
                    required
                >

            </div>

            <div class="form-group">

                <label for="department_name">
                    Department Name
                </label>

                <input
                    type="text"
                    id="department_name"
                    name="department_name"
                    maxlength="100"
                    placeholder="Example: Computer Science and Engineering"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Add Department
            </button>

            <a
                href="departments.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>