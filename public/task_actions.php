<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

session_start();
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_task') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $project_id = intval($_POST['project_id'] ?? 0);
    $assignee = intval($_POST['assignee'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $due_date = $_POST['due_date'] ?? null;
    if ($title === '' || !$project_id || !$assignee) {
        echo json_encode(['success' => false, 'message' => 'Title, Project, and Assignee are required.']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO tasks (title, description, project_id, assigned_to, status, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssisssi', $title, $desc, $project_id, $assignee, $status, $due_date, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $task_id = $conn->insert_id;
        // Fetch the new task with project and assignee info
        $sql = 'SELECT t.*, p.name AS project_name, u.username AS assignee FROM tasks t 
                LEFT JOIN projects p ON t.project_id = p.project_id 
                LEFT JOIN users u ON t.assigned_to = u.user_id 
                WHERE t.task_id = ?';
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $task_id);
        $stmt2->execute();
        $task = $stmt2->get_result()->fetch_assoc();
        ob_start();
        include __DIR__ . '/../partials/task_card.php';
        $html = ob_get_clean();
        echo json_encode(['success' => true, 'message' => 'Task added successfully!', 'task_html' => $html, 'project_id' => $project_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add task.']);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_task') {
    $task_id = intval($_POST['task_id'] ?? 0);
    if (!$task_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM tasks WHERE task_id=?');
    $stmt->bind_param('i', $task_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully!', 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']); 