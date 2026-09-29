<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Student Management";


/* ==========================================================
   GET ALL STUDENTS
   ========================================================== */

$sql = "
    SELECT
        s.student_id,
        s.register_number,
        s.student_name,
        s.email,
        s.phone,
        s.section_id,
        sec.section_name,
        u.username,
        u.status
    FROM students s
    INNER JOIN users u
        ON s.user_id = u.user_id
    INNER JOIN sections sec
        ON s.section_id = sec.section_id
    ORDER BY
        sec.section_id ASC,
        s.register_number ASC
";

$result = $conn->query($sql);


/* ==========================================================
   HEADER
   ========================================================== */

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>


<!-- ==========================================================
     MAIN CONTENT
     ========================================================== -->

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
            box-sizing:border-box;
        "
    >


        <!-- ==================================================
             PAGE HEADER
             ================================================== -->

        <div
            style="
                width:100%;
                min-height:90px;

                background:#ffffff;

                border-radius:10px;

                padding:20px 25px;

                margin-bottom:20px;

                box-sizing:border-box;

                display:flex;
                align-items:center;
                justify-content:space-between;

                gap:20px;
            "
        >

            <div
                style="
                    flex:1;
                    min-width:0;
                "
            >

                <h2
                    style="
                        margin:0 0 6px 0;
                        padding:0;

                        color:#1e293b;

                        font-size:26px;
                        font-weight:700;

                        line-height:1.2;
                    "
                >
                    Student Management
                </h2>

                <p
                    style="
                        margin:0;
                        padding:0;

                        color:#64748b;

                        font-size:15px;
                    "
                >
                    Manage CSE students and their sections.
                </p>

            </div>


            <div
                style="
                    flex-shrink:0;
                "
            >

                <a
                    href="student-add.php"
                    style="
                        display:inline-flex;

                        align-items:center;
                        justify-content:center;

                        min-width:145px;
                        height:44px;

                        padding:0 20px;

                        box-sizing:border-box;

                        background:#2563eb;

                        color:#ffffff;

                        border-radius:7px;

                        text-decoration:none;

                        font-size:15px;

                        font-weight:600;

                        white-space:nowrap;
                    "
                >
                    + Add Student
                </a>

            </div>

        </div>


        <!-- ==================================================
             SECTION CAPACITY
             ================================================== -->

        <div
            style="
                width:100%;

                background:#ffffff;

                border-radius:10px;

                padding:16px 22px;

                margin-bottom:20px;

                box-sizing:border-box;
            "
        >

            <div
                style="
                    color:#475569;
                    font-size:14px;
                "
            >

                <strong
                    style="
                        color:#1e293b;
                    "
                >
                    Section Capacity:
                </strong>

                Each section can have a maximum of

                <strong
                    style="
                        color:#2563eb;
                    "
                >
                    64 students
                </strong>.

            </div>

        </div>


        <!-- ==================================================
             STUDENT TABLE
             ================================================== -->

        <div
            style="
                width:100%;

                background:#ffffff;

                border-radius:10px;

                box-sizing:border-box;

                overflow-x:auto;
            "
        >

            <table
                style="
                    width:100%;

                    border-collapse:collapse;

                    min-width:1050px;
                "
            >


                <!-- ==================================================
                     TABLE HEADER
                     ================================================== -->

                <thead>

                    <tr
                        style="
                            background:#f8fafc;

                            border-bottom:1px solid #e2e8f0;
                        "
                    >

                        <th
                            style="
                                padding:15px 12px;
                                text-align:left;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Register Number
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:left;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Student Name
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:left;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Email
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:left;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Phone
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:center;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Section
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:left;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Username
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:center;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Status
                        </th>

                        <th
                            style="
                                padding:15px 12px;
                                text-align:center;
                                color:#0f172a;
                                font-size:14px;
                                font-weight:700;
                                white-space:nowrap;
                            "
                        >
                            Actions
                        </th>

                    </tr>

                </thead>


                <!-- ==================================================
                     TABLE BODY
                     ================================================== -->

                <tbody>


                <?php if ($result && $result->num_rows > 0): ?>


                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr
                            style="
                                border-bottom:1px solid #e2e8f0;
                            "
                        >


                            <!-- REGISTER NUMBER -->

                            <td
                                style="
                                    padding:14px 12px;
                                    white-space:nowrap;
                                    color:#1e293b;
                                "
                            >

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $row["register_number"]
                                    );
                                    ?>
                                </strong>

                            </td>


                            <!-- STUDENT NAME -->

                            <td
                                style="
                                    padding:14px 12px;
                                    white-space:nowrap;
                                    color:#334155;
                                "
                            >

                                <?php
                                echo htmlspecialchars(
                                    $row["student_name"]
                                );
                                ?>

                            </td>


                            <!-- EMAIL -->

                            <td
                                style="
                                    padding:14px 12px;
                                    color:#475569;
                                "
                            >

                                <?php
                                echo htmlspecialchars(
                                    $row["email"] ?? ""
                                );
                                ?>

                            </td>


                            <!-- PHONE -->

                            <td
                                style="
                                    padding:14px 12px;
                                    white-space:nowrap;
                                    color:#475569;
                                "
                            >

                                <?php
                                echo htmlspecialchars(
                                    $row["phone"] ?? ""
                                );
                                ?>

                            </td>


                            <!-- SECTION -->

                            <td
                                style="
                                    padding:14px 12px;
                                    text-align:center;
                                "
                            >

                                <span
                                    style="
                                        display:inline-block;

                                        background:#eff6ff;

                                        color:#2563eb;

                                        padding:5px 11px;

                                        border-radius:5px;

                                        font-size:13px;

                                        font-weight:600;

                                        white-space:nowrap;
                                    "
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $row["section_name"]
                                    );
                                    ?>
                                </span>

                            </td>


                            <!-- USERNAME -->

                            <td
                                style="
                                    padding:14px 12px;
                                    white-space:nowrap;
                                    color:#475569;
                                "
                            >

                                <?php
                                echo htmlspecialchars(
                                    $row["username"]
                                );
                                ?>

                            </td>


                            <!-- STATUS -->

                            <td
                                style="
                                    padding:14px 12px;
                                    text-align:center;
                                "
                            >

                                <?php if (
                                    $row["status"] === "ACTIVE"
                                ): ?>

                                    <span
                                        style="
                                            display:inline-block;

                                            background:#dcfce7;

                                            color:#166534;

                                            padding:5px 10px;

                                            border-radius:5px;

                                            font-size:12px;

                                            font-weight:600;

                                            white-space:nowrap;
                                        "
                                    >
                                        ACTIVE
                                    </span>

                                <?php else: ?>

                                    <span
                                        style="
                                            display:inline-block;

                                            background:#fee2e2;

                                            color:#991b1b;

                                            padding:5px 10px;

                                            border-radius:5px;

                                            font-size:12px;

                                            font-weight:600;

                                            white-space:nowrap;
                                        "
                                    >
                                        INACTIVE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- ==================================================
                                 ACTIONS
                                 IMPORTANT: THIS IS INSIDE THE WHILE LOOP
                                 ================================================== -->

                            <td
                                style="
                                    padding:14px 12px;

                                    text-align:center;

                                    white-space:nowrap;
                                "
                            >


                                <!-- EDIT -->

                                <a
                                    href="student-edit.php?id=<?php
                                        echo (int)$row["student_id"];
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


                                <!-- DEACTIVATE -->

                                <?php if (
                                    $row["status"] === "ACTIVE"
                                ): ?>

                                    <a
                                        href="student-status.php?id=<?php
                                            echo (int)$row["student_id"];
                                        ?>&status=INACTIVE"
                                        onclick="
                                            return confirm(
                                                'Deactivate this student?'
                                            );
                                        "
                                        style="
                                            display:inline-block;

                                            background:#dc2626;

                                            color:#ffffff;

                                            padding:7px 12px;

                                            border-radius:5px;

                                            text-decoration:none;

                                            font-size:13px;

                                            font-weight:600;

                                            margin-right:5px;
                                        "
                                    >
                                        Deactivate
                                    </a>

                                <?php else: ?>


                                    <!-- ACTIVATE -->

                                    <a
                                        href="student-status.php?id=<?php
                                            echo (int)$row["student_id"];
                                        ?>&status=ACTIVE"
                                        onclick="
                                            return confirm(
                                                'Activate this student?'
                                            );
                                        "
                                        style="
                                            display:inline-block;

                                            background:#16a34a;

                                            color:#ffffff;

                                            padding:7px 12px;

                                            border-radius:5px;

                                            text-decoration:none;

                                            font-size:13px;

                                            font-weight:600;

                                            margin-right:5px;
                                        "
                                    >
                                        Activate
                                    </a>

                                <?php endif; ?>


                                <!-- DELETE -->

                                <a
                                    href="student-delete.php?id=<?php
                                        echo (int)$row["student_id"];
                                    ?>"
                                    onclick="
                                        return confirm(
                                            'WARNING: This will permanently delete this student and their login account. Continue?'
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


                    <!-- NO STUDENTS -->

                    <tr>

                        <td
                            colspan="8"
                            style="
                                padding:45px 20px;

                                text-align:center;

                                color:#64748b;

                                font-size:15px;
                            "
                        >
                            No students found.
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