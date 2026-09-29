<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Department";

$error = "";

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: departments.php?error=invalid_request");
    exit;
}

$department_id = (int)$_GET["id"];

// Get department
$sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: departments.php?error=not_found");
    exit;
}

$department = $result->fetch_assoc();

$stmt->close();


// Update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $department_code =
        strtoupper(trim($_POST["department_code"] ?? ""));

    $department_name =
        trim($_POST["department_name"] ?? "");

    if (
        $department_code === "" ||
        $department_name === ""
    ) {

        $error = "All fields are required.";

    } else {

        // Check duplicate code
        $sql = "
            SELECT department_id
            FROM departments
            WHERE department_code = ?
            AND department_id != ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "si",
            $department_code,
            $department_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Department code already exists.";

        } else {

            // Check duplicate name
            $sql = "
                SELECT department_id
                FROM departments
                WHERE department_name = ?
                AND department_id != ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "si",
                $department_name,
                $department_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "Department name already exists.";

            } else {

                $sql = "
                    UPDATE departments
                    SET
                        department_code = ?,
                        department_name = ?
                    WHERE department_id = ?
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "ssi",
                    $department_code,
                    $department_name,
                    $department_id
                );

                if ($stmt->execute()) {

                    header(
                        "Location: departments.php?success=updated"
                    );

                    exit;

                } else {

                    $error = "Failed to update department.";
                }
            }
        }

        $stmt->close();
    }

    // Keep entered values if validation fails
    $department["department_code"] = $department_code;
    $department["department_name"] = $department_name;
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div class="page-header">

        <div>
            <h1>Edit Department</h1>
            <p>Update department information.</p>
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
                    value="<?php
                        echo htmlspecialchars(
                            $department["department_code"]
                        );
                    ?>"
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
                    value="<?php
                        echo htmlspecialchars(
                            $department["department_name"]
                        );
                    ?>"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Department
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