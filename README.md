# RBAC (Role-Based Access Control) System

A PHP-based Role-Based Access Control system with user management capabilities.

## 📁 Project Structure

```
RBAC/
├── config/
│   └── db.php                 # Database connection
├── includes/
│   └── auth.php               # Authentication helper functions
├── public/
│   ├── index.php              # Redirects to login
│   ├── login.php              # Admin login page
│   ├── dashboard.php          # Main admin dashboard
│   └── logout.php             # Logout functionality
└── sql/
    └── rbac_schema.sql        # Database schema and initial data
```

## 🚀 Setup Instructions

### 1. **Database Setup**
1. Open phpMyAdmin or MySQL CLI
2. Import the `sql/rbac_schema.sql` file
3. This will create the database and all required tables

### 2. **Default Admin Account**
- **Username:** `admin`
- **Password:** `admin123`

### 3. **Access the Application**
1. Start your XAMPP server
2. Navigate to: `http://localhost/RBAC/public/`
3. You'll be redirected to the login page
4. Use the default admin credentials to log in

## 🗄️ Database Schema

### **Tables:**

1. **roles** - Stores all available roles
   - `role_id` (Primary Key)
   - `role_name` (e.g., "Admin", "Customer")
   - `created_at`

2. **employees** - Stores employee data
   - `employee_id` (Primary Key)
   - `name`
   - `email`
   - `role_id` (Foreign Key to roles)
   - `created_at`

3. **customers** - Stores customer data
   - `customer_id` (Primary Key)
   - `name`
   - `email`
   - `role_id` (Foreign Key to roles)
   - `created_at`

4. **users** - Stores authentication data
   - `user_id` (Primary Key)
   - `username`
   - `password_hash`
   - `role_id` (Foreign Key to roles)
   - `employee_id` (Foreign Key to employees, optional)
   - `customer_id` (Foreign Key to customers, optional)
   - `is_active`
   - `created_at`

## 🔐 Available Roles

1. Super Admin
2. Admin
3. Project Manager
4. Team Lead
5. Senior User
6. Regular User
7. Guest User
8. Auditor
9. Support Staff
10. Viewer

## 🎯 Features

### **Admin Dashboard**
- **Add Employee:** Create new employee accounts with roles
- **Add Customer:** Create new customer accounts with roles
- **View Users:** See all users in the system with their details
- **User Management:** All CRUD operations in one unified interface

### **Security Features**
- Password hashing using PHP's `password_hash()`
- Session-based authentication
- Role-based access control
- SQL injection prevention with prepared statements

## 🔧 Usage

### **Adding a New Employee:**
1. Log in as admin
2. Click "Add Employee" button
3. Fill in the form:
   - Name
   - Email
   - Select Role
   - Username
   - Password
4. Submit the form

### **Adding a New Customer:**
1. Log in as admin
2. Click "Add Customer" button
3. Fill in the form (same fields as employee)
4. Submit the form

### **Viewing All Users:**
- The dashboard automatically shows all users in a table
- Displays user type (Employee/Customer/Admin)
- Shows assigned roles and account status

## 🛠️ Technical Details

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Authentication:** Session-based with password hashing
- **Security:** Prepared statements, input validation
- **Frontend:** HTML5, CSS3, JavaScript

## 🔒 Security Notes

- Default admin password should be changed after first login
- All passwords are hashed using PHP's `password_hash()`
- SQL injection is prevented using prepared statements
- Session management prevents unauthorized access

## 📝 Future Enhancements

- User profile management
- Password reset functionality
- Activity logging
- Advanced role permissions
- API endpoints for external integration 