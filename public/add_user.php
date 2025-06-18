<?php
include 'db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;

    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, employee_id, customer_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $username, $password, $employee_id, $customer_id);
    $stmt->execute();
    echo "User added!";
}
?>
<form method="post">
    Username: <input type="text" name="username" required><br>
    Password: <input type="password" name="password" required><br>
    Employee ID (if employee): <input type="number" name="employee_id"><br>
    Customer ID (if customer): <input type="number" name="customer_id"><br>
    <input type="submit" value="Add User">
</form>