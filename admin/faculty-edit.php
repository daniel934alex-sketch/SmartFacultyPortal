<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "Edit Faculty";

$error = "";
$success = "";


// ---------------------------------------------------------
// Get Faculty ID
// ---------------------------------------------------------

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: faculty.php");
    exit;
}

$faculty_id = (int) $_GET['id'];


// ---------------------------------------------------------
// Fetch Faculty
// ---------------------------------------------------------

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
        u.status
    FROM faculty f
    INNER JOIN users u
        ON f.user_id = u.user_id
    WHERE f.faculty_id = ?
");

$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    header("Location: faculty.php");
    exit;
}

$faculty = $result->fetch_assoc();


// ---------------------------------------------------------
// Update Faculty
// ---------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_code = trim($_POST['faculty_code']);
    $faculty_name = trim($_POST['faculty_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $designation = trim($_POST['designation']);

    $is_hod = isset($_POST['is_hod']) ? 1 : 0;

    $new_password = $_POST['password'] ?? "";


    if ($faculty_code === "" || $faculty_name === "") {

        $error = "Faculty code and faculty name are required.";

    } else {

        // Check duplicate faculty code
        $stmt = $conn->prepare("
            SELECT faculty_id
            FROM faculty
            WHERE faculty_code = ?
            AND faculty_id != ?
        ");

        $stmt->bind_param(
            "si",
            $faculty_code,
            $faculty_id
        );

        $stmt->execute();

        $duplicate = $stmt->get_result();


        if ($duplicate->num_rows > 0) {

            $error = "Faculty code already exists.";

        } else {

            $conn->begin_transaction();

            try {

                // -------------------------------------------------
                // Update Faculty
                // -------------------------------------------------

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

                $stmt->execute();


                // -------------------------------------------------
                // Update User Role
                // -------------------------------------------------

                $role = $is_hod ? "HOD" : "FACULTY";

                $stmt = $conn->prepare("
                    UPDATE users
                    SET role = ?
                    WHERE user_id = ?
                ");

                $stmt->bind_param(
                    "si",
                    $role,
                    $faculty['user_id']
                );

                $stmt->execute();


                // -------------------------------------------------
                // Update Password Only If Entered
                // -------------------------------------------------

                if ($new_password !== "") {

                    $passwordHash = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE user_id = ?
                    ");

                    $stmt->bind_param(
                        "si",
                        $passwordHash,
                        $faculty['user_id']
                    );

                    $stmt->execute();
                }


                $conn->commit();

                $success = "Faculty details updated successfully.";


                // Refresh displayed values
                $faculty['faculty_code'] = $faculty_code;
                $faculty['faculty_name'] = $faculty_name;
                $faculty['email'] = $email;
                $faculty['phone'] = $phone;
                $faculty['designation'] = $designation;
                $faculty['is_hod'] = $is_hod;

            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    "Failed to update faculty: "
                    . $e->getMessage();
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>

<main>

    <h1>Edit Faculty</h1>

    <p>
        Update faculty information and login settings.
    </p>

    <br>


    <?php if ($error): ?>

        <div class="form-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="form-success">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>


    <div class="table-container">

        <form method="POST">


            <!-- Faculty Code -->

            <label>
                Faculty Code *
            </label>

            <input
                type="text"
                name="faculty_code"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['faculty_code']
                    );
                ?>"
                required
            >

            <br><br>


            <!-- Faculty Name -->

            <label>
                Faculty Name *
            </label>

            <input
                type="text"
                name="faculty_name"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['faculty_name']
                    );
                ?>"
                required
            >

            <br><br>


            <!-- Username -->

            <label>
                Username
            </label>

            <input
                type="text"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['username']
                    );
                ?>"
                disabled
            >

            <br><br>


            <!-- New Password -->

            <label>
                New Password
            </label>

            <input
                type="password"
                name="password"
                placeholder="Leave blank to keep current password"
            >

            <br><br>


            <!-- Email -->

            <label>
                Email
            </label>

            <input
                type="email"
                name="email"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['email'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- Phone -->

            <label>
                Phone
            </label>

            <input
                type="text"
                name="phone"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['phone'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- Designation -->

            <label>
                Designation
            </label>

            <input
                type="text"
                name="designation"
                value="<?php
                    echo htmlspecialchars(
                        $faculty['designation'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- HOD -->

            <label>

                <input
                    type="checkbox"
                    name="is_hod"
                    value="1"
                    <?php
                    echo $faculty['is_hod']
                        ? 'checked'
                        : '';
                    ?>
                >

                HOD

            </label>

            <br><br>


            <button
                type="submit"
                class="btn btn-success"
            >
                Save Changes
            </button>


            <a
                href="faculty.php"
                class="btn"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>