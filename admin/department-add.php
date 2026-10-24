
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Department";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $department_code = strtoupper(
        trim($_POST["department_code"] ?? "")
    );

    $department_name = trim(
        $_POST["department_name"] ?? ""
    );

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

                // Insert department

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

<style>
/* =====================================================
   ADD DEPARTMENT - PREMIUM DARK HOD THEME
   PAGE-SPECIFIC CSS ONLY
   ===================================================== */

/* MAIN CONTENT */

main.department-add-page {
    color: #f1f5ff;
    min-height: 100vh;
    box-sizing: border-box;
}

.department-add-page *,
.department-add-page *::before,
.department-add-page *::after {
    box-sizing: border-box;
}

.department-add-page .department-add-wrapper {
    max-width: 950px;
    margin: 0 auto;
}

/* PAGE HEADER */

.department-add-page .department-add-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;

    padding: 27px 30px;
    margin-bottom: 24px;

    border: 1px solid #51478c;
    border-radius: 18px;

    background: linear-gradient(
        115deg,
        #28244e 0%,
        #39316e 60%,
        #574898 100%
    );
}

.department-add-page .department-add-header h1 {
    margin: 0 0 9px;

    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.3;
}

.department-add-page .department-add-header p {
    margin: 0;

    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* BACK BUTTON */

.department-add-page .department-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 43px;
    padding: 11px 20px;

    border: 1px solid #8f83bd;
    border-radius: 10px;

    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;

    font-size: 12px;
    font-weight: 750;
    text-decoration: none;

    transition: background 0.2s ease;
}

.department-add-page .department-back-btn:hover {
    background: rgba(255, 255, 255, 0.17);
}

/* FORM CARD */

.department-add-page .department-add-card {
    width: 100%;

    padding: 35px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;

    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.16);
}

/* FORM */

.department-add-page .department-add-form {
    display: flex;
    flex-direction: column;
    gap: 23px;
}

/* FORM FIELDS */

.department-add-page .department-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.department-add-page .department-field label {
    display: block;

    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUT BOXES */

.department-add-page .department-field input {
    display: block;

    width: 100%;
    min-width: 0;
    height: 51px;

    padding: 12px 15px;

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

.department-add-page .department-field input::placeholder {
    color: #91a0b9;
}

.department-add-page .department-field input:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175, 155, 255, 0.14);
}

/* ACTION BUTTONS */

.department-add-page .department-add-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;

    margin-top: 7px;
}

.department-add-page .department-add-submit,
.department-add-page .department-add-cancel {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    min-height: 46px;
    padding: 12px 23px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;

    cursor: pointer;

    transition:
        background 0.2s ease,
        filter 0.2s ease;
}

/* ADD DEPARTMENT BUTTON */

.department-add-page .department-add-submit {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.department-add-page .department-add-submit:hover {
    filter: brightness(1.1);
}

/* CANCEL BUTTON */

.department-add-page .department-add-cancel {
    border: 1px solid #4b5a74;

    background: #28364e;
    color: #e1e9f7;
}

.department-add-page .department-add-cancel:hover {
    background: #34445f;
}

/* ERROR MESSAGE */

.department-add-page .department-add-alert {
    margin-bottom: 22px;
    padding: 15px 18px;

    border: 1px solid #915067;
    border-radius: 11px;

    background: #482a38;
    color: #ffd1da;

    font-size: 13px;
    line-height: 1.7;
}

/* MOBILE RESPONSIVE */

@media (max-width: 760px) {

    .department-add-page .department-add-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .department-add-page .department-add-header h1 {
        font-size: 25px;
    }

    .department-add-page .department-add-card {
        padding: 23px 19px;
    }

    .department-add-page .department-add-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .department-add-page .department-add-submit,
    .department-add-page .department-add-cancel {
        width: 100%;
    }

}
</style>

<main class="department-add-page">

    <div class="department-add-wrapper">

        <!-- PAGE HEADER -->

        <div class="department-add-header">

            <div>

                <h1>Add Department</h1>

                <p>
                    Create a new department.
                </p>

            </div>

            <a
                href="departments.php"
                class="department-back-btn"
            >
                ← Back to Departments
            </a>

        </div>


        <!-- VALIDATION ERROR -->

        <?php if ($error !== ""): ?>

            <div
                class="department-add-alert"
                role="alert"
            >
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

        <?php endif; ?>


        <!-- FORM CARD -->

        <div class="department-add-card">

            <form
                method="POST"
                action=""
                class="department-add-form"
            >

                <!-- DEPARTMENT CODE -->

                <div class="department-field">

                    <label for="department_code">
                        Department Code
                    </label>

                    <input
                        type="text"
                        id="department_code"
                        name="department_code"
                        maxlength="20"
                        placeholder="Example: CSE"
                        value="<?php
                            echo htmlspecialchars(
                                $department_code ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- DEPARTMENT NAME -->

                <div class="department-field">

                    <label for="department_name">
                        Department Name
                    </label>

                    <input
                        type="text"
                        id="department_name"
                        name="department_name"
                        maxlength="100"
                        placeholder="Example: Computer Science and Engineering"
                        value="<?php
                            echo htmlspecialchars(
                                $department_name ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- BUTTONS -->

                <div class="department-add-actions">

                    <button
                        type="submit"
                        class="department-add-submit"
                    >
                        Add Department
                    </button>

                    <a
                        href="departments.php"
                        class="department-add-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
