<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Faculty";


/*
|--------------------------------------------------------------------------
| Validate Faculty ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header(
        "Location: faculty.php?error=invalid_request"
    );

    exit;
}


$faculty_id = (int)$_GET["id"];


/*
|--------------------------------------------------------------------------
| Load Faculty
|--------------------------------------------------------------------------
*/

$sql = "
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

    INNER JOIN users u
        ON f.user_id = u.user_id

    WHERE f.faculty_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Database error.");
}


$stmt->bind_param(
    "i",
    $faculty_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: faculty.php?error=not_found"
    );

    exit;
}


$row = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Existing Values
|--------------------------------------------------------------------------
*/

$user_id =
    (int)$row["user_id"];

$faculty_code =
    $row["faculty_code"];

$faculty_name =
    $row["faculty_name"];

$email =
    $row["email"];

$phone =
    $row["phone"];

$department_id =
    (int)$row["department_id"];

$designation =
    $row["designation"];

$is_hod =
    (int)$row["is_hod"];

$username =
    $row["username"];

$error = "";


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_code =
        trim($_POST["faculty_code"] ?? "");

    $faculty_name =
        trim($_POST["faculty_name"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $phone =
        trim($_POST["phone"] ?? "");

    $designation =
        trim(
            $_POST["designation"]
            ?? "Assistant Professor"
        );

    $new_password =
        $_POST["password"] ?? "";

    $is_hod =
        isset($_POST["is_hod"])
        ? 1
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($faculty_code === "") {

        $error =
            "Please enter the faculty code.";

    } elseif ($faculty_name === "") {

        $error =
            "Please enter the faculty name.";

    } elseif ($designation === "") {

        $error =
            "Please enter the designation.";

    } elseif (
        $new_password !== "" &&
        strlen($new_password) < 6
    ) {

        $error =
            "New password must contain at least 6 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Faculty Code
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $sql = "
            SELECT faculty_id
            FROM faculty
            WHERE faculty_code = ?
            AND faculty_id != ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param(
                "si",
                $faculty_code,
                $faculty_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    "Faculty code already exists.";
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update Faculty
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | If selected as HOD
            |--------------------------------------------------------------------------
            |
            | First demote every other faculty member.
            |
            */

            if ($is_hod === 1) {

                /*
                | Demote previous HOD in faculty table
                */

                $sql = "
                    UPDATE faculty
                    SET is_hod = 0
                    WHERE faculty_id != ?
                ";

                $stmt =
                    $conn->prepare($sql);

                if (!$stmt) {

                    throw new Exception(
                        "Failed to update previous HOD."
                    );
                }


                $stmt->bind_param(
                    "i",
                    $faculty_id
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Failed to update previous HOD."
                    );
                }


                $stmt->close();


                /*
                | Demote previous HOD in users table
                */

                $sql = "
                    UPDATE users u

                    INNER JOIN faculty f
                        ON u.user_id = f.user_id

                    SET u.role = 'FACULTY'

                    WHERE f.faculty_id != ?
                    AND f.is_hod = 0
                    AND u.role = 'HOD'
                ";

                $stmt =
                    $conn->prepare($sql);

                if (!$stmt) {

                    throw new Exception(
                        "Failed to update previous HOD account."
                    );
                }


                $stmt->bind_param(
                    "i",
                    $faculty_id
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Failed to update previous HOD account."
                    );
                }


                $stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Update Faculty Table
            |--------------------------------------------------------------------------
            */

            $sql = "
                UPDATE faculty

                SET
                    faculty_code = ?,
                    faculty_name = ?,
                    email = ?,
                    phone = ?,
                    designation = ?,
                    is_hod = ?

                WHERE faculty_id = ?
            ";

            $stmt =
                $conn->prepare($sql);

            if (!$stmt) {

                throw new Exception(
                    "Failed to update faculty."
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

                $stmt->close();

                throw new Exception(
                    "Failed to update faculty."
                );
            }


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Update User Role
            |--------------------------------------------------------------------------
            */

            $role =
                ($is_hod === 1)
                ? "HOD"
                : "FACULTY";


            $sql = "
                UPDATE users

                SET role = ?

                WHERE user_id = ?
            ";

            $stmt =
                $conn->prepare($sql);

            if (!$stmt) {

                throw new Exception(
                    "Failed to update user role."
                );
            }


            $stmt->bind_param(
                "si",
                $role,
                $user_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Failed to update user role."
                );
            }


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Update Password
            |--------------------------------------------------------------------------
            */

            if ($new_password !== "") {

                $hashedPassword =
                    password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );


                $sql = "
                    UPDATE users

                    SET password = ?

                    WHERE user_id = ?
                ";

                $stmt =
                    $conn->prepare($sql);

                if (!$stmt) {

                    throw new Exception(
                        "Failed to update password."
                    );
                }


                $stmt->bind_param(
                    "si",
                    $hashedPassword,
                    $user_id
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Failed to update password."
                    );
                }


                $stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                "Location: faculty.php?success=updated"
            );

            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                $e->getMessage();
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

            <h1>
                Edit Faculty
            </h1>

            <p>
                Update faculty details and account settings.
            </p>

        </div>


        <a
            href="faculty.php"
            class="btn btn-secondary"
        >
            Back to Faculty
        </a>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <div class="form-container">

        <form
            method="POST"
            action=""
        >

            <!-- Faculty Code -->

            <div class="form-group">

                <label for="faculty_code">
                    Faculty Code
                </label>

                <input
                    type="text"
                    id="faculty_code"
                    name="faculty_code"
                    value="<?php
                        echo htmlspecialchars(
                            $faculty_code
                        );
                    ?>"
                    maxlength="20"
                    required
                >

            </div>


            <!-- Faculty Name -->

            <div class="form-group">

                <label for="faculty_name">
                    Faculty Name
                </label>

                <input
                    type="text"
                    id="faculty_name"
                    name="faculty_name"
                    value="<?php
                        echo htmlspecialchars(
                            $faculty_name
                        );
                    ?>"
                    maxlength="100"
                    required
                >

            </div>


            <!-- Username -->

            <div class="form-group">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    value="<?php
                        echo htmlspecialchars(
                            $username
                        );
                    ?>"
                    disabled
                >

                <small>
                    Username cannot be changed here.
                </small>

            </div>


            <!-- Email -->

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php
                        echo htmlspecialchars(
                            $email
                        );
                    ?>"
                    maxlength="100"
                >

            </div>


            <!-- Phone -->

            <div class="form-group">

                <label for="phone">
                    Phone
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?php
                        echo htmlspecialchars(
                            $phone
                        );
                    ?>"
                    maxlength="20"
                >

            </div>


            <!-- Designation -->

            <div class="form-group">

                <label for="designation">
                    Designation
                </label>

                <input
                    type="text"
                    id="designation"
                    name="designation"
                    value="<?php
                        echo htmlspecialchars(
                            $designation
                        );
                    ?>"
                    maxlength="50"
                    required
                >

            </div>


            <!-- Password -->

            <div class="form-group">

                <label for="password">
                    New Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="6"
                >

                <small>
                    Leave blank to keep the current password.
                </small>

            </div>


            <!-- HOD -->

            <div class="form-group">

                <label>

                    <input
                        type="checkbox"
                        name="is_hod"
                        value="1"
                        <?php
                        if ($is_hod === 1) {
                            echo "checked";
                        }
                        ?>
                    >

                    Is HOD

                </label>

                <small>
                    Selecting HOD will make the current HOD a Faculty member.
                </small>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Faculty
                </button>


                <a
                    href="faculty.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</main>


<?php require_once "../includes/footer.php"; ?>