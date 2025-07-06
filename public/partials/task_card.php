<?php
// expects $task to be set
require_once __DIR__ . '/../includes/auth.php';
$is_admin = isAdmin();
$is_project_manager = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3);
$is_assignee = ($task['assigned_to'] == ($_SESSION['user_id'] ?? 0));
$can_edit = false;
$can_delete = false;
if ($is_admin || $is_project_manager) {
    $can_edit = true;
    $can_delete = true;
} elseif ($is_assignee) {
    $can_delete = true;
}
?>
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
            <span>Assignee: <strong><?php echo htmlspecialchars($task['assignee'] ?? '—'); ?></strong></span>
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
            <?php if ($can_edit): ?>
                <button class="btn btn-sm btn-outline-secondary flex-fill" data-bs-toggle="modal" data-bs-target="#editTaskModal<?php echo $task['task_id']; ?>">Edit</button>
            <?php endif; ?>
            <?php if ($can_delete): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                    <button type="submit" name="delete_task" class="btn btn-sm btn-outline-danger flex-fill">Delete</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($is_assignee): ?>
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
    </div>
</div> 