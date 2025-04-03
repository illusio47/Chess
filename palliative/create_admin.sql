-- First, delete any existing admin user with this email to avoid conflicts
DELETE FROM users WHERE email = 'admin@palliative.com';
DELETE FROM admins WHERE email = 'admin@palliative.com';

-- Insert into users table
INSERT INTO users (email, password, user_type) VALUES 
('admin@palliative.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert into admins table
INSERT INTO admins (name, email, role) VALUES 
('System Admin', 'admin@palliative.com', 'admin'); 