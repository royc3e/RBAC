<?php
require_once __DIR__ . '/../includes/error_log.php';
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/db.php';
include '../partials/header.php';
include '../partials/sidebar.php';
?>
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
.main-content {
    padding: 32px 0;
    background: #f7f8fa;
    min-height: 100vh;
}
.topbar {
    margin-bottom: 16px;
    padding: 18px 0 10px 0;
    background: none;
    font-size: 2.1em;
    text-align: center;
    font-weight: bold;
    box-shadow: none;
}
/* .card {
    max-width: 1400px;
    width: 90vw;
    min-width: 900px;
    margin: 32px auto;
    padding: 32px 18px 24px 18px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
} */
</style>
</head>
<?php

$message = '';
$message_type = '';

// Handle form submissions for user management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_employee':
                $name = $_POST['name'];
                $email = $_POST['email'];
                $role_id = $_POST['role_id'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO employees (name, email, role_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $name, $email, $role_id);
                    $stmt->execute();
                    $employee_id = $conn->insert_id;
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role_id, employee_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssii", $username, $password_hash, $role_id, $employee_id);
                    $stmt->execute();
                    $conn->commit();
                    $message = "Employee added successfully!";
                    $message_type = 'success';
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Add User', 'Added user ID: ' . $employee_id);
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
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO customers (name, email, role_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $name, $email, $role_id);
                    $stmt->execute();
                    $customer_id = $conn->insert_id;
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role_id, customer_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssii", $username, $password_hash, $role_id, $customer_id);
                    $stmt->execute();
                    $conn->commit();
                    $message = "Customer added successfully!";
                    $message_type = 'success';
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Add User', 'Added user ID: ' . $customer_id);
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
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username = ?, password_hash = ?, role_id = ? WHERE user_id = ?");
                        $stmt->bind_param("ssii", $username, $password_hash, $role_id, $user_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, role_id = ? WHERE user_id = ?");
                        $stmt->bind_param("sii", $username, $role_id, $user_id);
                    }
                    $stmt->execute();
                    if ($user_type == 'employee') {
                        $stmt = $conn->prepare("UPDATE employees SET name = ?, email = ?, role_id = ? WHERE employee_id = (SELECT employee_id FROM users WHERE user_id = ?)");
                        $stmt->bind_param("ssii", $name, $email, $role_id, $user_id);
                        $stmt->execute();
                    } elseif ($user_type == 'customer') {
                        $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, role_id = ? WHERE customer_id = (SELECT customer_id FROM users WHERE user_id = ?)");
                        $stmt->bind_param("ssii", $name, $email, $role_id, $user_id);
                        $stmt->execute();
                    }
                    $conn->commit();
                    $message = "User updated successfully!";
                    $message_type = 'success';
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Edit User', 'Edited user ID: ' . $user_id);
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
                    $stmt = $conn->prepare("SELECT employee_id, customer_id FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
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
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], 'Delete User', 'Deleted user ID: ' . $user_id);
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

$employee_roles = $conn->query("SELECT role_id, role_name FROM roles WHERE role_name IN ('Super Admin', 'Admin', 'Project Manager', 'Team Lead', 'Senior User', 'Regular User', 'Auditor', 'Support Staff') ORDER BY FIELD(role_name, 'Super Admin', 'Admin', 'Project Manager', 'Team Lead', 'Senior User', 'Regular User', 'Auditor', 'Support Staff')");
$customer_roles = $conn->query("SELECT role_id, role_name FROM roles WHERE role_name IN ('Regular User', 'Guest User', 'Viewer') ORDER BY FIELD(role_name, 'Regular User', 'Guest User', 'Viewer')");
$users_sql = "SELECT u.user_id, u.username, u.is_active, e.name as employee_name, e.email as employee_email, c.name as customer_name, c.email as customer_email, r.role_name, r.role_id FROM users u LEFT JOIN employees e ON u.employee_id = e.employee_id LEFT JOIN customers c ON u.customer_id = c.customer_id LEFT JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id DESC";
$users_result = $conn->query($users_sql);
?>
    <div class="main-content">
    <div class="card" style="max-width: 1100px; margin-left: 300px;">
        <h1 class="mb-4 text-center">User Management</h1>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">All Users</h2>
            <div>
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">Add Employee</button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">Add Customer</button>
            </div>
        </div>
                <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
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
                            <td><?php echo htmlspecialchars($user['employee_name'] ?: $user['customer_name'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['employee_email'] ?: $user['customer_email'] ?: 'N/A'); ?></td>
                            <td><?php echo $user['employee_name'] ? 'Employee' : ($user['customer_name'] ? 'Customer' : 'Admin'); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                    </td>
                                    <td>
                                <div class="d-flex flex-row align-items-center" style="gap: 0.5rem; flex-wrap: nowrap;">
                                    <button type="button" class="btn btn-sm btn-outline-info view-user-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewUserModal"
                                        data-user='<?php echo json_encode([
                                            "user_id" => $user["user_id"],
                                            "username" => $user["username"],
                                            "name" => $user["employee_name"] ?: $user["customer_name"] ?: "N/A",
                                            "email" => $user["employee_email"] ?: $user["customer_email"] ?: "N/A",
                                            "type" => $user["employee_name"] ? "Employee" : ($user["customer_name"] ? "Customer" : "Admin"),
                                            "role_id" => $user["role_id"],
                                            "role_name" => $user["role_name"],
                                            "is_active" => $user["is_active"]
                                        ]); ?>'>
                                        <span class="bi bi-eye"></span> View
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-user-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal"
                                        data-user='<?php echo json_encode([
                                            "user_id" => $user["user_id"],
                                            "username" => $user["username"],
                                            "name" => $user["employee_name"] ?: $user["customer_name"] ?: "N/A",
                                            "email" => $user["employee_email"] ?: $user["customer_email"] ?: "N/A",
                                            "type" => $user["employee_name"] ? "employee" : ($user["customer_name"] ? "customer" : "admin"),
                                            "role_id" => $user["role_id"],
                                            "role_name" => $user["role_name"],
                                            "is_active" => $user["is_active"]
                                        ]); ?>'>
                                        <span class="bi bi-pencil"></span> Edit
                                    </button>
                                    <form method="post" style="display:inline; margin: 0;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?>" title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <span class="bi bi-power"></span> <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    <form method="post" style="display:inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><span class="bi bi-trash"></span> Delete</button>
                                    </form>
                                </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
    </div>
    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
      <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
            <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
          <form method="post">
            <input type="hidden" name="action" value="add_employee">
                        <div class="modal-body">
              <div class="mb-3">
                <label>Name:</label>
                <input type="text" name="name" class="form-control" required>
                                </div>
              <div class="mb-3">
                <label>Email:</label>
                <input type="email" name="email" class="form-control" required>
                                </div>
              <div class="mb-3">
                <label>Username:</label>
                <input type="text" name="username" class="form-control" required>
                                </div>
              <div class="mb-3">
                <label>Password:</label>
                <input type="password" name="password" class="form-control" required>
                                </div>
              <div class="mb-3">
                <label>Role:</label>
                <select name="role_id" class="form-control" required>
                  <option value="">Select Role</option>
                  <?php $employee_roles->data_seek(0); while ($role = $employee_roles->fetch_assoc()): ?>
                    <option value="<?php echo $role['role_id']; ?>">
                      <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                  <?php endwhile; ?>
                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Employee</button>
            </div>
          </form>
                        </div>
                    </div>
                </div>
    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
      <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
            <h5 class="modal-title" id="addCustomerModalLabel">Add Customer</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
          <form method="post">
            <input type="hidden" name="action" value="add_customer">
                        <div class="modal-body">
              <div class="mb-3">
                                        <label>Name:</label>
                <input type="text" name="name" class="form-control" required>
                                    </div>
              <div class="mb-3">
                                        <label>Email:</label>
                <input type="email" name="email" class="form-control" required>
                                    </div>
              <div class="mb-3">
                                        <label>Username:</label>
                <input type="text" name="username" class="form-control" required>
                                    </div>
              <div class="mb-3">
                <label>Password:</label>
                <input type="password" name="password" class="form-control" required>
                                    </div>
              <div class="mb-3">
                                    <label>Role:</label>
                <select name="role_id" class="form-control" required>
                                        <option value="">Select Role</option>
                  <?php $customer_roles->data_seek(0); while ($role = $customer_roles->fetch_assoc()): ?>
                                            <option value="<?php echo $role['role_id']; ?>">
                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Customer</button>
                                </div>
                            </form>
        </div>
      </div>
    </div>
    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <dl class="row">
              <dt class="col-sm-4">User ID</dt>
              <dd class="col-sm-8" id="view-user-id"></dd>
              <dt class="col-sm-4">Username</dt>
              <dd class="col-sm-8" id="view-username"></dd>
              <dt class="col-sm-4">Name</dt>
              <dd class="col-sm-8" id="view-name"></dd>
              <dt class="col-sm-4">Email</dt>
              <dd class="col-sm-8" id="view-email"></dd>
              <dt class="col-sm-4">Type</dt>
              <dd class="col-sm-8" id="view-type"></dd>
              <dt class="col-sm-4">Role</dt>
              <dd class="col-sm-8" id="view-role"></dd>
              <dt class="col-sm-4">Status</dt>
              <dd class="col-sm-8" id="view-status"></dd>
            </dl>
                        </div>
                        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
    </div>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit-user-id">
            <input type="hidden" name="user_type" id="edit-user-type">
            <div class="modal-body">
              <div class="mb-3">
                <label>Name:</label>
                <input type="text" name="name" id="edit-name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label>Email:</label>
                <input type="email" name="email" id="edit-email" class="form-control" required>
              </div>
              <div class="mb-3">
                <label>Username:</label>
                <input type="text" name="username" id="edit-username" class="form-control" required>
              </div>
              <div class="mb-3">
                <label>Password: <span class="text-muted" style="font-size:0.9em;">(leave blank to keep unchanged)</span></label>
                <input type="password" name="password" id="edit-password" class="form-control">
              </div>
              <div class="mb-3">
                <label>Role:</label>
                <select name="role_id" id="edit-role-id" class="form-control" required>
                  <option value="">Select Role</option>
                  <?php $employee_roles->data_seek(0); while ($role = $employee_roles->fetch_assoc()): ?>
                    <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                  <?php endwhile; ?>
                  <?php $customer_roles->data_seek(0); while ($role = $customer_roles->fetch_assoc()): ?>
                    <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap JS for modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- User Modal JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // View User
  document.querySelectorAll('.view-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var user = JSON.parse(this.getAttribute('data-user'));
      document.getElementById('view-user-id').textContent = user.user_id;
      document.getElementById('view-username').textContent = user.username;
      document.getElementById('view-name').textContent = user.name;
      document.getElementById('view-email').textContent = user.email;
      document.getElementById('view-type').textContent = user.type;
      document.getElementById('view-role').textContent = user.role_name;
      document.getElementById('view-status').textContent = user.is_active ? 'Active' : 'Inactive';
    });
  });
  // Edit User
  document.querySelectorAll('.edit-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var user = JSON.parse(this.getAttribute('data-user'));
      document.getElementById('edit-user-id').value = user.user_id;
      document.getElementById('edit-user-type').value = user.type;
      document.getElementById('edit-name').value = user.name;
      document.getElementById('edit-email').value = user.email;
      document.getElementById('edit-username').value = user.username;
        document.getElementById('edit-password').value = '';
      // Set role dropdown
      var roleSelect = document.getElementById('edit-role-id');
      for (var i = 0; i < roleSelect.options.length; i++) {
        if (roleSelect.options[i].value == user.role_id) {
          roleSelect.selectedIndex = i;
          break;
        }
      }
    });
  });
    });
</script>
</body>
</html> 