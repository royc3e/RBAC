-- RBAC Database Schema
CREATE DATABASE IF NOT EXISTS rbac_db;
USE rbac_db;

-- Roles table
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Employees table
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Customers table
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Users table (for authentication)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    employee_id INT DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

-- Insert default roles
INSERT INTO roles (role_name) VALUES
('Super Admin'),
('Admin'), 
('Project Manager'),
('Team Lead'),
('Senior User'),
('Regular User'),
('Guest User'),
('Auditor'),
('Support Staff'),
('Viewer');

-- Insert a default admin user (password: admin123)
-- This hash was generated using password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (username, password_hash, role_id) VALUES
('admin', '$2y$10$4XirlHK/feiQHzZUJ8R7n.J.peVJkDfeF0eXbMvxxFSKwki41fAZ2', 1); 