<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "Faculty Management";


// Fetch all faculty
$sql = "
    SELECT
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        f.email,
        f.phone,
        f.designation,
        f.is_hod,
        u.username,
        u.status
    FROM faculty f
    INNER JOIN users u
        ON f.user_id = u.user_id
    ORDER BY f.faculty_id ASC
";

$result = $conn->query($sql);

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>

<main>

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:25px;
    ">

        <div>

            <h1>Faculty Management</h1>

            <p>
                Manage CSE faculty members and their login accounts.
            </p>

        </div>

        <a href="faculty-add.php"
           class="btn btn-primary">

            + Add Faculty

        </a>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Faculty Code</th>

                    <th>Name</th>

                    <th>Username</th>

                    <th>Email</th>

                    <th>Phone</th>

                    <th>Designation</th>

                    <th>HOD</th>

                    <th>Status</th>

                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php
                            echo $row['faculty_id'];
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['faculty_code']
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['faculty_name']
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['username']
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['email'] ?? '-'
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['phone'] ?? '-'
                            );
                            ?>
                        </td>


                        <td>
                            <?php
                            echo htmlspecialchars(
                                $row['designation'] ?? '-'
                            );
                            ?>
                        </td>


                        <td>

                            <?php if ($row['is_hod'] == 1): ?>

                                <span>Yes</span>

                            <?php else: ?>

                                <span>No</span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if ($row['status'] === 'ACTIVE'): ?>

                                <span>Active</span>

                            <?php else: ?>

                                <span>Inactive</span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <a
                                href="faculty-edit.php?id=<?php
                                    echo $row['faculty_id'];
                                ?>"
                                class="btn btn-primary"
                            >
                                Edit
                            </a>

                            <a
    href="faculty-subjects.php?faculty_id=<?php
        echo $row['faculty_id'];
    ?>"
    class="btn btn-success"
>
    Subjects
</a>


                            <?php if ($row['status'] === 'ACTIVE'): ?>

                                <a
                                    href="faculty-status.php?id=<?php
                                        echo $row['faculty_id'];
                                    ?>&status=INACTIVE"
                                    class="btn btn-danger"
                                    onclick="return confirm(
                                        'Deactivate this faculty member?'
                                    );"
                                >
                                    Deactivate
                                </a>

                            <?php else: ?>

                                <a
                                    href="faculty-status.php?id=<?php
                                        echo $row['faculty_id'];
                                    ?>&status=ACTIVE"
                                    class="btn btn-success"
                                >
                                    Activate
                                </a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="10">
                        No faculty members found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>