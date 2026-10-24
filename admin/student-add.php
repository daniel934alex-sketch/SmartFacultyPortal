
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Student";

$error = "";

$register_number = "";
$student_name = "";
$email = "";
$phone = "";
$section_id = "";
$username = "";


/* ==========================================================
   GET SECTIONS
   ========================================================== */

$sections = [];

$sectionSql = "
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_id ASC
";

$sectionResult = $conn->query($sectionSql);

if ($sectionResult) {
    while ($section = $sectionResult->fetch_assoc()) {
        $sections[] = $section;
    }
}


/* ==========================================================
   FORM SUBMISSION
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $register_number = trim($_POST["register_number"] ?? "");
    $student_name = trim($_POST["student_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $section_id = (int)($_POST["section_id"] ?? 0);
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";


    /* BASIC VALIDATION */

    if (
        $register_number === "" ||
        $student_name === "" ||
        $section_id <= 0 ||
        $username === "" ||
        $password === ""
    ) {
        $error = "Please fill in all required fields.";
    }


    /* CHECK SECTION */

    if ($error === "") {

        $sql = "
            SELECT
                section_id,
                section_name
            FROM sections
            WHERE section_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Unable to verify section.";
        } else {

            $stmt->bind_param("i", $section_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Invalid section selected.";
            }

            $stmt->close();
        }
    }


    /* CHECK SECTION CAPACITY - MAXIMUM 64 */

    if ($error === "") {

        $sql = "
            SELECT COUNT(*) AS total_students
            FROM students
            WHERE section_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Unable to check section capacity.";
        } else {

            $stmt->bind_param("i", $section_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $total_students = (int)$row["total_students"];

            $stmt->close();

            if ($total_students >= 64) {
                $error = "This section already has 64 students. You cannot add another student to this section.";
            }
        }
    }


    /* CHECK DUPLICATE REGISTER NUMBER */

    if ($error === "") {

        $sql = "
            SELECT student_id
            FROM students
            WHERE register_number = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Unable to check register number.";
        } else {

            $stmt->bind_param("s", $register_number);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "This register number already exists.";
            }

            $stmt->close();
        }
    }


    /* CHECK DUPLICATE USERNAME */

    if ($error === "") {

        $sql = "
            SELECT user_id
            FROM users
            WHERE username = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Unable to check username.";
        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "This username already exists.";
            }

            $stmt->close();
        }
    }


    /* PASSWORD VALIDATION */

    if ($error === "") {

        if (strlen($password) < 6) {
            $error = "Password must contain at least 6 characters.";
        }
    }


    /* CREATE STUDENT */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /* HASH PASSWORD */

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* CREATE LOGIN ACCOUNT */

            $userSql = "
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
                    'STUDENT',
                    'ACTIVE'
                )
            ";

            $stmt = $conn->prepare($userSql);

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare user creation."
                );
            }

            $stmt->bind_param(
                "ss",
                $username,
                $password_hash
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to create student login account."
                );
            }

            $user_id = $stmt->insert_id;
            $stmt->close();


            /* CREATE STUDENT RECORD */

            $studentSql = "
                INSERT INTO students
                (
                    user_id,
                    register_number,
                    student_name,
                    email,
                    phone,
                    section_id
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt = $conn->prepare($studentSql);

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare student creation."
                );
            }

            $stmt->bind_param(
                "issssi",
                $user_id,
                $register_number,
                $student_name,
                $email,
                $phone,
                $section_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to create student record."
                );
            }

            $stmt->close();


            /* SAVE TRANSACTION */

            $conn->commit();

            header(
                "Location: students.php?success=added"
            );

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error = "Unable to add student. Please try again.";
        }
    }
}


/* ==========================================================
   SHARED HOD LAYOUT
   ========================================================== */

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<style>
/* ==========================================
   ADD STUDENT - PREMIUM DARK HOD THEME
   PAGE-SPECIFIC CSS ONLY
   ========================================== */

/* PAGE LAYOUT */

.student-add-page {
    margin-left: 245px;
    padding: 105px 32px 48px;
    min-height: 100vh;
    color: #e8edf8;
    background: #0c1220;
    box-sizing: border-box;
}

.student-add-page *,
.student-add-page *::before,
.student-add-page *::after {
    box-sizing: border-box;
}

.student-add-wrap {
    max-width: 950px;
    margin: 0 auto;
}

/* PAGE HEADER */

.student-add-head {
    padding: 27px 30px;
    margin-bottom: 22px;
    border: 1px solid #51478c;
    border-radius: 17px;
    background: linear-gradient(
        115deg,
        #28244e,
        #39316e 60%,
        #574898
    );
}

.student-add-head h1 {
    margin: 0 0 9px;
    font-size: 29px;
    font-weight: 800;
    color: #ffffff;
}

.student-add-head p {
    margin: 0;
    font-size: 14px;
    color: #ddd5f6;
}

/* ERROR MESSAGE */

.student-add-alert {
    padding: 15px 18px;
    margin-bottom: 22px;
    border: 1px solid #915067;
    border-radius: 10px;
    background: #482a38;
    color: #ffd1da;
}

/* FORM CARD */

.student-add-card {
    padding: 32px;
    border: 1px solid #2e3c52;
    border-radius: 17px;
    background: #172235;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.16);
}

/* TWO COLUMN FORM */

.student-add-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px 24px;
}

/* FORM FIELDS */

.student-add-field {
    min-width: 0;
}

.student-add-field label {
    display: block;
    margin: 0 0 9px;
    font-size: 13px;
    font-weight: 700;
    color: #dce5f4;
}

/* INPUT FIELDS AND DROPDOWN */

.student-add-field input,
.student-add-field select {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 48px;
    padding: 11px 14px;
    border: 1px solid #3a4962;
    border-radius: 10px;
    outline: none;
    background: #10192a;
    color: #f4f6ff;
    font-family: inherit;
    font-size: 14px;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.student-add-field input::placeholder {
    color: #91a0b9;
}

.student-add-field input:focus,
.student-add-field select:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175, 155, 255, 0.14);
}

.student-add-field select option {
    background: #10192a;
    color: #ffffff;
}

/* HELPER TEXT */

.student-add-field small {
    display: block;
    margin-top: 8px;
    color: #a8b6ce;
    font-size: 12px;
    line-height: 1.5;
}

/* ACTION BUTTONS */

.student-add-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 4px;
}

.student-add-actions button,
.student-add-actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 45px;
    padding: 12px 23px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 750;
    cursor: pointer;
}

/* ADD STUDENT BUTTON */

.student-add-save {
    border: none;
    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );
    color: #191330;
}

.student-add-save:hover {
    filter: brightness(1.1);
}

/* CANCEL BUTTON */

.student-add-cancel {
    border: 1px solid #4b5a74;
    background: #28364e;
    color: #e1e9f7;
}

.student-add-cancel:hover {
    background: #34445f;
}

/* RESPONSIVE DESIGN */

@media (max-width: 1050px) {
    .student-add-page {
        padding: 100px 22px 35px;
    }
}

@media (max-width: 760px) {
    .student-add-page {
        margin-left: 0;
        padding: 92px 16px 30px;
    }

    .student-add-card {
        padding: 22px 18px;
    }

    .student-add-head {
        padding: 23px 20px;
    }

    .student-add-head h1 {
        font-size: 25px;
    }

    .student-add-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }
}
</style>


<!-- ======================================
     ADD STUDENT PAGE CONTENT
     ====================================== -->

<div class="student-add-page">

    <div class="student-add-wrap">

        <!-- PAGE HEADER -->

        <div class="student-add-head">

            <h1>Add Student</h1>

            <p>
                Add a student to a CSE section.
            </p>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($error !== ""): ?>

            <div
                class="student-add-alert"
                role="alert"
            >
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <!-- FORM CARD -->

        <div class="student-add-card">

            <form method="POST" action="">

                <div class="student-add-grid">


                    <!-- REGISTER NUMBER -->

                    <div class="student-add-field">

                        <label for="register_number">
                            Register Number *
                        </label>

                        <input
                            type="text"
                            id="register_number"
                            name="register_number"
                            maxlength="30"
                            required
                            placeholder="Example: 714025104042"
                            value="<?= htmlspecialchars(
                                $register_number,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <!-- STUDENT NAME -->

                    <div class="student-add-field">

                        <label for="student_name">
                            Student Name *
                        </label>

                        <input
                            type="text"
                            id="student_name"
                            name="student_name"
                            maxlength="100"
                            required
                            placeholder="Enter student name"
                            value="<?= htmlspecialchars(
                                $student_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="student-add-field">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="100"
                            placeholder="student@example.com"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="student-add-field">

                        <label for="phone">
                            Phone
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="20"
                            placeholder="Enter phone number"
                            value="<?= htmlspecialchars(
                                $phone,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <!-- SECTION -->

                    <div class="student-add-field">

                        <label for="section_id">
                            Section *
                        </label>

                        <select
                            id="section_id"
                            name="section_id"
                            required
                        >

                            <option value="">
                                Select Section
                            </option>

                            <?php foreach ($sections as $section): ?>

                                <option
                                    value="<?= (int)$section['section_id'] ?>"
                                    <?= (int)$section_id ===
                                    (int)$section['section_id']
                                    ? 'selected'
                                    : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        $section['section_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small>
                            Maximum 64 students are allowed
                            in each section.
                        </small>

                    </div>


                    <!-- USERNAME -->

                    <div class="student-add-field">

                        <label for="username">
                            Student Username *
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="50"
                            required
                            placeholder="Example: 714025104042"
                            value="<?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="student-add-field">

                        <label for="password">
                            Password *
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="6"
                            required
                            placeholder="Minimum 6 characters"
                        >

                    </div>


                    <!-- FORM BUTTONS -->

                    <div class="student-add-actions">

                        <button
                            type="submit"
                            class="student-add-save"
                        >
                            Add Student
                        </button>

                        <a
                            href="students.php"
                            class="student-add-cancel"
                        >
                            Cancel
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>
