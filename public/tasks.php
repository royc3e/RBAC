<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../includes/error_log.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/tasks.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<?php
// --- Fetch projects for filter and dropdown ---
$projects = [];
$res_proj = $conn->query('SELECT project_id, name FROM projects ORDER BY name');
if ($res_proj) {
    while ($row = $res_proj->fetch_assoc()) {
        $projects[$row['project_id']] = $row['name'];
    }
}
// --- Fetch users for assignee dropdown ---
$users = [];
$res_users = $conn->query('SELECT user_id, username FROM users WHERE is_active=1 ORDER BY username');
if ($res_users) {
    while ($row = $res_users->fetch_assoc()) {
        $users[$row['user_id']] = $row['username'];
    }
}
// --- Fetch tasks with project and assignee info ---
$sql = 'SELECT t.*, p.name AS project_name, u.username AS assignee FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.project_id 
        LEFT JOIN users u ON t.assigned_to = u.user_id 
        ORDER BY t.due_date ASC, t.status DESC';
$res = $conn->query($sql);
$tasks = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// Fetch all assignees for each task
$task_assignees_map = [];
foreach ($tasks as $task) {
    $task_assignees_map[$task['task_id']] = get_task_assignees($conn, $task['task_id']);
}

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (!can_perform('create_task')) {
        $_SESSION['task_message'] = 'You do not have permission to create tasks.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $project_id = intval($_POST['project_id'] ?? 0);
    $assignees = isset($_POST['assignees']) ? array_map('intval', $_POST['assignees']) : [];
    $status = $_POST['status'] ?? 'Pending';
    $due_date = $_POST['due_date'] ?? null;
    if ($title === '' || !$project_id || empty($assignees)) {
        $errors[] = 'Title, Project, and at least one Assignee are required.';
        error_log('DEBUG: Add Task validation failed. Title: ' . $title . ', Project: ' . $project_id . ', Assignees: ' . print_r($assignees, true));
    } else {
        $primary_assignee = $assignees[0];
        $stmt = $conn->prepare('INSERT INTO tasks (title, description, project_id, assigned_to, status, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssisssi', $title, $desc, $project_id, $primary_assignee, $status, $due_date, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $task_id = $conn->insert_id;
            set_task_assignees($conn, $task_id, $assignees);
            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Add Task', 'Added task ID: ' . $task_id);
            $_SESSION['task_message'] = 'Task added successfully!';
            $_SESSION['task_message_type'] = 'success';
            header('Location: tasks.php');
            exit;
        } else {
            $_SESSION['task_message'] = 'Failed to add task.';
            $_SESSION['task_message_type'] = 'danger';
            header('Location: tasks.php');
            exit;
        }
        $stmt->close();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    if (!can_perform('edit_task')) {
        $_SESSION['task_message'] = 'You do not have permission to edit tasks.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    $task_id = intval($_POST['task_id']);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $project_id = intval($_POST['project_id'] ?? 0);
    $assignees = isset($_POST['assignees']) ? array_map('intval', $_POST['assignees']) : [];
    $status = $_POST['status'] ?? 'Pending';
    $due_date = $_POST['due_date'] ?? null;
    if ($title === '' || !$project_id || empty($assignees)) {
        $errors[] = 'Title, Project, and at least one Assignee are required.';
    } else {
        $primary_assignee = $assignees[0];
        $stmt = $conn->prepare('UPDATE tasks SET title=?, description=?, project_id=?, assigned_to=?, status=?, due_date=? WHERE task_id=?');
        $stmt->bind_param('ssisssi', $title, $desc, $project_id, $primary_assignee, $status, $due_date, $task_id);
        if ($stmt->execute()) {
            set_task_assignees($conn, $task_id, $assignees);
            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Edit Task', 'Edited task ID: ' . $task_id);
            $_SESSION['task_message'] = 'Task updated successfully!';
            $_SESSION['task_message_type'] = 'success';
            header('Location: tasks.php');
            exit;
        } else {
            $_SESSION['task_message'] = 'Failed to update task.';
            $_SESSION['task_message_type'] = 'danger';
            header('Location: tasks.php');
            exit;
        }
        $stmt->close();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    if (!can_perform('delete_task')) {
        $_SESSION['task_message'] = 'You do not have permission to delete tasks.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    $task_id = intval($_POST['task_id']);
    // First, delete related comments
    $stmt_comments = $conn->prepare('DELETE FROM comments WHERE task_id=?');
    $stmt_comments->bind_param('i', $task_id);
    $stmt_comments->execute();
    $stmt_comments->close();
    // Then, delete related task_assignees
    $stmt_assignees = $conn->prepare('DELETE FROM task_assignees WHERE task_id=?');
    $stmt_assignees->bind_param('i', $task_id);
    $stmt_assignees->execute();
    $stmt_assignees->close();
    // Now, delete the task itself
    $stmt = $conn->prepare('DELETE FROM tasks WHERE task_id=?');
    $stmt->bind_param('i', $task_id);
    if ($stmt->execute()) {
        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Delete Task', 'Deleted task ID: ' . $task_id);
        $_SESSION['task_message'] = 'Task deleted successfully!';
        $_SESSION['task_message_type'] = 'success';
        header('Location: tasks.php');
        exit;
    } else {
        $_SESSION['task_message'] = 'Failed to delete task.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['task_id']) && !isset($_POST['edit_task'])) {
    $task_id = intval($_POST['task_id']);
    $new_status = $_POST['status'];
    // Only allow assignees to update status (optional, or allow all)
    $stmt = $conn->prepare('UPDATE tasks SET status=? WHERE task_id=?');
    $stmt->bind_param('si', $new_status, $task_id);
    if ($stmt->execute()) {
        // Fetch the task name
        $task_name = '';
        $stmt_task_name = $conn->prepare('SELECT title FROM tasks WHERE task_id=?');
        $stmt_task_name->bind_param('i', $task_id);
        $stmt_task_name->execute();
        $stmt_task_name->bind_result($task_name_db);
        if ($stmt_task_name->fetch()) {
            $task_name = $task_name_db;
        }
        $stmt_task_name->close();
        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Change Task Status', 'Changed status of task "' . $task_name . '" to ' . $new_status);
        $_SESSION['task_message'] = 'Task status updated successfully!';
        $_SESSION['task_message_type'] = 'success';
    } else {
        $_SESSION['task_message'] = 'Failed to update task status.';
        $_SESSION['task_message_type'] = 'danger';
    }
    $stmt->close();
    header('Location: tasks.php');
    exit;
}

// Group tasks by project
$tasks_by_project = [];
foreach ($tasks as $task) {
    $tasks_by_project[$task['project_id']][] = $task;
}

// Recently Added Tasks by project (top 5 per project)
$recent_tasks_by_project = [];
foreach ($tasks as $task) {
    $recent_tasks_by_project[$task['project_id']][] = $task;
}
foreach ($recent_tasks_by_project as $pid => &$arr) {
    usort($arr, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $arr = array_slice($arr, 0, 5);
}
unset($arr);

// Group tasks by status (using tasks.status field only)
$tasks_by_status = [
    'Pending' => [],
    'In Progress' => [],
    'Completed' => []
];
foreach ($tasks as $task) {
    $tasks_by_status[$task['status']][] = $task;
}

// Restrict Assign Task (backend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    if (!can_perform('assign_task')) {
        $_SESSION['task_message'] = 'You do not have permission to assign tasks.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    $task_id = intval($_POST['task_id']);
    $assignees = isset($_POST['assignees']) ? array_map('intval', $_POST['assignees']) : [];
    if (empty($assignees)) {
        $_SESSION['task_message'] = 'Please select at least one assignee.';
        $_SESSION['task_message_type'] = 'danger';
        header('Location: tasks.php');
        exit;
    }
    // Update main assigned_to field to first assignee
    $primary_assignee = $assignees[0];
    $stmt = $conn->prepare('UPDATE tasks SET assigned_to=? WHERE task_id=?');
    $stmt->bind_param('ii', $primary_assignee, $task_id);
    $stmt->execute();
    $stmt->close();
    // Update task_assignees table
    set_task_assignees($conn, $task_id, $assignees);
    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Assign Task', 'Updated assignees for task ID: ' . $task_id);
    $_SESSION['task_message'] = 'Task assignees updated successfully!';
    $_SESSION['task_message_type'] = 'success';
    header('Location: tasks.php');
    exit;
}
?>
<div class="main-content">
<?php if (!empty($_SESSION['task_message'])): ?>
    <div class="task-flash-container">
        <div id="task-flash-message" class="alert alert-<?php echo $_SESSION['task_message_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['task_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['task_message'], $_SESSION['task_message_type']); ?>
<?php endif; ?>
    <div class="topbar">Tasks</div>
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Tasks</h2>
            <?php if (can_perform('create_task')): ?>
            <button class="btn btn-primary" id="openAddTaskModalBtn">+ Add Task</button>
            <?php endif; ?>
        </div>
        <!-- Project Filter Bar -->
        <form class="row g-2 mb-3" method="get" autocomplete="off">
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" placeholder="Search by title or description...">
            </div>
            <div class="col-md-2">
                <select name="project_id" class="form-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $pid => $pname): ?>
                        <option value="<?php echo $pid; ?>" <?php if(isset($_GET['project_id']) && $_GET['project_id']==$pid) echo 'selected'; ?>><?php echo htmlspecialchars($pname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="tags" class="form-control" placeholder="Tags (comma separated)">
            </div>
            <div class="col-md-2">
                <select name="assignee" class="form-select">
                    <option value="">All Assignees</option>
                    <?php foreach ($users as $uid => $uname): ?>
                        <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($uname); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="due_date" class="form-control" placeholder="Due date">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
            </div>
        </form>
        <!-- Tasks Grouped by Status -->
        <?php 
        $status_labels = [
            'Pending' => 'warning',
            'In Progress' => 'primary',
            'Completed' => 'success'
        ];
        ?>
        <?php foreach ($tasks_by_status as $status => $tasks_list): ?>
            <?php if (empty($tasks_list)) continue; ?>
            <div class="mb-4" data-status-group="<?php echo $status; ?>">
                <h4 class="mb-3 text-<?php echo $status_labels[$status]; ?>">
                    <i class="bi bi-list-task"></i> <?php echo htmlspecialchars($status); ?> Tasks
                </h4>
                    <div class="tasks-grid row g-4">
                    <?php foreach ($tasks_list as $task): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="task-box p-4 h-100 d-flex flex-column justify-content-between shadow-sm rounded bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <span class="badge bg-<?php
                                            echo $task['status'] === 'Completed' ? 'success' :
                                                 ($task['status'] === 'In Progress' ? 'primary' : 'warning');
                                        ?>"><?php echo $task['status']; ?></span>
                                    </div>
                                    <div class="mb-2 text-muted" style="font-size:0.98em;">
                                        <span>Project: <strong><?php echo htmlspecialchars($task['project_name'] ?? '—'); ?></strong></span><br>
                                    <span>Assignees: <strong>
                                        <?php
                                        $assignees = $task_assignees_map[$task['task_id']] ?? [];
                                        if ($assignees) {
                                            $names = array_map(function($a) {
                                                return htmlspecialchars($a['username']);
                                            }, $assignees);
                                            echo implode(', ', $names);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </strong></span>
                                    </div>
                                    <div class="mb-2">
                                        <span><strong>Due:</strong> <?php echo $task['due_date'] ? date('F j, Y', strtotime($task['due_date'])) : '—'; ?></span>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                    <div class="mb-2 text-secondary" style="font-size:0.97em; min-height: 38px;">
                                        <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-sm btn-outline-info flex-fill" onclick="openTaskDetails(<?php echo $task['task_id']; ?>)">View</button>
                                        <?php
                                        $can_edit = false;
                                        $can_delete = false;
                                        $is_admin = isAdmin();
                                        $is_project_manager = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3);
                                    $user_id = $_SESSION['user_id'] ?? 0;
                                    $is_assignee = false;
                                    $assignees = $task_assignees_map[$task['task_id']] ?? [];
                                    foreach ($assignees as $a) {
                                        if ($a['user_id'] == $user_id) {
                                            $is_assignee = true;
                                            break;
                                        }
                                    }
                                        if ($is_admin || $is_project_manager) {
                                            $can_edit = true;
                                            $can_delete = true;
                                        }
                                        ?>
                                        <?php if ($can_edit): ?>
                                            <button class="btn btn-sm btn-outline-secondary flex-fill" data-bs-toggle="modal" data-bs-target="#editTaskModal<?php echo $task['task_id']; ?>">Edit</button>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                <button type="submit" name="delete_task" class="btn btn-sm btn-outline-danger flex-fill">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php if (can_perform('assign_task')): ?>
                                        <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#assignTaskModal<?php echo $task['task_id']; ?>">Assign</button>
                                    <?php endif; ?>
                                    </div>
                                    <?php if ($is_assignee): ?>
                                    <?php if (can_perform('mark_task_complete')): ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                            <label class="form-label mb-1">Status:</label>
                                            <select name="status" class="form-select form-select-sm d-inline w-auto" style="min-width:120px;" onchange="this.form.submit()">
                                                <option value="Pending" <?php if($task['status']==='Pending') echo 'selected'; ?>>Pending</option>
                                                <option value="In Progress" <?php if($task['status']==='In Progress') echo 'selected'; ?>>In Progress</option>
                                                <option value="Completed" <?php if($task['status']==='Completed') echo 'selected'; ?>>Completed</option>
                                            </select>
                                            <span class="ms-2 badge bg-<?php
                                                echo $task['status'] === 'Completed' ? 'success' :
                                                     ($task['status'] === 'In Progress' ? 'primary' : 'warning');
                                            ?>"><?php echo $task['status']; ?></span>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <!-- End Tasks Grouped by Status -->
        <!-- Recently Added Tasks Section -->
        <div class="mb-4">
            <h4 class="mb-3 text-success">Recently Added Tasks</h4>
            <div style="max-height: 320px; overflow-y: auto;">
                <?php foreach ($projects as $pid => $pname): ?>
                    <?php if (empty($recent_tasks_by_project[$pid])) continue; ?>
                    <div class="mb-3">
                        <h6 class="mb-2 text-secondary"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($pname); ?></h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_tasks_by_project[$pid] as $task): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center" style="white-space:normal;">
                                    <span style="max-width:60%;overflow-wrap:break-word;"><strong><?php echo htmlspecialchars($task['title']); ?></strong> <span class="text-muted">(<?php echo date('M j, Y', strtotime($task['created_at'])); ?>)</span></span>
                                    <span class="badge bg-<?php
                                        echo $task['status'] === 'Completed' ? 'success' :
                                             ($task['status'] === 'In Progress' ? 'primary' : 'warning');
                                    ?>" style="font-size:1em;"><?php echo $task['status']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- End Recently Added Tasks Section -->
    </div>
</div>
<div id="task-message-area"></div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
<?php elseif ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addTaskLabel">Add Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label fw-bold text-primary">Project <span class="text-danger">*</span></label>
            <select name="project_id" class="form-select form-select-lg mb-2" required>
                <option value="">Select project...</option>
                <?php foreach ($projects as $pid => $pname): ?>
                    <option value="<?php echo $pid; ?>"><?php echo htmlspecialchars($pname); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Assignees</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($users as $uid => $uname): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="assignees[]" value="<?php echo $uid; ?>" id="assigneeAdd<?php echo $uid; ?>">
                        <label class="form-check-label" for="assigneeAdd<?php echo $uid; ?>"><?php echo htmlspecialchars($uname); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <small class="text-muted">Select one or more users.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <option value="Low">Low</option>
                <option value="Medium" selected>Medium</option>
                <option value="High">High</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_task" class="btn btn-primary">Add Task</button>
      </div>
    </form>
  </div>
</div>
<!-- Edit Task Modals -->
<?php foreach ($tasks as $task): ?>
<div class="modal fade" id="editTaskModal<?php echo $task['task_id']; ?>" tabindex="-1" aria-labelledby="editTaskLabel<?php echo $task['task_id']; ?>" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editTaskLabel<?php echo $task['task_id']; ?>">Edit Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($task['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Project</label>
            <select name="project_id" class="form-select" required>
                <option value="">Select project...</option>
                <?php foreach ($projects as $pid => $pname): ?>
                    <option value="<?php echo $pid; ?>" <?php if($task['project_id']==$pid) echo 'selected'; ?>><?php echo htmlspecialchars($pname); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Assignees</label>
            <div class="d-flex flex-wrap gap-2">
                <?php 
                $current_assignees = array_column($task_assignees_map[$task['task_id']] ?? [], 'user_id');
                foreach ($users as $uid => $uname): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="assignees[]" value="<?php echo $uid; ?>" id="assigneeEdit<?php echo $task['task_id']; ?>_<?php echo $uid; ?>" <?php if(in_array($uid, $current_assignees)) echo 'checked'; ?>>
                        <label class="form-check-label" for="assigneeEdit<?php echo $task['task_id']; ?>_<?php echo $uid; ?>"><?php echo htmlspecialchars($uname); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <option value="Low" <?php if($task['priority']==='Low') echo 'selected'; ?>>Low</option>
                <option value="Medium" <?php if($task['priority']==='Medium') echo 'selected'; ?>>Medium</option>
                <option value="High" <?php if($task['priority']==='High') echo 'selected'; ?>>High</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Pending" <?php if($task['status']==='Pending') echo 'selected'; ?>>Pending</option>
                <option value="In Progress" <?php if($task['status']==='In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Completed" <?php if($task['status']==='Completed') echo 'selected'; ?>>Completed</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($task['due_date']); ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="edit_task" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<!-- After the Edit Task Modals, add Assign Task Modals for each task -->
<?php foreach ($tasks as $task): ?>
<div class="modal fade" id="assignTaskModal<?php echo $task['task_id']; ?>" tabindex="-1" aria-labelledby="assignTaskLabel<?php echo $task['task_id']; ?>" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignTaskLabel<?php echo $task['task_id']; ?>">Assign Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
        <div class="mb-3">
            <label class="form-label">Assignees</label>
            <div class="d-flex flex-wrap gap-2">
                <?php 
                $current_assignees = array_column($task_assignees_map[$task['task_id']] ?? [], 'user_id');
                foreach ($users as $uid => $uname): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="assignees[]" value="<?php echo $uid; ?>" id="assigneeAssign<?php echo $task['task_id']; ?>_<?php echo $uid; ?>" <?php if(in_array($uid, $current_assignees)) echo 'checked'; ?>>
                        <label class="form-check-label" for="assigneeAssign<?php echo $task['task_id']; ?>_<?php echo $uid; ?>"><?php echo htmlspecialchars($uname); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <small class="text-muted">Select one or more users.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="assign_task" class="btn btn-primary">Assign</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openTaskDetails(taskId) {
    $('#taskDetailsModal').modal('show');
    $('#taskDetailsContent').html('<div class="text-center p-4"><div class="spinner-border"></div></div>');
    $.get('task_details.php', {task_id: taskId}, function(data) {
        $('#taskDetailsContent').html(data);
    });
}
function postComment(taskId) {
    var comment = $('#newComment').val();
    if (!comment.trim()) return;
    $.post('task_details.php', {task_id: taskId, comment: comment}, function(data) {
        $('#commentsSection').html(data);
        $('#newComment').val('');
    });
}
$(function() {
    // Open Add Task Modal with Bootstrap JS API
    $('#openAddTaskModalBtn').on('click', function(e) {
        e.preventDefault();
        var modalEl = document.getElementById('addTaskModal');
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    });
    // Show message
    function showTaskMessage(msg, type) {
        var html = '<div class="alert alert-'+type+' alert-dismissible fade show" role="alert">'+msg+'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        $('#task-message-area').html(html);
    }
    // Auto-hide flash message after 5 seconds
    setTimeout(function() {
        var msg = document.getElementById('task-flash-message');
        if (msg) {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(msg);
            bsAlert.close();
        }
    }, 5000);
});
</script>
<!-- Task Details Modal -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskDetailsLabel">Task Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="taskDetailsContent">
        <!-- AJAX content here -->
      </div>
    </div>
  </div>
</div>
</body>
</html> 