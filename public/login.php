<?php
require_once __DIR__ . '/../includes/error_log.php';
require_once __DIR__ . '/../includes/activity_log.php';
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role_id FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            // Fetch role_name
            $role_stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $role_stmt->bind_param("i", $user['role_id']);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            if ($role_row = $role_result->fetch_assoc()) {
                $_SESSION['role_name'] = $role_row['role_name'];
            }
            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Login', 'User logged in');
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>To-Do List Login</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: #f7f8fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            padding: 40px 32px 32px 32px;
            max-width: 370px;
            width: 100%;
            margin: 0 auto;
            text-align: center;
        }
        .login-title {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 8px;
            color: #232946;
        }
        .login-icon {
            font-size: 2.8em;
            color: #007bff;
            margin-bottom: 18px;
        }
        .form-control {
            border-radius: 8px;
            font-size: 1.08em;
        }
        .input-group-text {
            background: #f7f8fa;
            border: none;
            color: #888;
        }
        .btn-primary {
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1em;
            padding: 10px 0;
        }
        .login-footer {
            margin-top: 18px;
            color: #888;
            font-size: 0.98em;
        }
        .error {
            color: #dc3545;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-icon">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="login-title">To-Do List</div>
        <div class="mb-4" style="color:#888; font-size:1.08em;">Sign in to your account</div>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="username" required class="form-control" placeholder="Username">
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" required class="form-control" placeholder="Password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="login-footer">
            <small>Any registered user can log in.<br>Default: admin / admin123</small>
        </div>
    </div>
</body>
</html> 