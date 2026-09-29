<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Department Management";


/* ==========================================================
   GET DEPARTMENTS
   ========================================================== */

$sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    ORDER BY department_id ASC
";

$result = $conn->query($sql);


/* ==========================================================
   HEADER
   ========================================================== */

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>


<div
    class="main-content"
    style="
        box-sizing:border-box;
        width:auto;
        min-height:100vh;
        overflow-x:hidden;
    "
>

    <div
        style="
            width:100%;
            max-width:1400px;
            margin:0 auto;
        "
    >

        <!-- PAGE HEADER -->

        <div
            style="
                background:#ffffff;
                border-radius:10px;
                padding:20px 25px;
                margin-bottom:20px;
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:20px;
            "
        >

            <div>

                <h2
                    style="
                        margin:0 0 6px 0;
                        color:#1e293b;
                        font-size:26px;
                    "
                >
                    Department Management
                </h2>

                <p
                    style="
                        margin:0;
                        color:#64748b;
                        font-size:15px;
                    "
                >
                    Manage departments in the Smart Faculty Portal.
                </p>

            </div>


            <a
                href="department-add.php"
                style="
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;

                    height:44px;
                    padding:0 20px;

                    background:#2563eb;
                    color:#ffffff;

                    border-radius:7px;

                    text-decoration:none;

                    font-size:15px;
                    font-weight:600;

                    white-space:nowrap;
                "
            >
                + Add Department
            </a>

        </div>


        <!-- DEPARTMENT TABLE -->

        <div
            style="
                background:#ffffff;
                border-radius:10px;
                overflow-x:auto;
            "
        >

            <table
                style="
                    width:100%;
                    border-collapse:collapse;
                    min-width:700px;
                "
            >

                <thead>

                    <tr
                        style="
                            background:#f8fafc;
                            border-bottom:1px solid #e2e8f0;
                        "
                    >

                        <th
                            style="
                                padding:15px;
                                text-align:left;
                            "
                        >
                            Department Code
                        </th>

                        <th
                            style="
                                padding:15px;
                                text-align:left;
                            "
                        >
                            Department Name
                        </th>

                        <th
                            style="
                                padding:15px;
                                text-align:center;
                            "
                        >
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr
                            style="
                                border-bottom:1px solid #e2e8f0;
                            "
                        >

                            <td
                                style="
                                    padding:14px 15px;
                                    font-weight:600;
                                "
                            >
                                <?php
                                echo htmlspecialchars(
                                    $row["department_code"]
                                );
                                ?>
                            </td>


                            <td
                                style="
                                    padding:14px 15px;
                                "
                            >
                                <?php
                                echo htmlspecialchars(
                                    $row["department_name"]
                                );
                                ?>
                            </td>


                            <td
                                style="
                                    padding:14px 15px;
                                    text-align:center;
                                    white-space:nowrap;
                                "
                            >

                                <a
                                    href="department-edit.php?id=<?php
                                        echo (int)$row["department_id"];
                                    ?>"
                                    style="
                                        display:inline-block;
                                        background:#2563eb;
                                        color:#ffffff;
                                        padding:7px 12px;
                                        border-radius:5px;
                                        text-decoration:none;
                                        font-size:13px;
                                        font-weight:600;
                                        margin-right:5px;
                                    "
                                >
                                    Edit
                                </a>


                                <a
                                    href="department-delete.php?id=<?php
                                        echo (int)$row["department_id"];
                                    ?>"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this department?'
                                        );
                                    "
                                    style="
                                        display:inline-block;
                                        background:#991b1b;
                                        color:#ffffff;
                                        padding:7px 12px;
                                        border-radius:5px;
                                        text-decoration:none;
                                        font-size:13px;
                                        font-weight:600;
                                    "
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="3"
                            style="
                                padding:40px;
                                text-align:center;
                                color:#64748b;
                            "
                        >
                            No departments found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

require_once "../includes/footer.php";

?>