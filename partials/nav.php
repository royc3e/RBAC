<nav class="mb-4">
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php') echo ' active'; ?>" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='user_management.php') echo ' active'; ?>" href="user_management.php">User Management</a></li>
        <li class="nav-item"><a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='projects.php') echo ' active'; ?>" href="projects.php">Projects</a></li>
        <li class="nav-item"><a class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='tasks.php') echo ' active'; ?>" href="tasks.php">Tasks</a></li>
        <li class="nav-item ms-auto"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
    </ul>
</nav> 