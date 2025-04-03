-- Sample data for Palliative Care System
USE palliative;

-- Sample Doctors
INSERT INTO users (email, password, user_type) VALUES
('dr.sharma@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('dr.patel@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor'),
('dr.gupta@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor');

INSERT INTO doctors (name, email, phone, specialization, qualification, experience_years, license_number, consultation_fee) VALUES
('Dr. Rajesh Sharma', 'dr.sharma@palliative.care', '9876543210', 'Oncology', 'MD, DM (Oncology)', 15, 'MCI123456', 1000.00),
('Dr. Priya Patel', 'dr.patel@palliative.care', '9876543211', 'Pain Management', 'MBBS, MD (Anesthesia)', 10, 'MCI234567', 800.00),
('Dr. Amit Gupta', 'dr.gupta@palliative.care', '9876543212', 'Palliative Medicine', 'MBBS, DNB (Palliative)', 8, 'MCI345678', 900.00);

-- Sample Patients
INSERT INTO users (email, password, user_type) VALUES
('rahul.kumar@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
('meera.singh@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
('anand.verma@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

INSERT INTO patients (name, email, phone, dob, gender, blood_group, emergency_contact, address, medical_history) VALUES
('Rahul Kumar', 'rahul.kumar@email.com', '9898989898', '1965-03-15', 'male', 'B+', '9876543213', 'A-123, Sector 15, Noida', 'Stage 2 Pancreatic Cancer, Diabetes Type 2'),
('Meera Singh', 'meera.singh@email.com', '9898989899', '1970-07-22', 'female', 'O+', '9876543214', '45B, MG Road, Bangalore', 'Advanced Lung Cancer, Hypertension'),
('Anand Verma', 'anand.verma@email.com', '9898989890', '1958-11-30', 'male', 'A+', '9876543215', '789, Civil Lines, Delhi', 'Chronic Pain, Osteoarthritis');

-- Sample Service Providers
INSERT INTO users (email, password, user_type) VALUES
('cityambulance@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'service'),
('medplus@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'service'),
('homecare@palliative.care', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'service');

INSERT INTO service_providers (company_name, email, phone, service_type, address, operating_hours, service_area, license_number) VALUES
('City Ambulance Services', 'cityambulance@palliative.care', '1800123456', 'cab', '56, Transport Nagar, Delhi', '24x7', 'Delhi NCR', 'AMB123456'),
('MedPlus Pharmacy', 'medplus@palliative.care', '1800234567', 'medicine', '123, Market Complex, Noida', '9 AM - 10 PM', 'Noida, Greater Noida', 'PHA234567'),
('HomeCare Equipment', 'homecare@palliative.care', '1800345678', 'equipment', '789, Industrial Area, Delhi', '10 AM - 8 PM', 'Delhi, Gurgaon, Noida', 'EQP345678');

-- Sample Appointments
INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status, notes) VALUES
(1, 1, NOW() + INTERVAL 1 DAY, 'Monthly checkup and pain management review', 'confirmed', 'Patient experiencing increased pain'),
(2, 2, NOW() + INTERVAL 2 DAY, 'Pain management consultation', 'pending', 'New patient referral from Apollo Hospital'),
(3, 3, NOW() + INTERVAL 3 DAY, 'Follow-up consultation', 'confirmed', 'Review after physiotherapy sessions'),
(1, 2, NOW() - INTERVAL 5 DAY, 'Emergency pain management', 'completed', 'Prescribed new pain medication'),
(2, 1, NOW() - INTERVAL 10 DAY, 'Regular checkup', 'completed', 'Patient showing improvement');

-- Sample Prescriptions
INSERT INTO prescriptions (patient_id, doctor_id, diagnosis, notes) VALUES
(1, 1, 'Moderate to severe pancreatic pain', 'Patient requires regular monitoring'),
(2, 2, 'Chronic chest pain and breathing difficulty', 'Consider oxygen therapy at home'),
(3, 3, 'Severe joint pain and limited mobility', 'Recommended physiotherapy');

-- Sample Prescription Items
INSERT INTO prescription_items (prescription_id, medicine, dosage, frequency, duration, instructions) VALUES
(1, 'Morphine Sulfate', '10mg', 'Twice daily', '30 days', 'Take with food'),
(1, 'Pantoprazole', '40mg', 'Once daily', '30 days', 'Take before breakfast'),
(2, 'Codeine Phosphate', '30mg', 'Three times daily', '15 days', 'Take after meals'),
(2, 'Oxygen Therapy', '2L/min', 'As needed', 'Continuous', 'Use oxygen concentrator at home'),
(3, 'Tramadol', '50mg', 'Twice daily', '15 days', 'Take with food'),
(3, 'Calcium + Vitamin D3', '500mg', 'Once daily', '30 days', 'Take after breakfast');

-- Sample Service Requests
INSERT INTO service_requests (patient_id, provider_id, service_type, requested_date, status, notes, provider_notes) VALUES
(1, 1, 'cab', NOW() + INTERVAL 1 DAY, 'confirmed', 'Need transport for hospital visit', 'Wheelchair accessible vehicle arranged'),
(2, 2, 'medicine', NOW(), 'in_progress', 'Monthly medicine refill', 'All medicines available, delivery by evening'),
(3, 3, 'equipment', NOW() - INTERVAL 1 DAY, 'completed', 'Need wheelchair and walker', 'Equipment delivered and setup complete'),
(1, 2, 'medicine', NOW() - INTERVAL 5 DAY, 'completed', 'Emergency medicine requirement', 'Delivered within 2 hours'),
(2, 3, 'equipment', NOW() + INTERVAL 2 DAY, 'pending', 'Oxygen concentrator required', 'Checking equipment availability');
