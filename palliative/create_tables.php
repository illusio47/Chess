<?php
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    // Create doctors table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        specialization VARCHAR(100),
        phone VARCHAR(20),
        gender ENUM('male', 'female', 'other'),
        dob DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email (email),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "Doctors table created successfully<br>";

    // Create appointments table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        reason TEXT,
        status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )");
    echo "Appointments table created successfully<br>";

    // Create prescriptions table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        diagnosis TEXT,
        medications TEXT,
        instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )");
    echo "Prescriptions table created successfully<br>";

    // Create a test doctor if none exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM doctors");
    $stmt->execute();
    $doctorCount = $stmt->fetchColumn();

    if ($doctorCount == 0) {
        // First create a user record for the doctor
        $hash = password_hash('doctor123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, user_type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['doctor@example.com', $hash, 'doctor']);
        $doctorUserId = $db->lastInsertId();

        // Then create the doctor record
        $stmt = $db->prepare("INSERT INTO doctors (user_id, name, email, specialization, phone, gender, dob) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $doctorUserId,
            'Dr. John Smith',
            'doctor@example.com',
            'General Medicine',
            '1234567890',
            'male',
            '1980-01-01'
        ]);
        echo "Test doctor created successfully<br>";
    }

    // Create medicine_orders table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `medicine_orders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `prescription_id` int(11) NOT NULL,
          `delivery_address` text NOT NULL,
          `contact_number` varchar(20) NOT NULL,
          `payment_method` enum('cash','card','insurance') NOT NULL DEFAULT 'cash',
          `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `patient_id` (`patient_id`),
          KEY `prescription_id` (`prescription_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "Created medicine_orders table.\n";
    
    // Create cab_bookings table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `cab_bookings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `pickup_address` text NOT NULL,
          `destination` text NOT NULL,
          `pickup_datetime` datetime NOT NULL,
          `cab_type` enum('standard','wheelchair','stretcher') NOT NULL DEFAULT 'standard',
          `special_requirements` text DEFAULT NULL,
          `status` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `patient_id` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "Created cab_bookings table.\n";
    
    // Create hospitals table
    $db->exec("
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
    
    echo "Created hospitals table.\n";
    
    // Insert sample hospitals
    $db->exec("
        INSERT INTO `hospitals` (`name`, `address`, `phone`, `email`, `website`, `status`, `created_at`) VALUES
        ('City General Hospital', '123 Main Street, City Center', '555-1234', 'info@citygeneral.com', 'www.citygeneral.com', 'active', NOW()),
        ('Memorial Medical Center', '456 Park Avenue, Downtown', '555-5678', 'contact@memorialmed.com', 'www.memorialmed.com', 'active', NOW()),
        ('St. John\'s Hospital', '789 Oak Road, Westside', '555-9012', 'info@stjohns.com', 'www.stjohns.com', 'active', NOW())
        ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
    ");
    
    echo "Inserted sample hospitals.\n";
    
    // Check if prescriptions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'prescriptions'");
    if ($stmt->rowCount() > 0) {
        echo "Prescriptions table exists.\n";
        
        // Check if there are any active prescriptions
        $stmt = $db->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'active'");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            echo "No active prescriptions found. Creating sample prescriptions...\n";
            
            // Get a patient ID
            $stmt = $db->query("SELECT id FROM patients LIMIT 1");
            $patient_id = $stmt->fetchColumn();
            
            // Get a doctor ID
            $stmt = $db->query("SELECT id FROM doctors LIMIT 1");
            $doctor_id = $stmt->fetchColumn();
            
            if ($patient_id && $doctor_id) {
                // Create sample prescriptions
                $db->exec("
                    INSERT INTO `prescriptions` (
                        `patient_id`, `doctor_id`, `prescription_date`, 
                        `diagnosis`, `notes`, `status`, `created_at`
                    ) VALUES 
                    ({$patient_id}, {$doctor_id}, NOW(), 'Hypertension', 'Take medication as prescribed', 'active', NOW()),
                    ({$patient_id}, {$doctor_id}, NOW(), 'Diabetes Type 2', 'Monitor blood sugar levels', 'active', NOW())
                ");
                
                echo "Created sample prescriptions.\n";
            } else {
                echo "Could not find patient or doctor IDs for sample prescriptions.\n";
            }
        } else {
            echo "Found {$count} active prescriptions.\n";
        }
    } else {
        echo "Prescriptions table does not exist.\n";
    }
    
    echo "All tables created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 