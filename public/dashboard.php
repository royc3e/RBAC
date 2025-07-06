<?php
require_once '../includes/auth.php';
requireLogin();
require_once '../config/db.php';
require_once '../includes/activity_log.php';
include '../partials/header.php';
include '../partials/sidebar.php';
// Get user initial for avatar
$user_initial = isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U';
// Fetch stats
$user_count = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$project_count = $conn->query("SELECT COUNT(*) as cnt FROM projects")->fetch_assoc()['cnt'];
$task_count = $conn->query("SELECT COUNT(*) as cnt FROM tasks")->fetch_assoc()['cnt'];
$completed_count = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE status = 'Completed'")->fetch_assoc()['cnt'];
// Fetch recent activity logs
$activity_logs = $conn->query("SELECT username, action, details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
// Fetch recent tasks
$recent_tasks = $conn->query("SELECT t.title, t.status, t.due_date, GROUP_CONCAT(u.username SEPARATOR ', ') as assignees FROM tasks t LEFT JOIN task_assignees ta ON t.task_id = ta.task_id LEFT JOIN users u ON ta.user_id = u.user_id GROUP BY t.task_id ORDER BY t.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<div class="main-content">
    <div class="topbar d-flex justify-content-between align-items-center">
        <span class="fs-3 fw-bold">Dashboard</span>
        <span class="topbar-avatar bg-primary text-white fw-bold" style="font-size:1.3em; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%;"><?php echo $user_initial; ?></span>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card text-center shadow-sm">
                <div class="stat-title text-muted">Users</div>
                <div class="stat-value display-5 fw-bold"><i class="bi bi-people"></i> <?php echo $user_count; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center shadow-sm">
                <div class="stat-title text-muted">Projects</div>
                <div class="stat-value display-5 fw-bold"><i class="bi bi-kanban"></i> <?php echo $project_count; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center shadow-sm">
                <div class="stat-title text-muted">Tasks</div>
                <div class="stat-value display-5 fw-bold"><i class="bi bi-list-task"></i> <?php echo $task_count; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card text-center shadow-sm">
                <div class="stat-title text-muted">Completed</div>
                <div class="stat-value display-5 fw-bold"><i class="bi bi-check-circle"></i> <?php echo $completed_count; ?></div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-bold">Recent Activity</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activity_logs as $log): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($log['username']); ?></span>
                                <span class="text-muted">&mdash; <?php echo htmlspecialchars($log['action']); ?></span><br>
                                <span class="text-secondary small"><?php echo htmlspecialchars($log['details']); ?></span>
                            </div>
                            <span class="text-muted small"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    Recent Tasks
                    <?php if (can_perform('create_task')): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal"><i class="bi bi-plus-lg"></i> Add Task</button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_tasks as $task): ?>
                        <li class="list-group-item">
                            <span class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></span>
                            <span class="badge bg-<?php echo $task['status'] === 'Completed' ? 'success' : ($task['status'] === 'In Progress' ? 'primary' : 'warning'); ?> ms-2"><?php echo $task['status']; ?></span>
                            <span class="text-muted ms-2">Due: <?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '—'; ?></span><br>
                            <span class="text-secondary small">Assignees: <?php echo htmlspecialchars($task['assignees']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- Add Task Modal (simple version) -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post" action="tasks.php" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addTaskLabel">Add Task</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control">
            </div>
            <!-- You can add more fields as needed -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_task" class="btn btn-primary">Add Task</button>
          </div>
        </form>
      </div>
    </div>
</div>
<!-- Bootstrap JS for modal -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>