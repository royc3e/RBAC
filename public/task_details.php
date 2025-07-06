<?php
require_once __DIR__ . '/../includes/error_log.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comments.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/tasks.php';

$task_id = intval($_GET['task_id'] ?? $_POST['task_id'] ?? 0);
if (!$task_id) {
    echo '<div class="alert alert-danger">Invalid task ID.</div>';
    exit;
}

// Handle new comment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $comment = trim($_POST['comment']);
    if ($user_id && $comment) {
        add_comment($conn, $task_id, $user_id, $comment);
        log_activity($conn, $user_id, $_SESSION['username'] ?? '', 'Commented on Task', 'Task ID: '.$task_id);
    }
    // Return only the comments section for AJAX update
    $comments = get_comments_by_task($conn, $task_id);
    echo render_comments_section($comments);
    exit;
}

// Fetch task details
$stmt = $conn->prepare('SELECT t.*, p.name AS project_name, u.username AS assignee FROM tasks t LEFT JOIN projects p ON t.project_id = p.project_id LEFT JOIN users u ON t.assigned_to = u.user_id WHERE t.task_id = ?');
$stmt->bind_param('i', $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$task) {
    echo '<div class="alert alert-danger">Task not found.</div>';
    exit;
}
$comments = get_comments_by_task($conn, $task_id);
// Fetch recent activity for this task
$stmt = $conn->prepare('SELECT * FROM activity_logs WHERE details LIKE ? ORDER BY created_at DESC LIMIT 5');
$like = '%Task ID: '.$task_id.'%';
$stmt->bind_param('s', $like);
$stmt->execute();
$activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$assignees = get_task_assignees($conn, $task_id);
$watchers = get_task_watchers($conn, $task_id);
$tags = get_task_tags($conn, $task_id);
$is_watching = false;
foreach ($watchers as $w) { if ($w['user_id'] == ($_SESSION['user_id'] ?? 0)) $is_watching = true; }

error_log('DEBUG: task_details.php - USER: ' . ($_SESSION['username'] ?? 'unset') . ' ROLE_ID: ' . ($_SESSION['role_id'] ?? 'unset'));
echo '<!-- ROLE_ID: ' . ($_SESSION['role_id'] ?? 'unset') . ' -->';

function render_comments_section($comments) {
    ob_start();
    echo '<div id="commentsSection">';
    echo '<h6>Comments</h6>';
    if (empty($comments)) {
        echo '<div class="text-muted">No comments yet.</div>';
    } else {
        echo '<ul class="list-group mb-3">';
        foreach ($comments as $c) {
            $initial = strtoupper(substr($c['username'] ?? 'U', 0, 1));
            echo '<li class="list-group-item d-flex align-items-start gap-2">';
            echo '<span class="avatar-circle" style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;background:#eee;border-radius:50%;font-weight:bold;font-size:1.1em;">'.htmlspecialchars($initial).'</span>';
            echo '<div><strong>'.htmlspecialchars($c['username'] ?? 'User').':</strong> '.nl2br(htmlspecialchars($c['comment'])).'<br><small class="text-muted">'.date('M j, Y H:i', strtotime($c['created_at'])).'</small></div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    if (function_exists('can_perform') && can_perform('add_comment')) {
        echo '<div class="input-group mb-2">';
        echo '<input type="text" class="form-control" id="newComment" placeholder="Add a comment...">';
        echo '<button class="btn btn-primary" onclick="postComment('.intval($_GET['task_id'] ?? $_POST['task_id'] ?? 0).')">Post</button>';
        echo '</div>';
    }
    echo '</div>';
    return ob_get_clean();
}

function render_threaded_comments($comments, $level = 0) {
    foreach ($comments as $c) {
        $initial = strtoupper(substr($c['username'] ?? 'U', 0, 1));
        echo '<div class="d-flex mb-2" style="margin-left:'.($level*32).'px">';
        echo '<span class="avatar-circle me-2">'.htmlspecialchars($initial).'</span>';
        echo '<div class="flex-grow-1">';
        echo '<strong>'.htmlspecialchars($c['username'] ?? 'User').':</strong> '.nl2br(htmlspecialchars($c['comment']));
        echo '<br><small class="text-muted">'.date('M j, Y H:i', strtotime($c['created_at'])).'</small>';
        echo '<button class="btn btn-link btn-sm p-0 ms-2 reply-btn" data-comment-id="'.$c['comment_id'].'">Reply</button>';
        // Reply form placeholder
        echo '<div class="reply-form mt-1" id="reply-form-'.$c['comment_id'].'" style="display:none;">';
        echo '<input type="text" class="form-control form-control-sm mb-1 reply-input" placeholder="Write a reply...">';
        echo '<button class="btn btn-primary btn-sm post-reply-btn" data-parent-id="'.$c['comment_id'].'">Post</button>';
        echo '</div>';
        if (!empty($c['children'])) render_threaded_comments($c['children'], $level+1);
        echo '</div></div>';
    }
}
?>
<div class="container-fluid p-0">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <div class="flex-grow-1">
                    <h4 class="card-title mb-1"><?php echo htmlspecialchars($task['title']); ?></h4>
                    <div class="text-muted mb-1" style="font-size:1.05em;">
                        <span class="me-3"><i class="bi bi-folder"></i> <strong><?php echo htmlspecialchars($task['project_name'] ?? '—'); ?></strong></span>
                        <span class="me-3"><i class="bi bi-person"></i> <strong><?php echo implode(', ', array_map(fn($a) => htmlspecialchars($a['username']), $assignees)); ?></strong></span>
                        <span class="me-3"><i class="bi bi-calendar"></i> <strong><?php echo $task['due_date'] ? date('F j, Y', strtotime($task['due_date'])) : '—'; ?></strong></span>
                        <span class="me-3"><i class="bi bi-flag"></i> <strong><?php echo htmlspecialchars($task['priority']); ?></strong></span>
                        <?php if ($tags): ?><span class="me-3"><i class="bi bi-tags"></i> <?php echo implode(', ', array_map('htmlspecialchars', $tags)); ?></span><?php endif; ?>
                        <span class="badge bg-<?php
                            echo $task['status'] === 'Completed' ? 'success' :
                                 ($task['status'] === 'In Progress' ? 'primary' : 'warning');
                        ?> ms-2"><?php echo $task['status']; ?></span>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <div class="fw-semibold mb-1">Description</div>
                <div class="bg-light rounded p-3" style="min-height:48px;white-space:pre-line;"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
            </div>
            <div class="mb-4">
                <div class="fw-semibold mb-2"></div>
                <?php echo render_comments_section($comments); ?>
            </div>
            <div class="mb-2">
                <button class="btn btn-sm btn-outline-<?php echo $is_watching ? 'secondary' : 'primary'; ?>" id="watchTaskBtn"><?php echo $is_watching ? 'Unwatch' : 'Watch'; ?> Task</button>
                <span class="ms-2 text-muted">Watchers: <?php echo count($watchers); ?></span>
            </div>
        </div>
    </div>
</div>
<style>
    .avatar-circle {
        width: 36px !important;
        height: 36px !important;
        background: #f0f2f5 !important;
        color: #495057;
        border: 2px solid #dee2e6;
        font-size: 1.1em;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    #commentsSection ul.list-group {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 8px 0;
    }
    #commentsSection .list-group-item {
        background: transparent;
        border: none;
        border-bottom: 1px solid #f0f0f0;
        padding-left: 0;
        padding-right: 0;
    }
    #commentsSection .list-group-item:last-child {
        border-bottom: none;
    }
    #commentsSection .input-group {
        margin-top: 8px;
    }
</style>
<script>
$(function() {
    // Reply button toggles reply form
    $(document).on('click', '.reply-btn', function() {
        var cid = $(this).data('comment-id');
        $('#reply-form-'+cid).toggle();
    });
    // Post reply
    $(document).on('click', '.post-reply-btn', function() {
        var parentId = $(this).data('parent-id');
        var input = $('#reply-form-'+parentId+' .reply-input');
        var comment = input.val();
        if (!comment.trim()) return;
        $.post('task_details.php', {task_id: <?php echo $task_id; ?>, comment: comment, parent_id: parentId}, function(data) {
            $('#commentsSection').html(data);
        });
    });
    // Watch/Unwatch
    $('#watchTaskBtn').on('click', function() {
        var action = $(this).text().trim().toLowerCase().includes('unwatch') ? 'unwatch' : 'watch';
        $.post('task_details.php', {task_id: <?php echo $task_id; ?>, watch_action: action}, function(data) {
            $('#taskDetailsContent').html(data);
        });
    });
});
</script> 