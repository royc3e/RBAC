<?php
require_once __DIR__ . '/../includes/auth.php';
// partials/sidebar.php
?>
<nav class="sidebar">
    <div class="sidebar-logo">To-Do</div>
    <div class="sidebar-content">
        <ul>
            <li><a href="dashboard.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php')echo 'active';?>"><i class="bi bi-house-door"></i> Dashboard</a></li>
            <?php if (isset($_SESSION['role_name']) && in_array($_SESSION['role_name'], ['Super Admin', 'Admin'])): ?>
            <li><a href="user_management.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='user_management.php')echo 'active';?>"><i class="bi bi-people"></i> User Management</a></li>
            <?php endif; ?>
            <?php if (can_perform('view_activity_logs')): ?>
            <li><a href="activity_logs.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='activity_logs.php')echo 'active';?>"><i class="bi bi-clipboard-data"></i> Activity Logs</a></li>
            <?php endif; ?>
            <?php if (can_perform('generate_reports')): ?>
            <li><a href="generate_reports.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='generate_reports.php')echo 'active';?>"><i class="bi bi-printer"></i> Generate Reports</a></li>
            <?php endif; ?>
            <li><a href="projects.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='projects.php')echo 'active';?>"><i class="bi bi-kanban"></i> Projects</a></li>
            <li><a href="tasks.php" class="<?php if(basename($_SERVER['PHP_SELF'])=='tasks.php')echo 'active';?>"><i class="bi bi-list-check"></i> Tasks</a></li>
        </ul>
    </div>
    <a href="logout.php" class="sidebar-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    <div class="sidebar-footer">
        Logged in as <b><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></b>
    </div>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var sidebarToggle = document.getElementById('sidebarToggle');
    var hamburgerIcon = document.getElementById('hamburgerIcon');
    if (sidebarToggle && hamburgerIcon) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            hamburgerIcon.classList.toggle('active');
        });
    }
});
</script> 