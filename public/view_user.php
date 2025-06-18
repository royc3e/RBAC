<?php
include 'db.php';
$sql = "SELECT u.user_id, u.username, e.name AS employee, c.name AS customer, r.role_name
        FROM users u
        LEFT JOIN employees e ON u.employee_id = e.employee_id
        LEFT JOIN customers c ON u.customer_id = c.customer_id
        LEFT JOIN user_roles ur ON u.user_id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.role_id";
$result = $conn->query($sql);
echo "<table border='1'><tr><th>User ID</th><th>Username</th><th>Employee</th><th>Customer</th><th>Role</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>{$row['user_id']}</td>
        <td>{$row['username']}</td>
        <td>{$row['employee']}</td>
        <td>{$row['customer']}</td>
        <td>{$row['role_name']}</td>
    </tr>";
}
echo "</table>";
?>