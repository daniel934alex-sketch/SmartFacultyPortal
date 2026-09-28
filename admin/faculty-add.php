<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Faculty";


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$faculty_code = "";
$faculty_name = "";
$username = "";
$email = "";
$phone = "";
$designation = "Assistant Professor";

$is_hod = 0;

$error = "";


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_code = trim($_POST["faculty_code"] ?? "");
    $faculty_name = trim($_POST["faculty_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $designation = trim(
        $_POST["designation"] ?? "Assistant Professor"
    );

    $is_hod = isset($_POST["is_hod"]) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($faculty_code === "") {

        $error = "Please enter the faculty code.";

    } elseif ($faculty_name === "") {

        $error = "Please enter the faculty name.";

    } elseif ($username === "") {

        $error = "Please enter the username.";

    } elseif ($password === "") {

        $error = "Please enter the password.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } elseif ($designation === "") {

        $error = "Please enter the designation.";

    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Faculty Code
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $sql = "
            SELECT faculty_id
            FROM faculty
            WHERE faculty_code = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param(
                "s",
                $faculty_code
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
    | Check Duplicate Username
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $sql = "
            SELECT user_id
            FROM users
            WHERE username = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Database error.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    "Username already exists.";
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing HOD
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $is_hod === 1
    ) {

        $sql = "
            SELECT faculty_id
            FROM faculty
            WHERE is_hod = 1
            LIMIT 1
        ";

        $result = $conn->query($sql);

        if (
            $result &&
            $result->num_rows > 0
        ) {

            $error =
                "A HOD already exists. Please edit the current HOD before assigning another HOD.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Faculty
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */

            $hashedPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            $role =
                ($is_hod === 1)
                ? "HOD"
                : "FACULTY";


            $status = "ACTIVE";


            $sql = "
                INSERT INTO users
                (
                    username,
                    password,
                    role,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {

                throw new Exception(
                    "Failed to create user account."
                );
            }


            $stmt->bind_param(
                "ssss",
                $username,
                $hashedPassword,
                $role,
                $status
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Failed to create user account."
                );
            }


            $user_id =
                $stmt->insert_id;

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Create Faculty
            |--------------------------------------------------------------------------
            */

            /*
            | Department 1 = CSE
            */

            $department_id = 1;


            $sql = "
                INSERT INTO faculty
                (
                    user_id,
                    faculty_code,
                    faculty_name,
                    email,
                    phone,
                    department_id,
                    designation,
                    is_hod
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {

                throw new Exception(
                    "Failed to create faculty record."
                );
            }


            $stmt->bind_param(
                "issssisi",
                $user_id,
                $faculty_code,
                $faculty_name,
                $email,
                $phone,
                $department_id,
                $designation,
                $is_hod
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Failed to create faculty record."
                );
            }


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                "Location: faculty.php?success=added"
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
                Add Faculty
            </h1>

            <p>
                Create a new faculty account.
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

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?php
                        echo htmlspecialchars(
                            $username
                        );
                    ?>"
                    maxlength="50"
                    required
                >

            </div>


            <!-- Password -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="6"
                    required
                >

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

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Faculty
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