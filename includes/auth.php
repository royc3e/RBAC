<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role_id']) && ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die("Access denied. Admin privileges required.");
    }
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}

function can_perform($action) {
    $role_id = $_SESSION['role_id'] ?? 0;
    $permissions = [
        'create_task' =>    [1,2,3,4,5,6,9],
        'edit_task' =>      [1,2,3,4,5,6,9],
        'delete_task' =>    [1,2,3,4,9],
        'mark_task_complete'=>[1,2,3,4,5,6,9],
        'view_all_tasks' => [1,2,3,4,5,6,9,8,10],
        'view_assigned_tasks'=>[1,2,3,4,5,6,9,8,10],
        'assign_task' =>    [1,2,3,4,9],
        'create_project' => [1,2,3,4,9],
        'edit_project' =>   [1,2,3,4,9],
        'delete_project' => [1,2,3,4],
        'view_projects' =>  [1,2,3,4,5,6,9,8,10],
        'add_comment' =>    [1,2,3,4,5,6,9],
        'edit_comment' =>   [1,2,3,4,5,6,9],
        'delete_comment' => [1,2,3,4,9],
        'view_comments' =>  [1,2,3,4,5,6,9,8,10],
        'view_activity_logs' => [1,2,3,9,8],
        'generate_reports' => [1,2,3,4,9,8],
    ];
    return in_array($role_id, $permissions[$action] ?? []);
}
?> 