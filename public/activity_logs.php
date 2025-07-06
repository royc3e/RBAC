<?php
require_once __DIR__ . '/../includes/error_log.php';
require_once '../includes/auth.php';
if (!can_perform('view_activity_logs')) {
    echo '<div class="alert alert-danger">Access denied. You do not have permission to view activity logs.</div>';
    exit;
}
require_once '../config/db.php';
include '../partials/header.php';
include '../partials/sidebar.php';
?>
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<?php
// Handle filters/search
$where = [];
$params = [];
$types = '';
if (!empty($_GET['search'])) {
    $where[] = "(username LIKE ? OR action LIKE ? OR details LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}
if (!empty($_GET['from'])) {
    $where[] = "created_at >= ?";
    $params[] = $_GET['from'] . ' 00:00:00';
    $types .= 's';
}
if (!empty($_GET['to'])) {
    $where[] = "created_at <= ?";
    $params[] = $_GET['to'] . ' 23:59:59';
    $types .= 's';
}
$sql = "SELECT log_id, user_id, username, action, details, created_at FROM activity_logs";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC LIMIT 200";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="main-content">
    <div class="topbar">Activity Logs</div>
    <div class="card" style="max-width: 1100px;">
        <h2 class="mb-4">Recent Activity</h2>
        <form class="row g-3 mb-4" method="get">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by user, action, or details" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($log['details'], 0, 40, '...')); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#logModal<?php echo $log['log_id']; ?>">View Details</button>
                                <!-- Modal -->
                                <div class="modal fade" id="logModal<?php echo $log['log_id']; ?>" tabindex="-1" aria-labelledby="logModalLabel<?php echo $log['log_id']; ?>" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="logModalLabel<?php echo $log['log_id']; ?>">Activity Log Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body">
                                        <p><strong>Date/Time:</strong> <?php echo htmlspecialchars($log['created_at']); ?></p>
                                        <p><strong>User:</strong> <?php echo htmlspecialchars($log['username']); ?> (ID: <?php echo $log['user_id']; ?>)</p>
                                        <p><strong>Action:</strong> <?php echo htmlspecialchars($log['action']); ?></p>
                                        <p><strong>Details:</strong><br><?php echo nl2br(htmlspecialchars($log['details'])); ?></p>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Bootstrap JS for modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 