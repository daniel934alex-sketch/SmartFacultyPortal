<nav class="navbar">

    <div class="navbar-left">
        <strong>Smart Faculty Assistant</strong>
    </div>

    <div class="navbar-right">

        <span>
            <?php
            echo htmlspecialchars($_SESSION['username']);
            ?>
        </span>

        <span class="role-badge">
            <?php
            echo htmlspecialchars($_SESSION['role']);
            ?>
        </span>

        <a href="../auth/logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</nav>