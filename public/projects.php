<?php
require_once __DIR__ . '/../includes/error_log.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<?php

// --- Role check: Only Admin (1) and Project Manager (3) can manage projects ---
$can_manage = in_array($_SESSION['role_id'] ?? 0, [1, 3]);

// --- Handle Add/Edit/Delete Actions ---
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    // Restrict Create Project (backend)
    if (isset($_POST['add_project'])) {
        if (!can_perform('create_project')) {
            $_SESSION['project_message'] = 'You do not have permission to create projects.';
            $_SESSION['project_message_type'] = 'danger';
            header('Location: projects.php');
            exit;
        }
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $end_date = $_POST['end_date'] ?? null;
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        if ($name === '') {
            $errors[] = 'Project name is required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO projects (name, description, status, start_date, end_date, is_private, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssi', $name, $desc, $status, $start_date, $end_date, $is_private, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $success = 'Project added successfully!';
            } else {
                $errors[] = 'Failed to add project.';
            }
            $stmt->close();
        }
    }
    // Restrict Edit Project (backend)
    if (isset($_POST['edit_project'])) {
        if (!can_perform('edit_project')) {
            $_SESSION['project_message'] = 'You do not have permission to edit projects.';
            $_SESSION['project_message_type'] = 'danger';
            header('Location: projects.php');
            exit;
        }
        $id = intval($_POST['project_id']);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        if ($name === '') {
            $errors[] = 'Project name is required.';
        } else {
            $stmt = $conn->prepare('UPDATE projects SET name=?, description=?, status=?, start_date=?, end_date=?, is_private=? WHERE project_id=?');
            $stmt->bind_param('ssssssi', $name, $desc, $status, $start_date, $end_date, $is_private, $id);
            if ($stmt->execute()) {
                $success = 'Project updated successfully!';
            } else {
                $errors[] = 'Failed to update project.';
            }
            $stmt->close();
        }
    }
    // Restrict Delete Project (backend)
    if (isset($_POST['delete_project'])) {
        if (!can_perform('delete_project')) {
            $_SESSION['project_message'] = 'You do not have permission to delete projects.';
            $_SESSION['project_message_type'] = 'danger';
            header('Location: projects.php');
            exit;
        }
        $id = intval($_POST['project_id']);
        $stmt = $conn->prepare('DELETE FROM projects WHERE project_id=?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $success = 'Project deleted.';
        } else {
            $errors[] = 'Failed to delete project.';
        }
        $stmt->close();
    }
    // Add Member
    if (isset($_POST['add_member'])) {
        $project_id = intval($_POST['project_id']);
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'] ?? 'Contributor';
        $stmt = $conn->prepare('INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $project_id, $user_id, $role);
        if ($stmt->execute()) {
            $success = 'Member added.';
        } else {
            $errors[] = 'Failed to add member.';
        }
        $stmt->close();
    }
    // Update Member Role
    if (isset($_POST['update_member_role'])) {
        $project_id = intval($_POST['project_id']);
        $user_id = intval($_POST['user_id']);
        $role = $_POST['role'] ?? 'Contributor';
        $stmt = $conn->prepare('UPDATE project_members SET role=? WHERE project_id=? AND user_id=?');
        $stmt->bind_param('sii', $role, $project_id, $user_id);
        if ($stmt->execute()) {
            $success = 'Member role updated.';
        } else {
            $errors[] = 'Failed to update role.';
        }
        $stmt->close();
    }
    // Remove Member
    if (isset($_POST['remove_member'])) {
        $project_id = intval($_POST['project_id']);
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare('DELETE FROM project_members WHERE project_id=? AND user_id=?');
        $stmt->bind_param('ii', $project_id, $user_id);
        if ($stmt->execute()) {
            $success = 'Member removed.';
        } else {
            $errors[] = 'Failed to remove member.';
        }
        $stmt->close();
    }
}

// --- Fetch Projects with new fields ---
$sql = 'SELECT p.*, u.username AS creator FROM projects p LEFT JOIN users u ON p.created_by = u.user_id ORDER BY p.created_at DESC';
$res = $conn->query($sql);
$projects = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// --- Fetch project members for all projects ---
$project_members = [];
if (count($projects) > 0) {
    $ids = array_column($projects, 'project_id');
    $in = implode(',', array_map('intval', $ids));
    $sql_members = "SELECT pm.project_id, pm.user_id, u.username, pm.role FROM project_members pm JOIN users u ON pm.user_id = u.user_id WHERE pm.project_id IN ($in) ORDER BY pm.role, u.username";
    $res_members = $conn->query($sql_members);
    if ($res_members) {
        while ($row = $res_members->fetch_assoc()) {
            $project_members[$row['project_id']][] = $row;
        }
    }
}

// --- Fetch all users for add member dropdown ---
$all_users = [];
$res_users = $conn->query('SELECT user_id, username FROM users WHERE is_active=1');
if ($res_users) {
    while ($row = $res_users->fetch_assoc()) {
        $all_users[$row['user_id']] = $row['username'];
    }
}

// --- Fetch task counts for each project ---
$task_counts = [];
if (count($projects) > 0) {
    $ids = array_column($projects, 'project_id');
    $in = implode(',', array_map('intval', $ids));
    $sql_tasks = "SELECT project_id, COUNT(*) as total, SUM(status='Completed') as completed FROM tasks WHERE project_id IN ($in) GROUP BY project_id";
    $res_tasks = $conn->query($sql_tasks);
    if ($res_tasks) {
        while ($row = $res_tasks->fetch_assoc()) {
            $task_counts[$row['project_id']] = $row;
        }
    }
}
?>
<div class="main-content">
    <div class="topbar">Projects</div>
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Projects</h2>
            <?php if (can_perform('create_project')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">+ Add Project</button>
            <?php endif; ?>
        </div>
        <!-- Search/Filter Bar (UI only for now) -->
        <form class="row g-2 mb-3" method="get" autocomplete="off">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control" placeholder="Start date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control" placeholder="End date">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" type="submit">Filter</button>
            </div>
        </form>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <!-- Projects Grid -->
        <div class="projects-grid row g-4">
        <?php if (count($projects) === 0): ?>
            <div class="col-12 text-center text-muted">No projects found.</div>
        <?php else: ?>
            <?php foreach ($projects as $proj): ?>
                <?php
                $tasks = $task_counts[$proj['project_id']] ?? ['total'=>0,'completed'=>0];
                $total = (int)$tasks['total'];
                $completed = (int)$tasks['completed'];
                $progress = $total > 0 ? round(($completed/$total)*100) : 0;
                $start = $proj['start_date'] ? date('F j, Y', strtotime($proj['start_date'])) : '—';
                $end = $proj['end_date'] ? date('F j, Y', strtotime($proj['end_date'])) : '—';
                $status = $proj['status'] ?? 'Active';
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="project-box p-4 h-100 d-flex flex-column justify-content-between shadow-sm rounded bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($proj['name']); ?></h5>
                            <span class="badge bg-<?php
                                echo $status === 'Active' ? 'success' :
                                     ($status === 'On Hold' ? 'warning' :
                                     ($status === 'Completed' ? 'primary' : 'secondary'));
                            ?>"><?php echo $status; ?></span>
                        </div>
                        <div class="mb-2 text-muted" style="font-size:0.98em;">
                            <span>Start: <strong><?php echo $start; ?></strong></span><br>
                            <span>End: <strong><?php echo $end; ?></strong></span>
                        </div>
                        <div class="mb-2">
                            <span class="me-2"><strong>Members:</strong> <button class="btn btn-sm btn-outline-info px-2 py-0" data-bs-toggle="modal" data-bs-target="#membersModal<?php echo $proj['project_id']; ?>">View (<?php echo isset($project_members[$proj['project_id']]) ? count($project_members[$proj['project_id']]) : 0; ?>)</button></span>
                            <span class="me-2"><strong>Tasks:</strong> <span class="text-success fw-bold"><?php echo $completed; ?></span> / <?php echo $total; ?></span>
                        </div>
                        <div class="mb-2">
                            <div class="progress" style="height:16px;">
                              <div class="progress-bar <?php
                                if ($progress==100) echo 'bg-success';
                                else if ($progress>=50) echo 'bg-primary';
                                else echo 'bg-warning';
                              ?>" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $progress; ?>%
                              </div>
                            </div>
                        </div>
                        <div class="mb-2"><a href="tasks.php?project_id=<?php echo $proj['project_id']; ?>" class="btn btn-link btn-sm p-0">View Tasks</a></div>
                        <div class="d-flex justify-content-between align-items-center mt-2" style="font-size:0.97em;">
                            <span class="text-muted">By <strong><?php echo htmlspecialchars($proj['creator'] ?? '—'); ?></strong></span>
                            <span class="text-muted"><?php echo date('F j, Y', strtotime($proj['created_at'])); ?></span>
                        </div>
                        <?php if (can_perform('edit_project')): ?>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-outline-secondary flex-fill" data-bs-toggle="modal" data-bs-target="#editProjectModal<?php echo $proj['project_id']; ?>">Edit</button>
                        </div>
                        <?php endif; ?>
                        <?php if (can_perform('delete_project')): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                            <button type="submit" name="delete_project" class="btn btn-sm btn-outline-danger flex-fill">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
        <!-- Render all modals after the grid -->
        <?php if (count($projects) > 0): ?>
            <?php foreach ($projects as $proj): ?>
                <!-- Members Modal -->
                <div class="modal fade" id="membersModal<?php echo $proj['project_id']; ?>" tabindex="-1" aria-labelledby="membersLabel<?php echo $proj['project_id']; ?>" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="membersLabel<?php echo $proj['project_id']; ?>">Project Members</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <?php if (!empty($project_members[$proj['project_id']])): ?>
                            <ul class="list-group mb-3">
                            <?php foreach ($project_members[$proj['project_id']] as $mem): ?>
                                <?php if (!isset($mem['user_id'])) continue; ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($mem['username']); ?></span>
                                    <div class="d-flex align-items-center">
                                        <?php if ($can_manage): ?>
                                        <form method="post" class="d-flex align-items-center me-2">
                                            <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo isset($mem['user_id']) ? htmlspecialchars($mem['user_id']) : ''; ?>">
                                            <select name="role" class="form-select form-select-sm me-1">
                                                <option value="Manager" <?php if($mem['role']==='Manager') echo 'selected'; ?>>Manager</option>
                                                <option value="Contributor" <?php if($mem['role']==='Contributor') echo 'selected'; ?>>Contributor</option>
                                                <option value="Viewer" <?php if($mem['role']==='Viewer') echo 'selected'; ?>>Viewer</option>
                                            </select>
                                            <button type="submit" name="update_member_role" class="btn btn-sm btn-outline-primary">Update</button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo isset($mem['user_id']) ? htmlspecialchars($mem['user_id']) : ''; ?>">
                                            <button type="submit" name="remove_member" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                        <?php else: ?>
                                        <span class="badge bg-secondary ms-2"><?php echo $mem['role']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-muted mb-3">No members assigned.</div>
                        <?php endif; ?>
                        <?php if ($can_manage): ?>
                        <form method="post" class="d-flex align-items-end gap-2">
                            <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                            <div class="flex-grow-1">
                                <label class="form-label mb-1">Add User</label>
                                <select name="user_id" class="form-select form-select-sm" required>
                                    <option value="">Select user...</option>
                                    <?php
                                    $assigned_ids = array_map(function($m){return $m['user_id'];}, $project_members[$proj['project_id']] ?? []);
                                    foreach ($all_users as $uid => $uname):
                                        if (in_array($uid, $assigned_ids)) continue;
                                    ?>
                                        <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($uname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">Role</label>
                                <select name="role" class="form-select form-select-sm">
                                    <option value="Manager">Manager</option>
                                    <option value="Contributor" selected>Contributor</option>
                                    <option value="Viewer">Viewer</option>
                                </select>
                            </div>
                            <button type="submit" name="add_member" class="btn btn-sm btn-success">Add</button>
                        </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Edit Modal -->
                <div class="modal fade" id="editProjectModal<?php echo $proj['project_id']; ?>" tabindex="-1" aria-labelledby="editProjectLabel<?php echo $proj['project_id']; ?>" aria-hidden="true">
                  <div class="modal-dialog">
                    <form method="post" class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editProjectLabel<?php echo $proj['project_id']; ?>">Edit Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Project Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($proj['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($proj['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php if($proj['status']==='Active') echo 'selected'; ?>>Active</option>
                                <option value="On Hold" <?php if($proj['status']==='On Hold') echo 'selected'; ?>>On Hold</option>
                                <option value="Completed" <?php if($proj['status']==='Completed') echo 'selected'; ?>>Completed</option>
                                <option value="Cancelled" <?php if($proj['status']==='Cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($proj['start_date']); ?>">
                            </div>
                            <div class="col">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($proj['end_date']); ?>">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_private" id="isPrivate<?php echo $proj['project_id']; ?>" value="1" <?php if($proj['is_private']) echo 'checked'; ?>>
                            <label class="form-check-label" for="isPrivate<?php echo $proj['project_id']; ?>">Private Project</label>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_project" class="btn btn-primary">Save Changes</button>
                      </div>
                    </form>
                  </div>
                </div>
                <!-- Delete Modal -->
                <div class="modal fade" id="deleteProjectModal<?php echo $proj['project_id']; ?>" tabindex="-1" aria-labelledby="deleteProjectLabel<?php echo $proj['project_id']; ?>" aria-hidden="true">
                  <div class="modal-dialog">
                    <form method="post" class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="deleteProjectLabel<?php echo $proj['project_id']; ?>">Delete Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo $proj['project_id']; ?>">
                        <p>Are you sure you want to delete the project <strong><?php echo htmlspecialchars($proj['name']); ?></strong>?</p>
                        <ul class="list-group mb-2">
                          <li class="list-group-item"><strong>Status:</strong> <?php echo htmlspecialchars($proj['status']); ?></li>
                          <li class="list-group-item"><strong>Start:</strong> <?php echo $proj['start_date'] ? htmlspecialchars($proj['start_date']) : '—'; ?></li>
                          <li class="list-group-item"><strong>End:</strong> <?php echo $proj['end_date'] ? htmlspecialchars($proj['end_date']) : '—'; ?></li>
                          <li class="list-group-item"><strong>Members:</strong> <?php echo isset($project_members[$proj['project_id']]) ? count($project_members[$proj['project_id']]) : 0; ?></li>
                        </ul>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_project" class="btn btn-danger">Delete</button>
                      </div>
                    </form>
                  </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addProjectLabel">Add Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Project Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Active" selected>Active</option>
                <option value="On Hold">On Hold</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>
        <div class="row mb-3">
            <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control">
            </div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_private" id="isPrivateAdd" value="1">
            <label class="form-check-label" for="isPrivateAdd">Private Project</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_project" class="btn btn-primary">Add Project</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 