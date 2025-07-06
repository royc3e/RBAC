<?php
function log_activity($conn, $user_id, $username, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $username, $action, $details);
    $stmt->execute();
}
?>