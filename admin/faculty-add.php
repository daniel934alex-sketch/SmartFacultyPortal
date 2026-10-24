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


<style>
/* ============================================
   ADD FACULTY - PREMIUM DARK FORM
   This CSS applies only on faculty-add.php
   ============================================ */

body {
    background: #0c1220;
    color: #f1f5ff;
}

/* PAGE HEADER */

main .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 28px;
}

main .page-header h1 {
    margin: 0 0 7px;
    color: #fff;
    font-size: 32px;
    font-weight: 800;
}

main .page-header p {
    margin: 0;
    color: #a5b2c8;
    font-size: 13px;
}

/* FORM PANEL */

main .form-container {
    width: 100%;
    max-width: 850px;
    margin-top: 18px;
    padding: 34px;
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 18px;
    box-shadow: 0 15px 35px rgba(0,0,0,.15);
}

/* TWO-COLUMN FORM */

main .form-container form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 24px;
    row-gap: 21px;
}

/* FIELD GROUPS */

main .form-container .form-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
    margin: 0;
}

main .form-container .form-group label {
    display: block;
    margin-bottom: 10px;
    color: #d7deeb;
    font-size: 12px;
    font-weight: 700;
}

/* INPUTS */

main .form-container input[type="text"],
main .form-container input[type="password"],
main .form-container input[type="email"] {
    display: block;
    width: 100%;
    min-width: 0;
    height: 49px;
    padding: 0 15px;
    border: 1px solid #3a4860;
    border-radius: 10px;
    outline: none;
    background: #10192a;
    color: #f4f6ff;
    font-family: inherit;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

main .form-container input:focus {
    border-color: #ad98ff;
    box-shadow: 0 0 0 3px rgba(163,136,255,.15);
}

main .form-container input::placeholder {
    color: #73829a;
}

/* HOD CHECKBOX */

main .form-container .form-group:has(input[name="is_hod"]) {
    grid-column: 1 / -1;
    padding: 15px 17px;
    border: 1px solid #3a3a60;
    border-radius: 11px;
    background: #242540;
}

main .form-container .form-group:has(input[name="is_hod"]) label {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    color: #e3dcff;
    cursor: pointer;
}

main .form-container input[type="checkbox"] {
    width: 17px;
    height: 17px;
    margin: 0;
    accent-color: #a995ff;
    cursor: pointer;
}

/* BUTTON AREA */

main .form-container .form-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    grid-column: 1 / -1;
    padding-top: 8px;
}

main .form-container .btn,
main .page-header .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 43px;
    padding: 11px 20px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 12px;
    font-weight: 750;
    text-decoration: none;
    cursor: pointer;
}

main .form-container .btn-primary {
    background: linear-gradient(110deg, #b7a5ff, #947bfa);
    color: #1a1530;
}

main .form-container .btn-primary:hover {
    background: #c8bcff;
}

main .form-container .btn-secondary,
main .page-header .btn-secondary {
    background: #29354c;
    border-color: #485673;
    color: #d7e0f0;
}

main .form-container .btn-secondary:hover,
main .page-header .btn-secondary:hover {
    background: #35425c;
}

/* VALIDATION ERRORS */

main .alert-error {
    max-width: 850px;
    margin: 0 0 20px;
    padding: 15px 18px;
    border: 1px solid #91485c;
    border-radius: 11px;
    background: #492b39;
    color: #ffb8c6;
    font-size: 13px;
    line-height: 1.7;
}

/* RESPONSIVE */

@media (max-width: 760px) {
    main .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    main .page-header h1 {
        font-size: 26px;
    }

    main .form-container {
        padding: 22px 18px;
    }

    main .form-container form {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    main .form-container .form-group:has(input[name="is_hod"]),
    main .form-container .form-actions {
        grid-column: 1;
    }

    main .form-container .form-actions .btn {
        flex: 1;
    }
}
</style>



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