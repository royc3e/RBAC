<?php
require_once '../includes/auth.php';
requireAdmin();
include '../config/db.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_employee':
                $name = $_POST['name'];
                $email = $_POST['email'];
                $role_id = $_POST['role_id'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Insert employee
                    $stmt = $conn->prepare("INSERT INTO employees (name, email, role_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $name, $email, $role_id);
                    $stmt->execute();
                    $employee_id = $conn->insert_id;
                    
                    // Insert user account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role_id, employee_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssii", $username, $password_hash, $role_id, $employee_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $message = "Employee added successfully!";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $message_type = 'error';
                }
                break;
                
            case 'add_customer':
                $name = $_POST['name'];
                $email = $_POST['email'];
                $role_id = $_POST['role_id'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                
                // Start transaction
                $conn->begin_transaction();
                try {
                    // Insert customer
                    $stmt = $conn->prepare("INSERT INTO customers (name, email, role_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $name, $email, $role_id);
                    $stmt->execute();
                    $customer_id = $conn->insert_id;
                    
                    // Insert user account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role_id, customer_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssii", $username, $password_hash, $role_id, $customer_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $message = "Customer added successfully!";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'update_user':
                $user_id = $_POST['user_id'];
                $name = $_POST['name'];
                $email = $_POST['email'];
                $role_id = $_POST['role_id'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $user_type = $_POST['user_type'];
                
                $conn->begin_transaction();
                try {
                    // Update user account
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, password_hash = ?, role_id = ? WHERE user_id = ?");
                        $stmt->bind_param("ssii", $username, $password_hash, $role_id, $user_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, role_id = ? WHERE user_id = ?");
                        $stmt->bind_param("sii", $username, $role_id, $user_id);
                    }
                    $stmt->execute();
                    
                    // Update employee or customer record (only if not admin)
                    if ($user_type == 'employee') {
                        $stmt = $conn->prepare("UPDATE employees SET name = ?, email = ?, role_id = ? WHERE employee_id = (SELECT employee_id FROM users WHERE user_id = ?)");
                        $stmt->bind_param("ssii", $name, $email, $role_id, $user_id);
                        $stmt->execute();
                    } elseif ($user_type == 'customer') {
                        $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, role_id = ? WHERE customer_id = (SELECT customer_id FROM users WHERE user_id = ?)");
                        $stmt->bind_param("ssii", $name, $email, $role_id, $user_id);
                        $stmt->execute();
                    }
                    // For admin users, we don't update employees or customers table since they don't have entries there
                    
                    $conn->commit();
                    $message = "User updated successfully!";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'delete_user':
                $user_id = $_POST['user_id'];
                
                $conn->begin_transaction();
                try {
                    // Get user details first
                    $stmt = $conn->prepare("SELECT employee_id, customer_id FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    // Delete user
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    
                    // Delete employee/customer record
                    if ($user['employee_id']) {
                        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
                        $stmt->bind_param("i", $user['employee_id']);
                        $stmt->execute();
                    } elseif ($user['customer_id']) {
                        $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
                        $stmt->bind_param("i", $user['customer_id']);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    $message = "User deleted successfully!";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $message_type = 'error';
                }
                break;

            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status ? 0 : 1;
                
                $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmt->bind_param("ii", $new_status, $user_id);
                $stmt->execute();
                
                $message = "User status updated successfully!";
                $message_type = 'success';
                break;
        }
    }
}

// Get roles for dropdowns (filtered)
$employee_roles = $conn->query("SELECT role_id, role_name FROM roles WHERE role_name IN ('Project Manager', 'Team Lead', 'Senior User', 'Regular User', 'Support Staff') ORDER BY role_name");
$customer_roles = $conn->query("SELECT role_id, role_name FROM roles WHERE role_name IN ('Regular User', 'Guest User', 'Viewer') ORDER BY role_name");

// Get all users with their details
$users_sql = "SELECT u.user_id, u.username, u.is_active, 
                     e.name as employee_name, e.email as employee_email,
                     c.name as customer_name, c.email as customer_email,
                     r.role_name, r.role_id
              FROM users u
              LEFT JOIN employees e ON u.employee_id = e.employee_id
              LEFT JOIN customers c ON u.customer_id = c.customer_id
              LEFT JOIN roles r ON u.role_id = r.role_id
              ORDER BY u.user_id DESC";
$users_result = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>RBAC Admin Dashboard</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        .header { 
            background: #fff; 
            padding: 20px; 
            margin-bottom: 20px; 
            border: 1px solid #ddd;
        }
        .nav { 
            background: #fff; 
            padding: 15px; 
            margin-bottom: 20px; 
            border: 1px solid #ddd;
        }
        .nav a { 
            color: #333; 
            text-decoration: none; 
            margin-right: 20px; 
            padding: 8px 16px; 
        }
        .nav a:hover { 
            background: #f0f0f0; 
        }
        .section { 
            background: #fff; 
            padding: 20px; 
            margin-bottom: 20px; 
            border: 1px solid #ddd;
        }
        .form-group { 
            margin-bottom: 15px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        input[type="text"], input[type="email"], input[type="password"], select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            box-sizing: border-box;
        }
        input[type="submit"] { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            cursor: pointer; 
        }
        input[type="submit"]:hover { 
            background: #0056b3; 
        }
        .message { 
            padding: 10px; 
            margin-bottom: 20px; 
            border: 1px solid #ddd;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-color: #c3e6cb; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-color: #f5c6cb; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            padding: 10px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background: #f8f9fa; 
            font-weight: bold; 
        }
        .tab-container { 
            margin-bottom: 20px; 
        }
        .tab-buttons { 
            display: flex; 
            border-bottom: 1px solid #ddd; 
            margin-bottom: 20px; 
        }
        .tab-button { 
            background: none; 
            border: none; 
            padding: 10px 20px; 
            cursor: pointer; 
        }
        .tab-button.active { 
            background: #007bff; 
            color: white; 
        }
        .tab-content { 
            display: none; 
        }
        .tab-content.active { 
            display: block; 
        }
        .btn { 
            padding: 5px 10px; 
            border: none; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            margin: 2px; 
            font-size: 12px; 
        }
        .btn-view { 
            background: #17a2b8; 
            color: white; 
        }
        .btn-edit { 
            background: #ffc107; 
            color: #212529; 
        }
        .btn-delete { 
            background: #dc3545; 
            color: white; 
        }
        .btn-toggle { 
            background: #28a745; 
            color: white; 
        }
        .form-row { 
            display: flex; 
            gap: 15px; 
        }
        .form-row .form-group { 
            flex: 1; 
        }
        
        /* Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content { 
            background-color: white; 
            margin: 5% auto; 
            padding: 20px; 
            border: 1px solid #ddd;
            width: 80%; 
            max-width: 500px; 
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 1px solid #ddd; 
        }
        .modal-title { 
            font-size: 18px; 
            font-weight: bold; 
        }
        .close { 
            color: #aaa; 
            font-size: 24px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        .close:hover { 
            color: #000; 
        }
        .modal-body { 
            margin-bottom: 20px; 
        }
        .modal-footer { 
            text-align: right; 
            padding-top: 10px; 
            border-top: 1px solid #ddd; 
        }
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
            padding: 8px 16px; 
            border: none; 
            cursor: pointer; 
            margin-left: 10px; 
        }
        .user-info { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 10px; 
            margin-bottom: 15px; 
        }
        .user-info-item { 
            padding: 8px; 
            background: #f8f9fa; 
            border: 1px solid #ddd;
        }
        .user-info-label { 
            font-weight: bold; 
            color: #666; 
            font-size: 12px; 
        }
        .user-info-value { 
            color: #333; 
            margin-top: 3px; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RBAC Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        
        <div class="nav">
            <a href="#dashboard">Dashboard</a>
            <a href="#add-user">Add User</a>
            <a href="#view-users">View Users</a>
            <a href="logout.php">Logout</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add User Section -->
        <div class="section" id="add-user">
            <h2>Add New User</h2>
            
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="showTab('employee-tab')">Add Employee</button>
                    <button class="tab-button" onclick="showTab('customer-tab')">Add Customer</button>
                </div>
                
                <!-- Employee Tab -->
                <div id="employee-tab" class="tab-content active">
                    <h3>Add Employee</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_employee">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name:</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Password:</label>
                                <input type="password" name="password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Role:</label>
                            <select name="role_id" required>
                                <option value="">Select Role</option>
                                <?php while ($role = $employee_roles->fetch_assoc()): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <input type="submit" value="Add Employee">
                    </form>
                </div>
                
                <!-- Customer Tab -->
                <div id="customer-tab" class="tab-content">
                    <h3>Add Customer</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_customer">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name:</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Password:</label>
                                <input type="password" name="password" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Role:</label>
                            <select name="role_id" required>
                                <option value="">Select Role</option>
                                <?php while ($role = $customer_roles->fetch_assoc()): ?>
                                    <option value="<?php echo $role['role_id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <input type="submit" value="Add Customer">
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Users Section -->
        <div class="section" id="view-users">
            <h2>View All Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <?php 
                                if ($user['employee_name']) {
                                    echo htmlspecialchars($user['employee_name']);
                                } elseif ($user['customer_name']) {
                                    echo htmlspecialchars($user['customer_name']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($user['employee_email']) {
                                    echo htmlspecialchars($user['employee_email']);
                                } elseif ($user['customer_email']) {
                                    echo htmlspecialchars($user['customer_email']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($user['employee_name']) {
                                    echo 'Employee';
                                } elseif ($user['customer_name']) {
                                    echo 'Customer';
                                } else {
                                    echo 'Admin';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <a href="#" class="btn btn-view" onclick="viewUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['employee_name'] ?: $user['customer_name'] ?: 'N/A'); ?>', '<?php echo htmlspecialchars($user['employee_email'] ?: $user['customer_email'] ?: 'N/A'); ?>', '<?php echo htmlspecialchars($user['role_name']); ?>', '<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>', '<?php echo $user['employee_name'] ? 'employee' : ($user['customer_name'] ? 'customer' : 'admin'); ?>')">View</a>
                                <a href="#" class="btn btn-edit" onclick="editUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['employee_name'] ?: $user['customer_name'] ?: ''); ?>', '<?php echo htmlspecialchars($user['employee_email'] ?: $user['customer_email'] ?: ''); ?>', <?php echo $user['role_id']; ?>, '<?php echo $user['employee_name'] ? 'employee' : ($user['customer_name'] ? 'customer' : 'admin'); ?>')">Edit</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                    <button type="submit" class="btn btn-toggle">
                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View User Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">User Details</span>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="user-info">
                    <div class="user-info-item">
                        <div class="user-info-label">User ID</div>
                        <div class="user-info-value" id="view-user-id"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Username</div>
                        <div class="user-info-value" id="view-username"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Name</div>
                        <div class="user-info-value" id="view-name"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Email</div>
                        <div class="user-info-value" id="view-email"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">User Type</div>
                        <div class="user-info-value" id="view-type"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Role</div>
                        <div class="user-info-value" id="view-role"></div>
                    </div>
                    <div class="user-info-item">
                        <div class="user-info-label">Status</div>
                        <div class="user-info-value" id="view-status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Edit User</span>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="editForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <input type="hidden" name="user_type" id="edit-user-type">
                    
                    <div class="form-row" id="name-email-row">
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="name" id="edit-name" required>
                        </div>
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" id="edit-email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username:</label>
                            <input type="text" name="username" id="edit-username" required>
                        </div>
                        <div class="form-group">
                            <label>Password: (leave blank to keep current)</label>
                            <input type="password" name="password" id="edit-password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Role:</label>
                        <select name="role_id" id="edit-role" required>
                            <option value="">Select Role</option>
                            <?php 
                            $all_roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
                            while ($role = $all_roles->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button class="btn btn-edit" onclick="submitEditForm()">Update User</button>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
        
        function viewUser(userId, username, name, email, role, status, type) {
            document.getElementById('view-user-id').textContent = userId;
            document.getElementById('view-username').textContent = username;
            document.getElementById('view-name').textContent = name;
            document.getElementById('view-email').textContent = email;
            document.getElementById('view-type').textContent = type === 'employee' ? 'Employee' : (type === 'customer' ? 'Customer' : 'Admin');
            document.getElementById('view-role').textContent = role;
            document.getElementById('view-status').textContent = status;
            
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function editUser(userId, username, name, email, roleId, type) {
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-role').value = roleId;
            document.getElementById('edit-user-type').value = type;
            document.getElementById('edit-password').value = '';
            
            // Handle admin users differently
            if (type === 'admin') {
                // Hide name and email fields for admin users
                document.getElementById('name-email-row').style.display = 'none';
                document.getElementById('edit-name').value = 'Admin User';
                document.getElementById('edit-email').value = 'admin@system.com';
                document.getElementById('edit-name').required = false;
                document.getElementById('edit-email').required = false;
            } else {
                // Show name and email fields for employees and customers
                document.getElementById('name-email-row').style.display = 'flex';
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-email').value = email;
                document.getElementById('edit-name').required = true;
                document.getElementById('edit-email').required = true;
            }
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function submitEditForm() {
            document.getElementById('editForm').submit();
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>