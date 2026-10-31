<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Department Management";

/* GET DEPARTMENTS */

$sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    ORDER BY department_id ASC
";

$result = $conn->query($sql);

/* SHARED HOD LAYOUT */

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<style>
/* ==========================================
   DEPARTMENT MANAGEMENT - PREMIUM DARK THEME
   PAGE-SPECIFIC CSS ONLY
   ========================================== */

main.department-page {
    margin-left: 245px;
    padding: 104px 32px 44px;
    min-height: 100vh;
    width: auto;
    background: #0c1220;
    color: #e8edf7;
    box-sizing: border-box;
}

.department-page *,
.department-page *::before,
.department-page *::after {
    box-sizing: border-box;
}

.department-page .department-wrap {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}

/* PAGE HEADER */

.department-page .department-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding: 27px 30px;
    border-radius: 17px;
    border: 1px solid #524785;
    background: linear-gradient(
        115deg,
        #28244e 0%,
        #39316e 60%,
        #574898 100%
    );
}

.department-page .department-heading h1 {
    margin: 0 0 8px;
    color: #ffffff;
    font-size: 29px;
    line-height: 1.2;
    font-weight: 800;
}

.department-page .department-heading p {
    margin: 0;
    color: #e1d9fb;
    font-size: 13px;
    line-height: 1.6;
}

/* ADD DEPARTMENT BUTTON */

.department-page .department-add-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    min-height: 44px;
    border-radius: 10px;
    border: 1px solid #ccbaff;
    background: #b7a5ff;
    color: #1b1531;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.department-page .department-add-btn:hover {
    background: #ccbeff;
}

/* TABLE CONTAINER */

.department-page .department-table-card {
    background: #172235;
    border: 1px solid #2c3a50;
    border-radius: 16px;
    overflow-x: auto;
    box-shadow: 0 12px 30px rgba(0,0,0,.13);
}

/* TABLE */

.department-page .department-table {
    width: 100%;
    min-width: 670px;
    border-collapse: collapse;
    table-layout: auto;
    background: transparent;
}

/* TABLE HEADER */

.department-page .department-table thead tr {
    background: #202d43;
}

.department-page .department-table th {
    padding: 18px 20px;
    border-bottom: 1px solid #394860;
    color: #d9d0ff;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
    width: auto;
    min-width: 0;
}

/* TABLE BODY */

.department-page .department-table td {
    padding: 17px 20px;
    border-bottom: 1px solid #2c3a50;
    color: #e0e8f6;
    font-size: 13px;
    vertical-align: middle;
}

.department-page .department-table tbody tr:last-child td {
    border-bottom: 0;
}

.department-page .department-table tbody tr:hover {
    background: #1e2c43;
}

/* DEPARTMENT CODE */

.department-page .department-table th:first-child,
.department-page .department-table td:first-child {
    width: 25%;
    min-width: 145px;
    max-width: none;
}

.department-page .department-table td:first-child {
    color: #c5b7ff;
    font-weight: 800;
}

/* ACTIONS COLUMN */

.department-page .department-table th:last-child,
.department-page .department-table td:last-child {
    text-align: center;
    width: 205px;
    white-space: nowrap;
}

/* ACTION BUTTONS */

.department-page .department-actions {
    display: inline-flex;
    justify-content: center;
    gap: 9px;
}

.department-page .department-action {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 9px 13px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    color: #ffffff;
}

.department-page .department-edit {
    background: #4359a9;
}

.department-page .department-edit:hover {
    background: #546ccc;
}

.department-page .department-delete {
    background: #922c45;
}

.department-page .department-delete:hover {
    background: #ba3b59;
}

/* EMPTY TABLE */

.department-page .department-empty {
    padding: 42px 20px !important;
    color: #a6b5cd !important;
    text-align: center !important;
}

/* RESPONSIVE */

@media (max-width: 760px) {
    main.department-page {
        margin-left: 0;
        padding: 92px 16px 34px;
    }

    .department-page .department-heading {
        padding: 24px 20px;
        align-items: flex-start;
    }

    .department-page .department-heading h1 {
        font-size: 24px;
    }

    .department-page .department-add-btn {
        width: 100%;
    }
}
</style>

<main class="department-page">

    <div class="department-wrap">

        <!-- PAGE HEADER -->

        <div class="department-heading">

            <div>
                <h1>Department Management</h1>

                <p>
                    Manage departments in the
                    Smart Faculty Portal.
                </p>
            </div>

            <a
                class="department-add-btn"
                href="department-add.php"
            >
                + Add Department
            </a>

        </div>

        <!-- DEPARTMENTS TABLE -->

        <div class="department-table-card">

            <table class="department-table">

                <thead>
                    <tr>
                        <th>Department Code</th>
                        <th>Department Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($result && $result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <!-- DEPARTMENT CODE -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row["department_code"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <!-- DEPARTMENT NAME -->

                                <td>
                                    <?= htmlspecialchars(
                                        $row["department_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <!-- ACTION BUTTONS -->

                                <td>

                                    <div class="department-actions">

                                        <a
                                            class="department-action department-edit"
                                            href="department-edit.php?id=<?= (int)$row["department_id"] ?>"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            class="department-action department-delete"
                                            href="department-delete.php?id=<?= (int)$row["department_id"] ?>"
                                            onclick="return confirm('Are you sure you want to delete this department?');"
                                        >
                                            Delete
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="department-empty">
                                No departments found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
