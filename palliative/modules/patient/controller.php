<?php
/**
 * Patient Module Controller
 * Palliative Care System
 */

class PatientController extends BaseController {
    protected $patient;
    
    public function __construct() {
        parent::__construct();
        
        try {
            // Get patient details
            $stmt = $this->db->prepare("SELECT * FROM patients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                session_destroy();
                $_SESSION['error'] = "Patient account not found. Please contact support.";
                header("Location: " . SITE_URL . "index.php?module=auth&action=login&type=patient");
            exit;
        }
        
            $this->patient = $patient;
            
        } catch (PDOException $e) {
            error_log("Error in PatientController constructor: " . $e->getMessage());
            session_destroy();
            $_SESSION['error'] = "An error occurred. Please try again later.";
            header("Location: " . SITE_URL . "index.php?module=auth&action=login&type=patient");
            exit;
        }
    }

    /**
     * Display patient dashboard
     */
    public function dashboard() {
        try {
            // Get upcoming appointments
            $stmt = $this->db->prepare("
                SELECT a.*, d.name as doctor_name, d.specialization
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.patient_id = ? AND a.status != 'cancelled' AND a.appointment_date >= NOW()
                ORDER BY a.appointment_date
                LIMIT 3
            ");
            $stmt->execute([$this->patient['id']]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent prescriptions
            $stmt = $this->db->prepare("
                SELECT p.*, d.name as doctor_name
                FROM prescriptions p
                JOIN doctors d ON p.doctor_id = d.id
                WHERE p.patient_id = ?
                ORDER BY p.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->patient['id']]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent medicine orders
            $stmt = $this->db->prepare("
                SELECT mo.*, ph.name as pharmacy_name
                FROM medicine_orders mo
                LEFT JOIN pharmacies ph ON mo.pharmacy_id = ph.id
                WHERE mo.patient_id = ?
                ORDER BY mo.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->patient['id']]);
            $medicine_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get recent cab bookings
            $stmt = $this->db->prepare("
                SELECT cb.*, sp.company_name as provider_name
                FROM cab_bookings cb
                LEFT JOIN service_providers sp ON cb.provider_id = sp.id
                WHERE cb.patient_id = ?
                ORDER BY cb.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->patient['id']]);
            $cab_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('dashboard', [
                'patient' => $this->patient,
                'appointments' => $appointments,
                'prescriptions' => $prescriptions,
                'medicine_orders' => $medicine_orders,
                'cab_bookings' => $cab_bookings
            ]);
        } catch (PDOException $e) {
            error_log("Error in dashboard: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your dashboard.";
            // Unable to load dashboard, forward to profile
            header('Location: index.php?module=patient&action=profile');
            exit();
        }
    }

    /**
     * Display and manage appointments
     */
    public function appointments() {
        try {
            // Get all appointments for the patient
        $stmt = $this->db->prepare("
                SELECT a.*, d.name as doctor_name, d.specialization 
            FROM appointments a 
                JOIN doctors d ON a.doctor_id = d.id 
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC
        ");
            $stmt->execute([$this->patient['id']]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Render the appointments view
            $this->render('appointments', [
                'patient' => $this->patient,
                'appointments' => $appointments
            ]);
        } catch (PDOException $e) {
            error_log("Error in appointments: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your appointments.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * Book new appointment
     */
    public function book_appointment() {
        try {
            // If form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Validate and sanitize input
                $doctor_id = filter_var($_POST['doctor_id'], FILTER_SANITIZE_NUMBER_INT);
                $appointment_date = filter_var($_POST['appointment_date'], FILTER_SANITIZE_STRING);
                $reason = filter_var($_POST['reason'], FILTER_SANITIZE_STRING);
                
                // Validate required fields
                if (empty($doctor_id) || empty($appointment_date)) {
                    $_SESSION['error'] = "Please fill in all required fields.";
                    $this->redirect('index.php?module=patient&action=book_appointment');
                    return;
                }
                
                // Validate appointment date (must be in the future)
                $appointment_timestamp = strtotime($appointment_date);
                if ($appointment_timestamp < time()) {
                    $_SESSION['error'] = "Appointment date must be in the future.";
                    $this->redirect('index.php?module=patient&action=book_appointment');
                    return;
                }
                
                // Get doctor's consultation fee
                $stmt = $this->db->prepare("
                    SELECT consultation_fee FROM doctors WHERE id = ?
                ");
                $stmt->execute([$doctor_id]);
                $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Add payment_status column if it doesn't exist
                $stmt = $this->db->query("SHOW COLUMNS FROM appointments LIKE 'payment_status'");
                if ($stmt->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE appointments ADD COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending'");
                }
                
                // Insert appointment with explicit pending status
                $stmt = $this->db->prepare("
                    INSERT INTO appointments (
                        patient_id, 
                        doctor_id, 
                        appointment_date, 
                        reason, 
                        status,         -- Explicitly set status
                        payment_status,  -- Payment status
                        created_at      -- Creation timestamp
                    ) VALUES (
                        ?, ?, ?, ?, 
                        'pending',      -- Always start as pending, requires doctor confirmation
                        'pending',      -- Payment starts as pending
                        NOW()
                    )
                ");
                $stmt->execute([
                    $this->patient['id'],
                    $doctor_id,
                    $appointment_date,
                    $reason
                ]);
                
                $appointment_id = $this->db->lastInsertId();
                
                $_SESSION['success'] = "Appointment booked successfully. Please proceed with payment.";
                $this->redirect('index.php?module=patient&action=payment&type=appointment&id=' . $appointment_id);
                return;
        }

        // Get available doctors
        $stmt = $this->db->prepare("
                SELECT d.id, d.name, d.specialization, d.consultation_fee
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                WHERE u.status = 'active' AND d.availability_status = 'available'
                ORDER BY d.name
        ");
        $stmt->execute();
            $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Render the book appointment form
            $this->render('book_appointment', [
                'patient' => $this->patient,
                'doctors' => $doctors
            ]);
        } catch (PDOException $e) {
            error_log("Error in book_appointment: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading available doctors.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * View specific appointment
     */
    public function view_appointment() {
        try {
            // Get appointment ID from URL
            $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            
            if (empty($id)) {
                $_SESSION['error'] = "Invalid appointment ID.";
                $this->redirect('index.php?module=patient&action=appointments');
                return;
            }
            
            // Get appointment details
        $stmt = $this->db->prepare("
                SELECT a.*, d.name as doctor_name, d.specialization 
            FROM appointments a 
                JOIN doctors d ON a.doctor_id = d.id 
            WHERE a.id = ? AND a.patient_id = ?
        ");
            $stmt->execute([$id, $this->patient['id']]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
                $_SESSION['error'] = "Appointment not found or access denied.";
                $this->redirect('index.php?module=patient&action=appointments');
                return;
            }
            
            // Render the appointment view
            $this->render('view_appointment', [
                'patient' => $this->patient,
                'appointment' => $appointment
            ]);
        } catch (PDOException $e) {
            error_log("Error in view_appointment: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading the appointment details.";
            $this->redirect('index.php?module=patient&action=appointments');
        }
    }

    /**
     * Cancel an appointment
     */
    public function cancel_appointment() {
        try {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        
        if (!$id) {
            $_SESSION['error'] = "Invalid appointment ID";
                $this->redirect('index.php?module=patient&action=appointments');
        }

            $this->db->beginTransaction();

            // Verify appointment belongs to patient and can be cancelled
            $stmt = $this->db->prepare("
                SELECT status 
                FROM appointments 
                WHERE id = ? AND patient_id = ?
                AND status IN ('scheduled', 'confirmed')
            ");
            $stmt->execute([$id, $this->patient['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                throw new Exception("Appointment cannot be cancelled");
            }

            // Cancel the appointment
            $stmt = $this->db->prepare("
                UPDATE appointments 
                SET status = 'cancelled',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $this->db->commit();
            $_SESSION['success'] = "Appointment cancelled successfully";

        } catch (Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirect('index.php?module=patient&action=appointments');
    }

    /**
     * View prescriptions
     */
    public function prescriptions() {
        try {
            // Get all prescriptions for the patient
        $stmt = $this->db->prepare("
                SELECT p.*, d.name as doctor_name 
            FROM prescriptions p
                JOIN doctors d ON p.doctor_id = d.id 
            WHERE p.patient_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$this->patient['id']]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Render the prescriptions view
            $this->render('prescriptions', [
                'patient' => $this->patient,
                'prescriptions' => $prescriptions
            ]);
        } catch (PDOException $e) {
            error_log("Error in prescriptions: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your prescriptions.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * View specific prescription
     */
    public function view_prescription() {
        try {
            // Get prescription ID from URL
            $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
            
            if (empty($id)) {
                $_SESSION['error'] = "Invalid prescription ID.";
                $this->redirect('index.php?module=patient&action=prescriptions');
                return;
            }
            
            // Get prescription details
        $stmt = $this->db->prepare("
                SELECT p.*, d.name as doctor_name 
            FROM prescriptions p
                JOIN doctors d ON p.doctor_id = d.id 
            WHERE p.id = ? AND p.patient_id = ?
        ");
            $stmt->execute([$id, $this->patient['id']]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
                $_SESSION['error'] = "Prescription not found or access denied.";
                $this->redirect('index.php?module=patient&action=prescriptions');
                return;
            }
            
            // Get prescription medications
        $stmt = $this->db->prepare("
            SELECT * FROM prescription_items 
            WHERE prescription_id = ?
                ORDER BY id
            ");
            $stmt->execute([$id]);
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Render the prescription view
            $this->render('view_prescription', [
                'patient' => $this->patient,
                'prescription' => $prescription,
                'medications' => $medications
            ]);
        } catch (PDOException $e) {
            error_log("Error in view_prescription: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading the prescription details.";
            $this->redirect('index.php?module=patient&action=prescriptions');
        }
    }

    /**
     * Display medicine ordering page
     */
    public function order_medicine() {
        try {
            // Get available pharmacies
            $stmt = $this->db->prepare("
                SELECT id, name, address, phone, operating_hours, delivery_available
                FROM pharmacies
                WHERE status = 'active'
                ORDER BY name ASC
            ");
            $stmt->execute();
            $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient's prescriptions
            $stmt = $this->db->prepare("
                SELECT p.*, d.name as doctor_name
                FROM prescriptions p
                JOIN doctors d ON p.doctor_id = d.id
                WHERE p.patient_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$this->patient['id']]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient's previous orders
            $stmt = $this->db->prepare("
                SELECT mo.*, p.name as pharmacy_name
                FROM medicine_orders mo
                JOIN pharmacies p ON mo.pharmacy_id = p.id
                WHERE mo.patient_id = ?
                ORDER BY mo.created_at DESC
            ");
            $stmt->execute([$this->patient['id']]);
            $previous_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('order_medicine', [
                'page_title' => 'Order Medicine',
                'pharmacies' => $pharmacies,
                'prescriptions' => $prescriptions,
                'previous_orders' => $previous_orders
            ]);
            
        } catch (Exception $e) {
            $this->logError("Error loading medicine ordering page: " . $e->getMessage());
            $_SESSION['error'] = "Error loading medicine ordering page";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * Process medicine order
     */
    public function process_order_medicine() {
        try {
            // Validate input
            $pharmacy_id = intval($_POST['pharmacy_id'] ?? 0);
            $prescription_id = intval($_POST['prescription_id'] ?? 0);
            $delivery_address = trim($_POST['delivery_address'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            // Validate required fields
            if ($pharmacy_id <= 0) {
                $_SESSION['error'] = "Please select a pharmacy";
                $_SESSION['form_data'] = $_POST;
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // Check if pharmacy exists and is active
            $stmt = $this->db->prepare("
                SELECT id, delivery_available FROM pharmacies 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$pharmacy_id]);
            $pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pharmacy) {
                $_SESSION['error'] = "Selected pharmacy is not available";
                $_SESSION['form_data'] = $_POST;
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // If delivery is requested, ensure pharmacy offers delivery and address is provided
            $delivery_requested = isset($_POST['delivery_requested']) && $_POST['delivery_requested'] == 1;
            
            if ($delivery_requested) {
                if ($pharmacy['delivery_available'] != 1) {
                    $_SESSION['error'] = "Selected pharmacy does not offer delivery service";
                    $_SESSION['form_data'] = $_POST;
                    $this->redirect('index.php?module=patient&action=order_medicine');
                    return;
                }
                
                if (empty($delivery_address)) {
                    $_SESSION['error'] = "Delivery address is required";
                    $_SESSION['form_data'] = $_POST;
                    $this->redirect('index.php?module=patient&action=order_medicine');
                    return;
                }
            }
            
            // Generate order number
            $order_number = 'ORD-' . time() . '-' . $this->patient['id'];
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Create order
            $stmt = $this->db->prepare("
                INSERT INTO medicine_orders (
                    patient_id, pharmacy_id, prescription_id, order_number,
                    total_amount, payment_status, order_status,
                    delivery_address, notes
                ) VALUES (
                    ?, ?, ?, ?, 0.00, 'pending', 'pending', ?, ?
                )
            ");
            $stmt->execute([
                $this->patient['id'],
                $pharmacy_id,
                ($prescription_id > 0) ? $prescription_id : null,
                $order_number,
                $delivery_requested ? $delivery_address : null,
                $notes
            ]);
            
            $order_id = $this->db->lastInsertId();
            
            // If medicines were manually entered, add them to the order
            $total_order_amount = 0.00;
            if (isset($_POST['medicine']) && is_array($_POST['medicine'])) {
                $medicines = $_POST['medicine'];
                $quantities = $_POST['quantity'] ?? [];
                
                for ($i = 0; $i < count($medicines); $i++) {
                    if (!empty($medicines[$i])) {
                        $quantity = intval($quantities[$i] ?? 1);
                        
                        // Get medicine price from database
                        $stmt = $this->db->prepare("
                            SELECT price 
                            FROM medicines 
                            WHERE name = ? AND pharmacy_id = ? AND status = 'active'
                            LIMIT 1
                        ");
                        $stmt->execute([$medicines[$i], $pharmacy_id]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Use actual price from database or default price
                        $unit_price = $result ? floatval($result['price']) : 10.00;
                        $total_price = $quantity * $unit_price;
                        $total_order_amount += $total_price;

                        $stmt = $this->db->prepare("
                            INSERT INTO medicine_order_items (
                                order_id, medicine_id, medicine_name, quantity, unit_price, total_price
                            ) VALUES (
                                ?, NULL, ?, ?, ?, ?
                            )
                        ");
                        $stmt->execute([
                            $order_id,
                            $medicines[$i],
                            $quantity,
                            $unit_price,
                            $total_price
                        ]);
                    }
                }
            }
            
            // Update order total amount
            $stmt = $this->db->prepare("
                UPDATE medicine_orders 
                SET total_amount = ? 
                WHERE id = ?
            ");
            $stmt->execute([$total_order_amount, $order_id]);
            
            // Commit transaction
            $this->db->commit();
            
            $_SESSION['success'] = "Medicine order placed successfully. Please proceed with payment.";
            // Redirect to payment page instead of order details
            $this->redirect('index.php?module=patient&action=payment&type=medicine_order&id=' . $order_id);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            $this->logError("Error processing medicine order: " . $e->getMessage());
            $_SESSION['error'] = "Error processing medicine order: " . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            $this->redirect('index.php?module=patient&action=order_medicine');
        }
    }

    /**
     * Book a cab for hospital visit
     */
    public function book_cab() {
        try {
            // Check if cab_bookings table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'cab_bookings'");
            if ($stmt->rowCount() == 0) {
                // Create cab_bookings table
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `cab_bookings` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `patient_id` int(11) NOT NULL,
                      `provider_id` int(11) NULL,
                      `pickup_address` text NOT NULL,
                      `destination` text NOT NULL,
                      `pickup_datetime` datetime NOT NULL,
                      `cab_type` enum('standard','wheelchair','stretcher') NOT NULL DEFAULT 'standard',
                      `special_requirements` text DEFAULT NULL,
                      `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
                      `created_at` datetime NOT NULL,
                      `updated_at` datetime DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `patient_id` (`patient_id`),
                      KEY `provider_id` (`provider_id`),
                      CONSTRAINT `cab_bookings_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
                      CONSTRAINT `cab_bookings_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                error_log("Created cab_bookings table");
            } else {
                // Check if provider_id column exists
                $stmt = $this->db->query("SHOW COLUMNS FROM cab_bookings LIKE 'provider_id'");
                if ($stmt->rowCount() == 0) {
                    // Add provider_id column
                    $this->db->exec("ALTER TABLE cab_bookings ADD COLUMN provider_id INT(11) NULL AFTER patient_id, ADD KEY `provider_id` (`provider_id`), ADD CONSTRAINT `cab_bookings_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE SET NULL");
                    error_log("Added provider_id column to cab_bookings table");
                }
            }
            
            // Check if hospitals table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'hospitals'");
            if ($stmt->rowCount() == 0) {
                // Create hospitals table
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `hospitals` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(100) NOT NULL,
                      `address` text NOT NULL,
                      `phone` varchar(20) NOT NULL,
                      `email` varchar(100) DEFAULT NULL,
                      `website` varchar(100) DEFAULT NULL,
                      `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                      `created_at` datetime NOT NULL,
                      `updated_at` datetime DEFAULT NULL,
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
                
                // Insert sample hospitals
                $this->db->exec("
                    INSERT INTO `hospitals` (`name`, `address`, `phone`, `email`, `website`, `status`, `created_at`) VALUES
                    ('City General Hospital', '123 Main Street, City Center', '555-1234', 'info@citygeneral.com', 'www.citygeneral.com', 'active', NOW()),
                    ('Memorial Medical Center', '456 Park Avenue, Downtown', '555-5678', 'contact@memorialmed.com', 'www.memorialmed.com', 'active', NOW()),
                    ('St. John\'s Hospital', '789 Oak Road, Westside', '555-9012', 'info@stjohns.com', 'www.stjohns.com', 'active', NOW())
                ");
                
                error_log("Created hospitals table and inserted sample data");
            }
            
            // If form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Validate and sanitize input
                $pickup_address = filter_var($_POST['pickup_address'], FILTER_SANITIZE_STRING);
                $destination = filter_var($_POST['destination'], FILTER_SANITIZE_STRING);
                $pickup_time = filter_var($_POST['pickup_time'], FILTER_SANITIZE_STRING);
                $pickup_date = filter_var($_POST['pickup_date'], FILTER_SANITIZE_STRING);
                $cab_type = filter_var($_POST['cab_type'], FILTER_SANITIZE_STRING);
                $special_requirements = filter_var($_POST['special_requirements'], FILTER_SANITIZE_STRING);
                $provider_id = isset($_POST['provider_id']) ? filter_var($_POST['provider_id'], FILTER_SANITIZE_NUMBER_INT) : null;
                
                // Validate required fields
                if (empty($pickup_address) || empty($destination) || empty($pickup_time) || empty($pickup_date) || empty($cab_type) || empty($provider_id)) {
                    $_SESSION['error'] = "Please fill in all required fields including selecting a transportation provider.";
                    $this->redirect('index.php?module=patient&action=book_cab');
                    return;
                }
                
                // Validate pickup date and time (must be in the future)
                $pickup_datetime = strtotime($pickup_date . ' ' . $pickup_time);
                if ($pickup_datetime < time()) {
                    $_SESSION['error'] = "Pickup date and time must be in the future.";
                    $this->redirect('index.php?module=patient&action=book_cab');
                    return;
                }
                
                // Create cab booking
                $stmt = $this->db->prepare("
                    INSERT INTO cab_bookings (
                        patient_id, provider_id, pickup_address, destination, 
                        pickup_datetime, cab_type, special_requirements, 
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $this->patient['id'],
                    $provider_id,
                    $pickup_address, 
                    $destination, 
                    date('Y-m-d H:i:s', $pickup_datetime), 
                    $cab_type, 
                    $special_requirements
                ]);
                
                $booking_id = $this->db->lastInsertId();
                
                // Calculate estimated fare based on cab type
                $base_fare = 0;
                switch ($cab_type) {
                    case 'standard':
                        $base_fare = 150.00;
                        break;
                    case 'wheelchair':
                        $base_fare = 250.00;
                        break;
                    case 'stretcher':
                        $base_fare = 350.00;
                        break;
                }
                
                // Add estimated_fare column if it doesn't exist
                $stmt = $this->db->query("SHOW COLUMNS FROM cab_bookings LIKE 'estimated_fare'");
                if ($stmt->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE cab_bookings ADD COLUMN estimated_fare DECIMAL(10,2) DEFAULT 0.00");
                }
                
                // Add payment_status column if it doesn't exist
                $stmt = $this->db->query("SHOW COLUMNS FROM cab_bookings LIKE 'payment_status'");
                if ($stmt->rowCount() == 0) {
                    $this->db->exec("ALTER TABLE cab_bookings ADD COLUMN payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending'");
                }
                
                // Update booking with estimated fare
                $stmt = $this->db->prepare("
                    UPDATE cab_bookings 
                    SET estimated_fare = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$base_fare, $booking_id]);
                
                $_SESSION['success'] = "Cab booked successfully. Please proceed with payment.";
                $this->redirect('index.php?module=patient&action=payment&type=cab_booking&id=' . $booking_id);
                return;
            }
            
            // Get hospital addresses for destination options
            $stmt = $this->db->prepare("
                SELECT id, name, address 
                FROM hospitals 
                WHERE status = 'active'
                ORDER BY name
            ");
            $stmt->execute();
            $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get list of transportation service providers
            $stmt = $this->db->prepare("
                SELECT sp.id, sp.company_name, sp.phone, sp.address, sp.operating_hours, sp.service_area
                FROM service_providers sp
                WHERE sp.service_type IN ('transportation', 'both')
                AND sp.status = 'active'
                ORDER BY sp.company_name
            ");
            $stmt->execute();
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Book Cab - Patient: " . print_r($this->patient, true));
            error_log("Book Cab - Hospitals: " . print_r($hospitals, true));
            error_log("Book Cab - Providers: " . print_r($providers, true));
            
            // Render the book cab form
            $this->render('book_cab', [
                'patient' => $this->patient,
                'hospitals' => $hospitals,
                'providers' => $providers
            ]);
        } catch (PDOException $e) {
            error_log("Error in book_cab: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while processing your request.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * View patient profile
     */
    public function profile() {
        try {
            // Get patient details with user information
            $stmt = $this->db->prepare("
                SELECT p.*, u.email, u.status as user_status
                FROM patients p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$this->patient['id']]);
            $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient_details) {
                $_SESSION['error'] = "Patient profile not found.";
                $this->redirect('index.php?module=patient&action=dashboard');
                return;
            }
            
            // Render the profile view
            $this->render('profile', [
                'patient' => $patient_details
            ]);
        } catch (PDOException $e) {
            error_log("Error in profile: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your profile.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * Edit patient profile
     */
    public function edit_profile() {
        try {
            // If form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Validate and sanitize input
                $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
                $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
                $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
                $dob = filter_var($_POST['dob'], FILTER_SANITIZE_STRING);
                $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
                $blood_group = filter_var($_POST['blood_group'], FILTER_SANITIZE_STRING);
                $emergency_contact = filter_var($_POST['emergency_contact'], FILTER_SANITIZE_STRING);
                $medical_history = filter_var($_POST['medical_history'], FILTER_SANITIZE_STRING);
                
                // Validate required fields
                if (empty($name) || empty($phone)) {
                    $_SESSION['error'] = "Name and phone number are required.";
                    $this->redirect('index.php?module=patient&action=edit_profile');
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
                        $this->redirect('index.php?module=patient&action=edit_profile');
                        return;
                    }
                    
                    // Validate file size
                    if ($_FILES['profile_image']['size'] > $max_size) {
                        $_SESSION['error'] = "Image size should not exceed 2MB.";
                        $this->redirect('index.php?module=patient&action=edit_profile');
                        return;
                    }
                    
                    // Create uploads directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../../uploads/profile_images/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'patient_' . $this->patient['id'] . '_' . time() . '.' . $file_extension;
                    $target_file = $upload_dir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                        $profile_image = 'uploads/profile_images/' . $filename;
                        
                        // Delete old profile image if exists
                        if (!empty($this->patient['profile_image']) && file_exists(__DIR__ . '/../../../' . $this->patient['profile_image'])) {
                            unlink(__DIR__ . '/../../../' . $this->patient['profile_image']);
                        }
                    } else {
                        $_SESSION['error'] = "Failed to upload image. Please try again.";
                        $this->redirect('index.php?module=patient&action=edit_profile');
                        return;
                    }
                }
                
                // Check if patients table has profile_image column
                $stmt = $this->db->query("SHOW COLUMNS FROM patients LIKE 'profile_image'");
                if ($stmt->rowCount() === 0) {
                    // Add profile_image column to patients table
                    $this->db->exec("ALTER TABLE patients ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
                }
                
                // Update patient details
                $sql = "
                    UPDATE patients SET
                        name = ?,
                        phone = ?,
                        address = ?,
                        dob = ?,
                        gender = ?,
                        blood_group = ?,
                        emergency_contact = ?,
                        medical_history = ?,
                        updated_at = NOW()
                ";
                $params = [
                    $name,
                    $phone,
                    $address,
                    $dob,
                    $gender,
                    $blood_group,
                    $emergency_contact,
                    $medical_history
                ];
                
                // Add profile_image to update if uploaded
                if ($profile_image !== null) {
                    $sql .= ", profile_image = ?";
                    $params[] = $profile_image;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $this->patient['id'];
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                
                // Update session data
                $this->patient['name'] = $name;
                $this->patient['phone'] = $phone;
                $this->patient['address'] = $address;
                $this->patient['dob'] = $dob;
                $this->patient['gender'] = $gender;
                $this->patient['blood_group'] = $blood_group;
                $this->patient['emergency_contact'] = $emergency_contact;
                $this->patient['medical_history'] = $medical_history;
                if ($profile_image !== null) {
                    $this->patient['profile_image'] = $profile_image;
                    $_SESSION['profile_image'] = $profile_image;
                }
                
                $_SESSION['success'] = "Profile updated successfully.";
                $this->redirect('index.php?module=patient&action=profile');
                return;
            }
            
            // Get patient details with user information
            $stmt = $this->db->prepare("
                SELECT p.*, u.email, u.status as user_status
                FROM patients p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$this->patient['id']]);
            $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$patient_details) {
                $_SESSION['error'] = "Patient profile not found.";
                $this->redirect('index.php?module=patient&action=dashboard');
                return;
            }
            
            // Render the edit profile form
            $this->render('edit_profile', [
                'patient' => $patient_details
            ]);
        } catch (PDOException $e) {
            error_log("Error in edit_profile: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while updating your profile.";
            $this->redirect('index.php?module=patient&action=profile');
        }
    }

    /**
     * View patient health records
     */
    public function health_records() {
        try {
            // Get medical history records for the patient
            $stmt = $this->db->prepare("
                SELECT mh.*, u.name as recorded_by_name
                FROM medical_history mh
                LEFT JOIN users u ON mh.recorded_by = u.id
                WHERE mh.patient_id = ?
                ORDER BY mh.recorded_date DESC
            ");
            $stmt->execute([$this->patient['id']]);
            $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient details
            $stmt = $this->db->prepare("
                SELECT p.*, u.email
                FROM patients p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$this->patient['id']]);
            $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Render the health records view
            $this->render('health_records', [
                'patient' => $patient_details,
                'medical_records' => $medical_records
            ]);
        } catch (PDOException $e) {
            error_log("Error in health_records: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your health records.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * View medicine order details
     */
    public function view_medicine_order() {
        try {
            $order_id = intval($_GET['id'] ?? 0);
            
            if ($order_id <= 0) {
                $_SESSION['error'] = "Invalid order ID";
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // Get order details
            $stmt = $this->db->prepare("
                SELECT mo.*, p.name as pharmacy_name, p.phone as pharmacy_phone,
                       p.address as pharmacy_address, pr.diagnosis as prescription_diagnosis
                FROM medicine_orders mo
                JOIN pharmacies p ON mo.pharmacy_id = p.id
                LEFT JOIN prescriptions pr ON mo.prescription_id = pr.id
                WHERE mo.id = ? AND mo.patient_id = ?
            ");
            $stmt->execute([$order_id, $this->patient['id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $_SESSION['error'] = "Order not found or you do not have permission to view it";
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // Get order items
            $stmt = $this->db->prepare("
                SELECT * FROM medicine_order_items
                WHERE order_id = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->render('view_medicine_order', [
                'page_title' => 'Order Details',
                'order' => $order,
                'items' => $items
            ]);
            
        } catch (Exception $e) {
            $this->logError("Error loading order details: " . $e->getMessage());
            $_SESSION['error'] = "Error loading order details";
            $this->redirect('index.php?module=patient&action=order_medicine');
        }
    }
    
    /**
     * Cancel medicine order
     */
    public function cancel_medicine_order() {
        try {
            $order_id = intval($_GET['id'] ?? 0);
            
            if ($order_id <= 0) {
                $_SESSION['error'] = "Invalid order ID";
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // Verify order belongs to this patient and is in a cancellable state
            $stmt = $this->db->prepare("
                SELECT id, order_status FROM medicine_orders
                WHERE id = ? AND patient_id = ? AND order_status IN ('pending', 'processing')
            ");
            $stmt->execute([$order_id, $this->patient['id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $_SESSION['error'] = "Order not found, already processed, or you do not have permission to cancel it";
                $this->redirect('index.php?module=patient&action=order_medicine');
                return;
            }
            
            // Update order status
            $stmt = $this->db->prepare("
                UPDATE medicine_orders
                SET order_status = 'cancelled', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            
            $_SESSION['success'] = "Order cancelled successfully";
            $this->redirect('index.php?module=patient&action=order_medicine');
            
        } catch (Exception $e) {
            $this->logError("Error cancelling order: " . $e->getMessage());
            $_SESSION['error'] = "Error cancelling order";
            $this->redirect('index.php?module=patient&action=order_medicine');
        }
    }

    /**
     * Get medicine price
     */
    public function get_medicine_price() {
        try {
            // Get medicine name from request
            $medicine_name = trim($_GET['medicine'] ?? '');
            
            if (empty($medicine_name)) {
                http_response_code(400);
                echo json_encode(['error' => 'Medicine name is required']);
                return;
            }
            
            // Get pharmacy ID from request
            $pharmacy_id = intval($_GET['pharmacy_id'] ?? 0);
            
            if ($pharmacy_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Pharmacy ID is required']);
                return;
            }
            
            // Get medicine price from database
            $stmt = $this->db->prepare("
                SELECT price 
                FROM medicines 
                WHERE name = ? AND pharmacy_id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$medicine_name, $pharmacy_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If medicine not found, return default price
            $price = $result ? floatval($result['price']) : 10.00;
            
            // Return price as JSON
            header('Content-Type: application/json');
            echo json_encode(['price' => $price]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error fetching medicine price']);
        }
    }

    /**
     * Show payment page
     */
    public function payment() {
        try {
            // Initialize PaymentController
            require_once __DIR__ . '/controllers/PaymentController.php';
            $paymentController = new PaymentController($this->db, $this->patient);
            
            // Show payment page
            $paymentController->showPaymentPage();
            
        } catch (Exception $e) {
            $this->logError("Error showing payment page: " . $e->getMessage());
            $_SESSION['error'] = "Error showing payment page: " . $e->getMessage();
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * Process payment
     */
    public function process_payment() {
        try {
            // Initialize PaymentController
            require_once __DIR__ . '/controllers/PaymentController.php';
            $paymentController = new PaymentController($this->db, $this->patient);
            
            // Process payment but keep appointment status as pending
            $type = $_GET['type'] ?? '';
            if ($type === 'appointment') {
                // Begin transaction
                $this->db->beginTransaction();
                
                try {
                    // Update only payment_status, keep appointment status as pending
                    $stmt = $this->db->prepare("
                        UPDATE appointments 
                        SET payment_status = 'paid'
                        WHERE id = ? AND patient_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$_GET['id'], $this->patient['id']]);
                    
                    $this->db->commit();
                    $_SESSION['success'] = "Payment successful. Your appointment is pending doctor's confirmation.";
                } catch (Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            } else {
                // Handle other payment types
                $paymentController->processPayment();
            }
            
        } catch (Exception $e) {
            $this->logError("Error processing payment: " . $e->getMessage());
            $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * Show payment history
     */
    public function payment_history() {
        try {
            // Initialize PaymentController
            require_once __DIR__ . '/controllers/PaymentController.php';
            $paymentController = new PaymentController($this->db, $this->patient);
            
            // Show payment history
            $paymentController->showPaymentHistory();
            
        } catch (Exception $e) {
            $this->logError("Error showing payment history: " . $e->getMessage());
            $_SESSION['error'] = "Error showing payment history: " . $e->getMessage();
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * Show symptom search page
     */
    public function symptom_search() {
        try {
            // Check if symptoms table exists
            $tableExists = false;
            try {
                $stmt = $this->db->prepare("SELECT 1 FROM symptoms LIMIT 1");
                $stmt->execute();
                $tableExists = true;
            } catch (PDOException $e) {
                // Table doesn't exist
                $tableExists = false;
            }
            
            // If tables don't exist, try to create them and import sample data
            if (!$tableExists) {
                try {
                    // Create tables if they don't exist
                    $this->db->exec("
                        CREATE TABLE IF NOT EXISTS `symptoms` (
                          `id` int NOT NULL AUTO_INCREMENT,
                          `name` varchar(255) NOT NULL,
                          `description` text,
                          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `name` (`name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                        
                        CREATE TABLE IF NOT EXISTS `diseases` (
                          `id` int NOT NULL AUTO_INCREMENT,
                          `name` varchar(255) NOT NULL,
                          `description` text,
                          `treatment` text,
                          `severity_level` enum('low','medium','high') DEFAULT 'medium',
                          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `name` (`name`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                        
                        CREATE TABLE IF NOT EXISTS `disease_symptoms` (
                          `id` int NOT NULL AUTO_INCREMENT,
                          `disease_id` int NOT NULL,
                          `symptom_id` int NOT NULL,
                          `severity` enum('mild','moderate','severe') DEFAULT 'moderate',
                          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `disease_symptom_unique` (`disease_id`,`symptom_id`),
                          KEY `symptom_id` (`symptom_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                        
                        CREATE TABLE IF NOT EXISTS `disease_specializations` (
                          `id` int NOT NULL AUTO_INCREMENT,
                          `disease_id` int NOT NULL,
                          `specialization` varchar(255) NOT NULL,
                          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `disease_specialization_unique` (`disease_id`,`specialization`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                        
                        CREATE TABLE IF NOT EXISTS `symptom_search_history` (
                          `id` int NOT NULL AUTO_INCREMENT,
                          `patient_id` int NOT NULL,
                          `symptoms` text NOT NULL,
                          `diseases_found` text,
                          `recommended_doctors` text,
                          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          KEY `patient_id` (`patient_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                    ");
                    
                    // Add foreign key constraints
                    $this->db->exec("
                        ALTER TABLE `disease_symptoms`
                          ADD CONSTRAINT `disease_symptoms_ibfk_1` FOREIGN KEY (`disease_id`) REFERENCES `diseases` (`id`) ON DELETE CASCADE,
                          ADD CONSTRAINT `disease_symptoms_ibfk_2` FOREIGN KEY (`symptom_id`) REFERENCES `symptoms` (`id`) ON DELETE CASCADE;
                        
                        ALTER TABLE `disease_specializations`
                          ADD CONSTRAINT `disease_specializations_ibfk_1` FOREIGN KEY (`disease_id`) REFERENCES `diseases` (`id`) ON DELETE CASCADE;
                        
                        ALTER TABLE `symptom_search_history`
                          ADD CONSTRAINT `symptom_search_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
                    ");
                    
                    // Import sample data
                    $sampleDataPath = __DIR__ . '/../../sql/sample_symptoms_diseases.sql';
                    if (file_exists($sampleDataPath)) {
                        $sql = file_get_contents($sampleDataPath);
                        $this->db->exec($sql);
                        $_SESSION['success'] = "Symptom search functionality has been set up with sample data.";
                    }
                } catch (PDOException $e) {
                    error_log("Error creating symptom tables: " . $e->getMessage());
                    // Continue with empty symptoms
                }
            }
            
            // Check if symptoms table has data
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM symptoms");
            $stmt->execute();
            $symptomCount = $stmt->fetchColumn();
            
            if ($symptomCount == 0) {
                // No symptoms in database, try to import sample data
                try {
                    $sampleDataPath = __DIR__ . '/../../sql/sample_symptoms_diseases.sql';
                    if (file_exists($sampleDataPath)) {
                        $sql = file_get_contents($sampleDataPath);
                        $this->db->exec($sql);
                        $_SESSION['success'] = "Sample symptom and disease data has been imported.";
                    }
                } catch (PDOException $e) {
                    error_log("Error importing sample data: " . $e->getMessage());
                    // Continue with empty symptoms
                }
            }
            
            // Get common symptoms for autocomplete
            $stmt = $this->db->prepare("
                SELECT name, description 
                FROM symptoms 
                ORDER BY name 
                LIMIT 50
            ");
            $stmt->execute();
            $common_symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get patient's recent symptom searches
            $stmt = $this->db->prepare("
                SELECT * FROM symptom_search_history 
                WHERE patient_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$this->patient['id']]);
            $recent_searches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Render the symptom search view
            $this->render('symptom_search', [
                'patient' => $this->patient,
                'common_symptoms' => $common_symptoms,
                'recent_searches' => $recent_searches
            ]);
        } catch (PDOException $e) {
            error_log("Error in symptom_search: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading the symptom search page.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }

    /**
     * Display cab bookings for patient
     */
    public function cab_bookings() {
        // Check if user is logged in
        if (!$this->isAuthenticated()) {
            $_SESSION['error'] = "Please log in to view your bookings.";
            $this->redirect('index.php?module=auth&action=login&type=patient');
        }
        
        try {
            // Get all cab bookings for the patient
            $stmt = $this->db->prepare("
                SELECT cb.*, sp.company_name as provider_name 
                FROM cab_bookings cb 
                LEFT JOIN service_providers sp ON cb.provider_id = sp.id 
                WHERE cb.patient_id = ?
                ORDER BY cb.pickup_datetime DESC
            ");
            $stmt->execute([$this->patient['id']]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates and prepare data for view
            foreach ($bookings as &$booking) {
                if (isset($booking['pickup_datetime'])) {
                    $pickup_datetime = new DateTime($booking['pickup_datetime']);
                    $booking['pickup_date'] = $pickup_datetime->format('Y-m-d');
                    $booking['pickup_time'] = $pickup_datetime->format('H:i:s');
                    $booking['formatted_date'] = $pickup_datetime->format('M j, Y');
                    $booking['formatted_time'] = $pickup_datetime->format('g:i A');
                }
            }
            
            // Render the cab bookings view
            $this->render('cab_bookings', [
                'patient' => $this->patient,
                'bookings' => $bookings
            ]);
        } catch (PDOException $e) {
            error_log("Error in cab_bookings: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while loading your cab bookings.";
            $this->redirect('index.php?module=patient&action=dashboard');
        }
    }
    
    /**
     * View cab booking details
     */
    public function view_cab_booking() {
        // Check if user is logged in
        if (!$this->isAuthenticated()) {
            $_SESSION['error'] = "Please log in to view booking details.";
            $this->redirect('index.php?module=auth&action=login&type=patient');
        }
        
        // Get booking ID
        $booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$booking_id) {
            $_SESSION['error'] = 'Invalid booking ID.';
            $this->redirect('index.php?module=patient&action=cab_bookings');
        }
        
        try {
            // Get booking details
            $stmt = $this->db->prepare("
                SELECT cb.*, sp.company_name as provider_name, sp.phone as provider_phone
                FROM cab_bookings cb
                LEFT JOIN service_providers sp ON cb.provider_id = sp.id
                WHERE cb.id = ? AND cb.patient_id = ?
            ");
            $stmt->execute([$booking_id, $this->patient['id']]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                $_SESSION['error'] = 'Booking not found or you do not have permission to view it.';
                $this->redirect('index.php?module=patient&action=cab_bookings');
            }
            
            // Format pickup date and time from pickup_datetime
            if (isset($booking['pickup_datetime'])) {
                $pickup_datetime = new DateTime($booking['pickup_datetime']);
                $booking['pickup_date'] = $pickup_datetime->format('Y-m-d');
                $booking['pickup_time'] = $pickup_datetime->format('H:i:s');
            }
            
            // Load view
            $this->render('view_cab_booking', [
                'patient' => $this->patient,
                'booking' => $booking
            ]);
        } catch (Exception $e) {
            $this->logError('Error viewing cab booking: ' . $e->getMessage());
            $_SESSION['error'] = 'An error occurred while retrieving booking details.';
            $this->redirect('index.php?module=patient&action=cab_bookings');
        }
    }
    
    /**
     * Cancel cab booking
     */
    public function cancel_cab_booking() {
        try {
            if (!isset($_GET['id'])) {
                throw new Exception("Booking ID not provided");
            }
            
            $booking_id = (int)$_GET['id'];
            
            // Check if booking exists and belongs to the patient
            $stmt = $this->db->prepare("
                SELECT id, status
                FROM cab_bookings
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->execute([$booking_id, $this->patient['id']]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Booking not found or you don't have permission to cancel it");
            }
            
            if ($booking['status'] == 'cancelled' || $booking['status'] == 'completed') {
                throw new Exception("Cannot cancel a booking that is already " . $booking['status']);
            }
            
            // Update booking status to cancelled
            $stmt = $this->db->prepare("
                UPDATE cab_bookings
                SET status = 'cancelled', cancelled_at = NOW()
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->execute([$booking_id, $this->patient['id']]);
            
            $_SESSION['success'] = "Cab booking cancelled successfully";
            $this->redirect('index.php?module=patient&action=cab_bookings');
        } catch (Exception $e) {
            error_log("Error in cancel_cab_booking: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('index.php?module=patient&action=cab_bookings');
        }
    }
}
