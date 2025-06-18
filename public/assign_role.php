<?php
include 'db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $role_id = $_POST['role_id'];
    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $role_id);
    $stmt->execute();
    echo "Role assigned!";
}
?>
<form method="post">
    User ID: <input type="number" name="user_id" required><br>
    Role ID: <input type="number" name="role_id" required><br>
    <input type="submit" value="Assign Role">
</form>