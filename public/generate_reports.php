<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
if (!can_perform('generate_reports')) {
    echo '<div class="alert alert-danger">Access denied. You do not have permission to generate reports.</div>';
    exit;
}
include '../partials/header.php';
include '../partials/sidebar.php';

// --- FILTERS ---
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';

$where = [];
$params = [];
$types = '';
$where[] = 'created_at BETWEEN ? AND ?';
$params[] = $date_from . ' 00:00:00';
$params[] = $date_to . ' 23:59:59';
$types .= 'ss';
if ($action_filter) {
    $where[] = 'action = ?';
    $params[] = $action_filter;
    $types .= 's';
}
if ($user_filter) {
    $where[] = 'username = ?';
    $params[] = $user_filter;
    $types .= 's';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- SUMMARY CARDS ---
$user_count = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$project_count = $conn->query("SELECT COUNT(*) as cnt FROM projects")->fetch_assoc()['cnt'];
$task_count = $conn->query("SELECT COUNT(*) as cnt FROM tasks")->fetch_assoc()['cnt'];
$completed_count = $conn->query("SELECT COUNT(*) as cnt FROM tasks WHERE status = 'Completed'")->fetch_assoc()['cnt'];
// Most Active User (use prepared statement)
$most_active_user = null;
$most_active_user_sql = "SELECT username, COUNT(*) as cnt FROM activity_logs $where_sql GROUP BY username ORDER BY cnt DESC LIMIT 1";
$most_active_user_stmt = $conn->prepare($most_active_user_sql);
if ($params) $most_active_user_stmt->bind_param($types, ...$params);
$most_active_user_stmt->execute();
$most_active_user_result = $most_active_user_stmt->get_result();
if ($most_active_user_result && $most_active_user_result->num_rows) {
    $most_active_user = $most_active_user_result->fetch_assoc();
}
$most_active_user_stmt->close();

// --- FILTER OPTIONS ---
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
$users = $conn->query("SELECT DISTINCT username FROM activity_logs ORDER BY username ASC");

// --- ACTIVITY LOG SUMMARY ---
$sql = "SELECT action, COUNT(*) as count, MIN(created_at) as first_date, MAX(created_at) as last_date FROM activity_logs $where_sql GROUP BY action ORDER BY count DESC, action ASC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$summary = $stmt->get_result();

// --- EXPORT CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_log_summary.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Action', 'Count', 'First Occurrence', 'Last Occurrence']);
    while ($row = $summary->fetch_assoc()) {
        fputcsv($out, [$row['action'], $row['count'], $row['first_date'], $row['last_date']]);
    }
    fclose($out);
    exit;
}
$last_updated = date('F j, Y, H:i:s');
?>
<div class="main-content">
    <div class="container report-summary">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0">System Report</h2>
            <div class="d-flex gap-2 align-items-center">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn btn-outline-success no-print"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
                <button class="btn btn-primary no-print" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                <button class="btn btn-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
        <form class="row g-2 mb-4 no-print" method="get">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All</option>
                    <?php while ($a = $actions->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php if($action_filter===$a['action'])echo 'selected';?>><?php echo htmlspecialchars($a['action']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">User</label>
                <select name="user" class="form-select">
                    <option value="">All</option>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($u['username']); ?>" <?php if($user_filter===$u['username'])echo 'selected';?>><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-primary">Apply Filters</button>
            </div>
        </form>
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card stat-card text-center shadow-sm">
                    <div class="stat-title text-muted">Users</div>
                    <div class="stat-value display-6 fw-bold"><i class="bi bi-people"></i> <?php echo $user_count; ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card text-center shadow-sm">
                    <div class="stat-title text-muted">Projects</div>
                    <div class="stat-value display-6 fw-bold"><i class="bi bi-kanban"></i> <?php echo $project_count; ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card text-center shadow-sm">
                    <div class="stat-title text-muted">Tasks</div>
                    <div class="stat-value display-6 fw-bold"><i class="bi bi-list-task"></i> <?php echo $task_count; ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card text-center shadow-sm">
                    <div class="stat-title text-muted">Completed</div>
                    <div class="stat-value display-6 fw-bold"><i class="bi bi-check-circle"></i> <?php echo $completed_count; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card text-center shadow-sm">
                    <div class="stat-title text-muted">Most Active User</div>
                    <div class="stat-value display-6 fw-bold"><i class="bi bi-person-badge"></i> <?php echo $most_active_user ? htmlspecialchars($most_active_user['username']) . ' (' . $most_active_user['cnt'] . ' actions)' : '—'; ?></div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-0">Activity Log Summary</h4>
            <span class="text-muted small">Last updated: <?php echo $last_updated; ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle activity-log-summary-table">
                <thead class="table-light">
                    <tr>
                        <th>Action</th>
                        <th>Count</th>
                        <th>First Occurrence</th>
                        <th>Last Occurrence</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt->data_seek(0); // rewind result set for table after CSV export
                if ($summary->num_rows > 0):
                    while ($row = $summary->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                            <td><?php echo $row['count']; ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['first_date']))); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['last_date']))); ?></td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No activity logs found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
    @media print {
        .no-print, .sidebar, .sidebar + .main-content .topbar { display: none !important; }
        body { background: #fff !important; }
        .main-content { margin-left: 0 !important; }
        .activity-log-summary-table th, .activity-log-summary-table td { font-size: 12px !important; }
    }
    .main-content {
        margin-left: 140px; /* Adjust to match sidebar width */
        min-height: 100vh;
        background: #f8f9fb;
        padding: 32px 0 32px 0;
        transition: margin-left 0.2s;
    }
    .report-summary {
        max-width: 1200px;
        margin: 0 auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.04);
        padding: 32px 32px 24px 32px;
    }
    .activity-log-summary-table th, .activity-log-summary-table td { vertical-align: top; }
    .stat-card { border-radius: 10px; }
    .stat-title { font-size: 1em; margin-bottom: 0.5em; }
    .stat-value { font-size: 1.5em; }
    @media (max-width: 991px) {
        .main-content { margin-left: 0; padding: 16px 0; }
        .report-summary { padding: 16px 4px; }
    }
</style> 