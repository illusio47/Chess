-- Insert test patient user
INSERT INTO users (email, password_hash, name, user_type) 
VALUES (
    'patient@test.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Test Patient', 
    'patient'
);

-- Get the ID of the patient user
SET @patient_user_id = (SELECT id FROM users WHERE email = 'patient@test.com');

-- Insert patient details
INSERT INTO patients (user_id, name, email, phone, dob, gender, blood_group) 
VALUES (
    @patient_user_id, 
    'Test Patient', 
    'patient@test.com',
    '555-123-4567',
    '1980-01-01',
    'male',
    'O+'
);

-- Insert test doctor user
INSERT INTO users (email, password_hash, name, user_type) 
VALUES (
    'doctor@test.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Dr. John Smith', 
    'doctor'
);

-- Get the ID of the doctor user
SET @doctor_user_id = (SELECT id FROM users WHERE email = 'doctor@test.com');

-- Insert doctor details
INSERT INTO doctors (user_id, name, email, phone, specialization, qualification, experience_years, license_number, consultation_fee) 
VALUES (
    @doctor_user_id, 
    'Dr. John Smith', 
    'doctor@test.com',
    '555-987-6543',
    'Oncology',
    'MD, PhD',
    15,
    'MED12345',
    150.00
); 