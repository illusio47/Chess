<?php
/**
 * Doctor Controller
 * Handles doctor-related functionality
 */
class DoctorController extends BaseController {
    private $doctorId;
    
    public function __construct() {
        // Initialize database connection
        parent::__construct();
        
        // Get doctor ID from the database based on user ID
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doctor) {
                $this->doctorId = $doctor['id'];
            }
        }
        
        // Check if user is a doctor
        if (!$this->hasRole('doctor')) {
            $this->setFlash('error', 'You do not have permission to access this page.');
            $this->redirect('index.php?module=auth&action=login&type=doctor');
        }
    }
    
    /**
     * Display doctor dashboard
     */
    public function dashboard() {
        try {
            error_log("Doctor dashboard method called. Doctor ID: " . $this->doctorId);
            
            // Check if doctorId is set
            if (!$this->doctorId) {
                error_log("Doctor ID is not set. User ID: " . ($_SESSION['user_id'] ?? 'not set'));
                throw new Exception("Doctor record not found. Please contact the administrator.");
            }
            
            // Get doctor information
            $stmt = $this->db->prepare("
                SELECT d.*, u.email, u.name as doctor_name
                FROM doctors d
                INNER JOIN users u ON d.user_id = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Doctor data: " . print_r($doctor, true));
            
            if (!$doctor) {
                error_log("Doctor record not found for ID: " . $this->doctorId);
                throw new Exception("Doctor record not found");
            }
            
            // Get appointment statistics
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'scheduled' OR status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_appointments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                    SUM(CASE WHEN DATE(appointment_date) = CURDATE() AND status != 'cancelled' THEN 1 ELSE 0 END) as today_appointments
                FROM appointments
                WHERE doctor_id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Appointment stats: " . print_r($stats, true));
            
            // Get prescription statistics
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_prescriptions,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_prescriptions,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_prescriptions
                FROM prescriptions
                WHERE doctor_id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $prescription_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Prescription stats: " . print_r($prescription_stats, true));
            
            // Get today's appointments
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       p.name as patient_name,
                       p.phone as patient_phone
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = ?
                AND DATE(a.appointment_date) = CURDATE()
                AND a.status != 'cancelled'
                ORDER BY a.appointment_date
            ");
            $stmt->execute([$this->doctorId]);
            $today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Today's appointments count: " . count($today_appointments));
            
            // Add first_name and last_name for each patient in today's appointments
            foreach ($today_appointments as &$appointment) {
                if (isset($appointment['patient_name'])) {
                    $nameParts = explode(' ', $appointment['patient_name'], 2);
                    $appointment['patient_first_name'] = $nameParts[0];
                    $appointment['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Get pending appointments
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       p.name as patient_name
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = ?
                AND (a.status = 'scheduled' OR a.status = 'pending')
                ORDER BY a.appointment_date
                LIMIT 5
            ");
            $stmt->execute([$this->doctorId]);
            $pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Pending appointments count: " . count($pending_appointments));
            
            // Add first_name and last_name for each patient in pending appointments
            foreach ($pending_appointments as &$appointment) {
                if (isset($appointment['patient_name'])) {
                    $nameParts = explode(' ', $appointment['patient_name'], 2);
                    $appointment['patient_first_name'] = $nameParts[0];
                    $appointment['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Get recent prescriptions
            $stmt = $this->db->prepare("
                SELECT pr.*, 
                       p.name as patient_name,
                       pi.medicine as medication,
                       pi.dosage
                FROM prescriptions pr
                INNER JOIN patients p ON pr.patient_id = p.id
                LEFT JOIN prescription_items pi ON pi.prescription_id = pr.id
                WHERE pr.doctor_id = ?
                ORDER BY pr.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$this->doctorId]);
            $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Recent prescriptions count: " . count($recent_prescriptions));
            
            // Add first_name and last_name for each patient in recent prescriptions
            foreach ($recent_prescriptions as &$prescription) {
                if (isset($prescription['patient_name'])) {
                    $nameParts = explode(' ', $prescription['patient_name'], 2);
                    $prescription['patient_first_name'] = $nameParts[0];
                    $prescription['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Prepare data for the view
            $data = [
                'page_title' => 'Doctor Dashboard',
                'doctor' => $doctor,
                'stats' => $stats,
                'prescription_stats' => $prescription_stats,
                'today_appointments' => $today_appointments,
                'pending_appointments' => $pending_appointments,
                'recent_prescriptions' => $recent_prescriptions
            ];
            
            error_log("Rendering dashboard with data: " . print_r($data, true));
            
            // Render the dashboard view
            $this->render('dashboard', $data);
            
        } catch (Exception $e) {
            error_log("Error in doctor dashboard: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->setFlash('error', 'An error occurred while loading the dashboard: ' . $e->getMessage());
            $this->render('dashboard', ['page_title' => 'Doctor Dashboard', 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Display and manage appointments
     */
    public function appointments() {
        try {
            // Check if there's a filter parameter
            $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
            
            // Get all appointments for this doctor
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       p.name as patient_name,
                       p.phone as patient_phone
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.id
                WHERE a.doctor_id = ?
                " . ($filter === 'today' ? "AND DATE(a.appointment_date) = CURDATE() " : "") . "
                " . ($filter === 'pending' ? "AND (a.status = 'scheduled' OR a.status = 'pending') " : "") . "
                ORDER BY a.appointment_date DESC
            ");
            $stmt->execute([$this->doctorId]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add first_name and last_name for each patient
            foreach ($appointments as &$appointment) {
                if (isset($appointment['patient_name'])) {
                    $nameParts = explode(' ', $appointment['patient_name'], 2);
                    $appointment['patient_first_name'] = $nameParts[0];
                    $appointment['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Group appointments by status
            $grouped_appointments = [
                'scheduled' => [],
                'pending' => [],
                'confirmed' => [],
                'completed' => [],
                'cancelled' => []
            ];
            
            foreach ($appointments as $appointment) {
                // Handle both 'scheduled' and 'pending' statuses
                $status = $appointment['status'];
                if ($status === 'pending') {
                    $status = 'scheduled'; // Treat 'pending' as 'scheduled' for grouping
                }
                if (!isset($grouped_appointments[$status])) {
                    $status = 'scheduled'; // Default to scheduled if status is not recognized
                }
                $grouped_appointments[$status][] = $appointment;
            }
            
            // If we're filtering for pending, make sure to get all pending appointments
            if ($filter === 'pending' && empty($grouped_appointments['scheduled'])) {
                // Get pending appointments specifically
                $stmt = $this->db->prepare("
                    SELECT a.*, 
                           p.name as patient_name,
                           p.phone as patient_phone
                    FROM appointments a
                    INNER JOIN patients p ON a.patient_id = p.id
                    WHERE a.doctor_id = ?
                    AND (a.status = 'scheduled' OR a.status = 'pending')
                    ORDER BY a.appointment_date DESC
                ");
                $stmt->execute([$this->doctorId]);
                $pending_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add first_name and last_name for each patient
                foreach ($pending_appointments as &$appointment) {
                    if (isset($appointment['patient_name'])) {
                        $nameParts = explode(' ', $appointment['patient_name'], 2);
                        $appointment['patient_first_name'] = $nameParts[0];
                        $appointment['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                    }
                }
                
                $grouped_appointments['scheduled'] = $pending_appointments;
            }
            
            // Prepare data for the view
            $data = [
                'page_title' => 'Manage Appointments',
                'appointments' => $appointments,
                'grouped_appointments' => $grouped_appointments,
                'filter' => $filter,
                'db' => $this->db // Pass the database connection to the view
            ];
            
            // Render the appointments view
            $this->render('appointments', $data);
            
        } catch (Exception $e) {
            $this->logError("Error in appointments: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred while loading appointments: ' . $e->getMessage());
            $this->render('appointments', ['page_title' => 'Manage Appointments', 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Update appointment status
     */
    public function update_appointment_status() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Invalid request method");
            }
            
            $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            
            // Debug logging
            error_log("Updating appointment status - ID: {$appointment_id}, Status: {$status}");
            error_log("POST data: " . print_r($_POST, true));
            
            if (!$appointment_id || !$status) {
                throw new Exception("Missing required parameters");
            }
            
            // Validate status
            $valid_statuses = ['scheduled', 'pending', 'confirmed', 'completed', 'cancelled'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Invalid status value");
            }
            
            // Check if appointment belongs to this doctor
            $stmt = $this->db->prepare("SELECT * FROM appointments WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$appointment_id, $this->doctorId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                throw new Exception("Appointment not found or access denied");
            }
            
            // Update appointment status
            $stmt = $this->db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $appointment_id]);
            
            // Set appropriate success message based on the status
            $message = 'Appointment status updated successfully';
            if ($status === 'confirmed') {
                $message = 'Appointment confirmed successfully';
            } elseif ($status === 'completed') {
                $message = 'Appointment marked as completed';
            } elseif ($status === 'cancelled') {
                $message = 'Appointment rejected successfully';
            }
            
            $this->setFlash('success', $message);
            
            // Redirect back to the referring page or to appointments page
            if (isset($_SERVER['HTTP_REFERER'])) {
                // Extract the module and action from the referer URL
                $referer = $_SERVER['HTTP_REFERER'];
                $parsed_url = parse_url($referer, PHP_URL_QUERY);
                parse_str($parsed_url, $query_params);
                
                // Check if there's a fragment/anchor in the URL
                $fragment = '';
                if (strpos($referer, '#') !== false) {
                    $parts = explode('#', $referer);
                    if (count($parts) > 1) {
                        $fragment = '#' . $parts[1];
                    }
                }
                
                // Determine where to redirect
                if (isset($query_params['module']) && $query_params['module'] === 'doctor') {
                    if (isset($query_params['action']) && $query_params['action'] === 'dashboard') {
                        $this->redirect('index.php?module=doctor&action=dashboard' . $fragment);
                    } else {
                        $this->redirect($referer);
                    }
                } else {
                    $this->redirect('index.php?module=doctor&action=appointments');
                }
            } else {
                $this->redirect('index.php?module=doctor&action=appointments');
            }
            
        } catch (Exception $e) {
            $this->logError("Error updating appointment status: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=appointments');
        }
    }
    
    /**
     * View patient details
     */
    public function view_patient() {
        try {
            $patient_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
            
            if (!$patient_id) {
                throw new Exception("Patient ID is required");
            }
            
            // Get patient information
            $stmt = $this->db->prepare("
                SELECT p.*, u.email
                FROM patients p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient) {
                throw new Exception("Patient not found");
            }
            
            // Get patient's appointments with this doctor
            $stmt = $this->db->prepare("
                SELECT * FROM appointments
                WHERE patient_id = ? AND doctor_id = ?
                ORDER BY appointment_date DESC
            ");
            $stmt->execute([$patient_id, $this->doctorId]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient's prescriptions from this doctor
            $stmt = $this->db->prepare("
                SELECT * FROM prescriptions
                WHERE patient_id = ? AND doctor_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$patient_id, $this->doctorId]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize medical_history as an empty array
            $medical_history = [];
            
            // Check if medical_history table exists before querying it
            try {
                $checkTableStmt = $this->db->prepare("
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'medical_history'
                ");
                $checkTableStmt->execute();
                
                if ($checkTableStmt->fetchColumn()) {
                    // Table exists, get patient's medical history
                    $stmt = $this->db->prepare("
                        SELECT * FROM medical_history
                        WHERE patient_id = ?
                        ORDER BY recorded_date DESC
                    ");
                    $stmt->execute([$patient_id]);
                    $medical_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $this->logError("medical_history table does not exist in the database");
                }
            } catch (Exception $e) {
                $this->logError("Error checking medical_history table: " . $e->getMessage());
                // Continue execution, just with an empty medical_history array
            }
            
            // Prepare data for the view
            $data = [
                'page_title' => 'Patient Details',
                'patient' => $patient,
                'appointments' => $appointments,
                'prescriptions' => $prescriptions,
                'medical_history' => $medical_history
            ];
            
            $this->logError("Patient data: " . json_encode($patient));
            $this->logError("Appointments data: " . json_encode($appointments));
            $this->logError("Prescriptions data: " . json_encode($prescriptions));
            
            // Render the view_patient view
            $this->render('view_patient', $data);
            
        } catch (Exception $e) {
            $this->logError("Error viewing patient: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=dashboard');
        }
    }
    
    /**
     * Create a new prescription
     */
    public function create_prescription() {
        try {
            // If form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_SANITIZE_NUMBER_INT);
                $medication = filter_input(INPUT_POST, 'medication', FILTER_SANITIZE_STRING);
                $dosage = filter_input(INPUT_POST, 'dosage', FILTER_SANITIZE_STRING);
                $frequency = filter_input(INPUT_POST, 'frequency', FILTER_SANITIZE_STRING);
                $duration = filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_STRING);
                $instructions = filter_input(INPUT_POST, 'instructions', FILTER_SANITIZE_STRING);
                
                // Validate required fields
                if (!$patient_id || !$medication || !$dosage || !$frequency || !$duration) {
                    throw new Exception("All fields are required");
                }
                
                // Check if patient exists
                $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$patient) {
                    throw new Exception("Patient not found");
                }
                
                // Format the diagnosis field with medication, dosage, frequency, and duration
                $diagnosis = "Medication: $medication\nDosage: $dosage\nFrequency: $frequency\nDuration: $duration";
                
                // Insert prescription
                $stmt = $this->db->prepare("
                    INSERT INTO prescriptions (
                        patient_id, doctor_id, diagnosis, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $patient_id, $this->doctorId, $diagnosis, $instructions
                ]);
                
                $this->setFlash('success', 'Prescription created successfully');
                $this->redirect('index.php?module=doctor&action=view_patient&id=' . $patient_id);
                
            } else {
                // Get patient ID from query string if provided
                $patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_SANITIZE_NUMBER_INT);
                
                // Get list of patients for dropdown
                $stmt = $this->db->prepare("
                    SELECT p.id, p.name
                    FROM patients p
                    INNER JOIN appointments a ON p.id = a.patient_id
                    WHERE a.doctor_id = ?
                    GROUP BY p.id
                    ORDER BY p.name
                ");
                $stmt->execute([$this->doctorId]);
                $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get patient details if patient_id is provided
                $patient = null;
                if ($patient_id) {
                    $stmt = $this->db->prepare("
                        SELECT p.*, u.email
                        FROM patients p
                        INNER JOIN users u ON p.user_id = u.id
                        WHERE p.id = ?
                    ");
                    $stmt->execute([$patient_id]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$patient) {
                        throw new Exception("Patient not found");
                    }
                }
                
                // Prepare data for the view
                $data = [
                    'page_title' => 'Create Prescription',
                    'patients' => $patients,
                    'patient' => $patient
                ];
                
                // Render the create_prescription view
                $this->render('create_prescription', $data);
            }
        } catch (Exception $e) {
            $this->logError("Error creating prescription: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=dashboard');
        }
    }
    
    /**
     * View and edit doctor profile
     */
    public function profile() {
        try {
            // Get doctor information
            $stmt = $this->db->prepare("
                SELECT d.*, u.email, u.created_at
                FROM doctors d
                INNER JOIN users u ON d.user_id = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$doctor) {
                throw new Exception("Doctor profile not found");
            }
            
            error_log("Doctor profile data: " . print_r($doctor, true));
            error_log("Profile image path from DB: " . ($doctor['profile_image'] ?? 'Not set'));
            
            // Get statistics
            $stats = [];
            
            // Total appointments
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $stats['total_appointments'] = $stmt->fetchColumn();
            
            // Total patients
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT patient_id) as count 
                FROM appointments 
                WHERE doctor_id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $stats['total_patients'] = $stmt->fetchColumn();
            
            // Total prescriptions
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $stats['total_prescriptions'] = $stmt->fetchColumn();
            
            // Pending appointments
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM appointments 
                WHERE doctor_id = ? AND (status = 'scheduled' OR status = 'pending')
            ");
            $stmt->execute([$this->doctorId]);
            $stats['pending_appointments'] = $stmt->fetchColumn();
            
            // Prepare data for the view
            $data = [
                'page_title' => 'My Profile',
                'doctor' => $doctor,
                'stats' => $stats
            ];
            
            // Render the profile view
            $this->render('profile', $data);
            
        } catch (Exception $e) {
            $this->logError("Error viewing profile: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=dashboard');
        }
    }
    
    /**
     * Update doctor profile
     */
    public function update_profile() {
        try {
            // Check if form is submitted
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('index.php?module=doctor&action=profile');
                return;
            }
            
            // Get form data
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $specialization = filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING);
            $license_number = filter_input(INPUT_POST, 'license_number', FILTER_SANITIZE_STRING);
            $qualification = filter_input(INPUT_POST, 'qualification', FILTER_SANITIZE_STRING);
            $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
            
            // Validate required fields
            if (!$name || !$email) {
                throw new Exception("Name and email are required");
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Update doctor information
            $stmt = $this->db->prepare("
                UPDATE doctors 
                SET name = ?, phone = ?, specialization = ?, 
                    license_number = ?, qualification = ?, bio = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $phone, $specialization, 
                $license_number, $qualification, $bio, 
                $this->doctorId
            ]);
            
            // Update email in users table
            $stmt = $this->db->prepare("
                UPDATE users 
                SET email = ? 
                WHERE id = (SELECT user_id FROM doctors WHERE id = ?)
            ");
            $stmt->execute([$email, $this->doctorId]);
            
            // Check if password change is requested
            $current_password = filter_input(INPUT_POST, 'current_password');
            $new_password = filter_input(INPUT_POST, 'new_password');
            $confirm_password = filter_input(INPUT_POST, 'confirm_password');
            
            if ($current_password && $new_password && $confirm_password) {
                // Get user ID
                $stmt = $this->db->prepare("SELECT user_id FROM doctors WHERE id = ?");
                $stmt->execute([$this->doctorId]);
                $user_id = $stmt->fetchColumn();
                
                // Verify current password
                $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $hashed_password = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $hashed_password)) {
                    throw new Exception("Current password is incorrect");
                }
                
                // Validate new password
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match");
                }
                
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long");
                }
                
                // Update password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_new_password, $user_id]);
            }
            
            // Commit transaction
            $this->db->commit();
            
            $this->setFlash('success', 'Profile updated successfully');
            $this->redirect('index.php?module=doctor&action=profile');
            
        } catch (Exception $e) {
            // Rollback transaction if active
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logError("Error updating profile: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=profile');
        }
    }
    
    /**
     * Display and manage prescriptions
     */
    public function prescriptions() {
        try {
            // Get all prescriptions for this doctor
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       pt.name as patient_name,
                       pt.phone as patient_phone
                FROM prescriptions p
                INNER JOIN patients pt ON p.patient_id = pt.id
                WHERE p.doctor_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$this->doctorId]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add first_name and last_name for each patient
            foreach ($prescriptions as &$prescription) {
                if (isset($prescription['patient_name'])) {
                    $nameParts = explode(' ', $prescription['patient_name'], 2);
                    $prescription['patient_first_name'] = $nameParts[0];
                    $prescription['patient_last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Prepare data for the view
            $data = [
                'page_title' => 'Manage Prescriptions',
                'prescriptions' => $prescriptions
            ];
            
            // Render the prescriptions view
            $this->render('prescriptions', $data);
            
        } catch (Exception $e) {
            $this->logError("Error in prescriptions: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred while loading prescriptions: ' . $e->getMessage());
            $this->render('prescriptions', ['page_title' => 'Manage Prescriptions', 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Display and manage patients
     */
    public function patients() {
        try {
            // Get all patients assigned to this doctor
            $stmt = $this->db->prepare("
                SELECT DISTINCT p.*, 
                       COUNT(a.id) as appointment_count
                FROM patients p
                LEFT JOIN appointments a ON p.id = a.patient_id AND a.doctor_id = ?
                GROUP BY p.id
                ORDER BY p.name ASC
            ");
            $stmt->execute([$this->doctorId]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add first_name and last_name for each patient
            foreach ($patients as &$patient) {
                if (isset($patient['name'])) {
                    $nameParts = explode(' ', $patient['name'], 2);
                    $patient['first_name'] = $nameParts[0];
                    $patient['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
                }
            }
            
            // Prepare data for the view
            $data = [
                'page_title' => 'Manage Patients',
                'patients' => $patients
            ];
            
            // Render the patients view
            $this->render('patients', $data);
            
        } catch (Exception $e) {
            $this->logError("Error in patients: " . $e->getMessage());
            $this->setFlash('error', 'An error occurred while loading patients: ' . $e->getMessage());
            $this->render('patients', ['page_title' => 'Manage Patients', 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Edit doctor profile
     */
    public function edit_profile() {
        try {
            // If form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Validate and sanitize input
                $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
                $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
                $specialization = filter_var($_POST['specialization'], FILTER_SANITIZE_STRING);
                $qualification = filter_var($_POST['qualification'], FILTER_SANITIZE_STRING);
                $experience_years = filter_var($_POST['experience_years'], FILTER_SANITIZE_NUMBER_INT);
                $consultation_fee = filter_var($_POST['consultation_fee'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $license_number = filter_var($_POST['license_number'], FILTER_SANITIZE_STRING);
                
                // Validate required fields
                if (empty($name) || empty($phone) || empty($specialization)) {
                    $_SESSION['error'] = "Name, phone number, and specialization are required.";
                    $this->redirect('index.php?module=doctor&action=edit_profile');
                    return;
                }
                
                // Handle profile image upload
                $profile_image = null;
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    // Validate file type
                    if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                        $_SESSION['error'] = "Only JPG, PNG, and GIF images are allowed.";
                        $this->redirect('index.php?module=doctor&action=edit_profile');
                        return;
                    }
                    
                    // Validate file size
                    if ($_FILES['profile_image']['size'] > $max_size) {
                        $_SESSION['error'] = "Image size should not exceed 2MB.";
                        $this->redirect('index.php?module=doctor&action=edit_profile');
                        return;
                    }
                    
                    // Create uploads directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../../uploads/profile_images/';
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            error_log("Failed to create directory: " . $upload_dir);
                            $_SESSION['error'] = "Failed to create upload directory. Please contact administrator.";
                            $this->redirect('index.php?module=doctor&action=edit_profile');
                            return;
                        }
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'doctor_' . $this->doctorId . '_' . time() . '.' . $file_extension;
                    $target_file = $upload_dir . $filename;
                    
                    error_log("Attempting to upload file to: " . $target_file);
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                        // Use relative path for the profile image
                        $profile_image = 'uploads/profile_images/' . $filename;
                        error_log("File uploaded successfully to: " . $target_file);
                        error_log("Profile image path to be saved in DB: " . $profile_image);
                        
                        // Get current profile image
                        $stmt = $this->db->prepare("SELECT profile_image FROM doctors WHERE id = ?");
                        $stmt->execute([$this->doctorId]);
                        $current_image = $stmt->fetchColumn();
                        
                        // Delete old profile image if exists
                        if (!empty($current_image) && file_exists(__DIR__ . '/../../../' . $current_image)) {
                            if (unlink(__DIR__ . '/../../../' . $current_image)) {
                                error_log("Deleted old profile image: " . $current_image);
                            } else {
                                error_log("Failed to delete old profile image: " . $current_image);
                            }
                        }
                    } else {
                        $upload_error = error_get_last();
                        error_log("Failed to upload image. PHP Error: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
                        error_log("Upload error code: " . $_FILES['profile_image']['error']);
                        error_log("Source file exists: " . (file_exists($_FILES['profile_image']['tmp_name']) ? 'Yes' : 'No'));
                        error_log("Target directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));
                        $_SESSION['error'] = "Failed to upload image. Please try again.";
                        $this->redirect('index.php?module=doctor&action=edit_profile');
                        return;
                    }
                }
                
                // Check if doctors table has profile_image column
                $stmt = $this->db->query("SHOW COLUMNS FROM doctors LIKE 'profile_image'");
                if ($stmt->rowCount() === 0) {
                    // Add profile_image column to doctors table
                    $this->db->exec("ALTER TABLE doctors ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
                }
                
                // Update doctor details
                $sql = "
                    UPDATE doctors SET
                        name = ?,
                        phone = ?,
                        specialization = ?,
                        qualification = ?,
                        experience_years = ?,
                        consultation_fee = ?,
                        license_number = ?,
                        updated_at = NOW()
                ";
                $params = [
                    $name,
                    $phone,
                    $specialization,
                    $qualification,
                    $experience_years,
                    $consultation_fee,
                    $license_number
                ];
                
                // Add profile_image to update if uploaded
                if ($profile_image !== null) {
                    $sql .= ", profile_image = ?";
                    $params[] = $profile_image;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $this->doctorId;
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                
                // Update session data
                $_SESSION['name'] = $name;
                $_SESSION['success'] = "Profile updated successfully.";
                $this->redirect('index.php?module=doctor&action=profile');
                return;
            }
            
            // Get doctor details with user information
            $stmt = $this->db->prepare("
                SELECT d.*, u.email, u.status as user_status
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$this->doctorId]);
            $doctor_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$doctor_details) {
                $_SESSION['error'] = "Doctor profile not found.";
                $this->redirect('index.php?module=doctor&action=dashboard');
                return;
            }
            
            // Render the edit profile form
            $this->render('edit_profile', [
                'doctor' => $doctor_details
            ]);
        } catch (PDOException $e) {
            error_log("Error in edit_profile: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $_SESSION['error'] = "An error occurred while updating your profile: " . $e->getMessage();
            $this->redirect('index.php?module=doctor&action=profile');
        } catch (Exception $e) {
            error_log("General error in edit_profile: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
            $this->redirect('index.php?module=doctor&action=profile');
        }
    }
    
    /**
     * Edit prescription
     */
    public function edit_prescription() {
        try {
            $prescription_id = intval($_GET['id'] ?? 0);
            
            if ($prescription_id <= 0) {
                $this->setFlash('error', 'Invalid prescription ID');
                $this->redirect('index.php?module=doctor&action=prescriptions');
                return;
            }
            
            // Get prescription details
            $stmt = $this->db->prepare("
                SELECT p.*, pt.name as patient_name, pt.id as patient_id
                FROM prescriptions p
                JOIN patients pt ON p.patient_id = pt.id
                WHERE p.id = ? AND p.doctor_id = ?
            ");
            $stmt->execute([$prescription_id, $this->doctorId]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prescription) {
                $this->setFlash('error', 'Prescription not found or you do not have permission to edit it');
                $this->redirect('index.php?module=doctor&action=prescriptions');
                return;
            }
            
            // Get prescription items
            $stmt = $this->db->prepare("
                SELECT * FROM prescription_items
                WHERE prescription_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$prescription_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('edit_prescription', [
                'page_title' => 'Edit Prescription',
                'prescription' => $prescription,
                'items' => $items
            ]);
            
        } catch (Exception $e) {
            $this->logError("Error loading prescription: " . $e->getMessage());
            $this->setFlash('error', 'Error loading prescription');
            $this->redirect('index.php?module=doctor&action=prescriptions');
        }
    }
    
    /**
     * Process prescription update
     */
    public function update_prescription() {
        try {
            $prescription_id = intval($_POST['prescription_id'] ?? 0);
            $patient_id = intval($_POST['patient_id'] ?? 0);
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            // Validate input
            if ($prescription_id <= 0 || $patient_id <= 0 || empty($diagnosis)) {
                $this->setFlash('error', 'Invalid input data');
                $this->redirect('index.php?module=doctor&action=edit_prescription&id=' . $prescription_id);
                return;
            }
            
            // Verify prescription belongs to this doctor
            $stmt = $this->db->prepare("
                SELECT id FROM prescriptions 
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$prescription_id, $this->doctorId]);
            if (!$stmt->fetch()) {
                $this->setFlash('error', 'You do not have permission to edit this prescription');
                $this->redirect('index.php?module=doctor&action=prescriptions');
                return;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Update prescription
            $stmt = $this->db->prepare("
                UPDATE prescriptions 
                SET diagnosis = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$diagnosis, $notes, $prescription_id]);
            
            // Delete existing prescription items
            $stmt = $this->db->prepare("
                DELETE FROM prescription_items 
                WHERE prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            
            // Add new prescription items
            $medicines = $_POST['medicine'] ?? [];
            $dosages = $_POST['dosage'] ?? [];
            $frequencies = $_POST['frequency'] ?? [];
            $durations = $_POST['duration'] ?? [];
            $instructions = $_POST['instructions'] ?? [];
            
            for ($i = 0; $i < count($medicines); $i++) {
                if (!empty($medicines[$i])) {
                    $stmt = $this->db->prepare("
                        INSERT INTO prescription_items (
                            prescription_id, medicine, dosage, frequency, duration, instructions
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $prescription_id,
                        $medicines[$i],
                        $dosages[$i] ?? '',
                        $frequencies[$i] ?? '',
                        $durations[$i] ?? '',
                        $instructions[$i] ?? ''
                    ]);
                }
            }
            
            // Commit transaction
            $this->db->commit();
            
            $this->setFlash('success', 'Prescription updated successfully');
            $this->redirect('index.php?module=doctor&action=prescriptions');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            $this->logError("Error updating prescription: " . $e->getMessage());
            $this->setFlash('error', 'Error updating prescription: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=edit_prescription&id=' . ($prescription_id ?? 0));
        }
    }
    
    /**
     * Delete prescription
     */
    public function delete_prescription() {
        try {
            $prescription_id = intval($_GET['id'] ?? 0);
            
            if ($prescription_id <= 0) {
                $this->setFlash('error', 'Invalid prescription ID');
                $this->redirect('index.php?module=doctor&action=prescriptions');
                return;
            }
            
            // Verify prescription belongs to this doctor
            $stmt = $this->db->prepare("
                SELECT id FROM prescriptions 
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$prescription_id, $this->doctorId]);
            if (!$stmt->fetch()) {
                $this->setFlash('error', 'You do not have permission to delete this prescription');
                $this->redirect('index.php?module=doctor&action=prescriptions');
                return;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Delete prescription items
            $stmt = $this->db->prepare("
                DELETE FROM prescription_items 
                WHERE prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            
            // Delete prescription
            $stmt = $this->db->prepare("
                DELETE FROM prescriptions 
                WHERE id = ?
            ");
            $stmt->execute([$prescription_id]);
            
            // Commit transaction
            $this->db->commit();
            
            $this->setFlash('success', 'Prescription deleted successfully');
            $this->redirect('index.php?module=doctor&action=prescriptions');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            $this->logError("Error deleting prescription: " . $e->getMessage());
            $this->setFlash('error', 'Error deleting prescription: ' . $e->getMessage());
            $this->redirect('index.php?module=doctor&action=prescriptions');
        }
    }
}
