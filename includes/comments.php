<?php
function get_comments_by_task($conn, $task_id) {
    $stmt = $conn->prepare('SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.user_id WHERE c.task_id = ? ORDER BY c.created_at ASC');
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    // Build threaded tree
    $tree = [];
    $refs = [];
    foreach ($comments as &$c) {
        $c['children'] = [];
        $refs[$c['comment_id']] = &$c;
    }
    foreach ($comments as &$c) {
        if ($c['parent_id']) {
            $refs[$c['parent_id']]['children'][] = &$c;
        } else {
            $tree[] = &$c;
        }
    }
    return $tree;
}

function add_comment($conn, $task_id, $user_id, $comment, $parent_id = null) {
    $stmt = $conn->prepare('INSERT INTO comments (task_id, user_id, comment, parent_id) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iisi', $task_id, $user_id, $comment, $parent_id);
    return $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!can_perform('add_comment')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to add comments.']);
        exit;
    }
    // ... existing add comment logic ...
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment'])) {
    if (!can_perform('edit_comment')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit comments.']);
        exit;
    }
    // ... existing edit comment logic ...
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    if (!can_perform('delete_comment')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete comments.']);
        exit;
    }
    // ... existing delete comment logic ...
} 