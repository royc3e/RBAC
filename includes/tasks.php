<?php
function get_task_assignees($conn, $task_id) {
    $stmt = $conn->prepare('SELECT u.user_id, u.username FROM task_assignees ta JOIN users u ON ta.user_id = u.user_id WHERE ta.task_id = ?');
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
function set_task_assignees($conn, $task_id, $user_ids) {
    $conn->query('DELETE FROM task_assignees WHERE task_id = '.intval($task_id));
    $stmt = $conn->prepare('INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)');
    foreach ($user_ids as $uid) {
        $stmt->bind_param('ii', $task_id, $uid);
        $stmt->execute();
    }
    $stmt->close();
}
function get_task_watchers($conn, $task_id) {
    $stmt = $conn->prepare('SELECT u.user_id, u.username FROM task_watchers tw JOIN users u ON tw.user_id = u.user_id WHERE tw.task_id = ?');
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
function set_task_watchers($conn, $task_id, $user_ids) {
    $conn->query('DELETE FROM task_watchers WHERE task_id = '.intval($task_id));
    $stmt = $conn->prepare('INSERT INTO task_watchers (task_id, user_id) VALUES (?, ?)');
    foreach ($user_ids as $uid) {
        $stmt->bind_param('ii', $task_id, $uid);
        $stmt->execute();
    }
    $stmt->close();
}
function get_task_tags($conn, $task_id) {
    $stmt = $conn->prepare('SELECT tags FROM tasks WHERE task_id = ?');
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $tags_str = $row && isset($row['tags']) && $row['tags'] !== null ? $row['tags'] : '';
    return $tags_str !== '' ? explode(',', $tags_str) : [];
}
function set_task_tags($conn, $task_id, $tags) {
    $tags_str = implode(',', $tags);
    $stmt = $conn->prepare('UPDATE tasks SET tags = ? WHERE task_id = ?');
    $stmt->bind_param('si', $tags_str, $task_id);
    $stmt->execute();
    $stmt->close();
} 