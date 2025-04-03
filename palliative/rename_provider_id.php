<?php
// Include database connection
include 'classes/Database.php';

// Set content type to plain text
header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    
    // Check if we need to rename the column
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'service_provider_id'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'service_provider_id' already exists in the service_requests table.\n";
    } else {
        // Check if provider_id column exists
        $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'provider_id'");
        if ($stmt->rowCount() > 0) {
            // Rename the column
            $db->exec("ALTER TABLE service_requests CHANGE COLUMN provider_id service_provider_id INT NOT NULL");
            echo "Column 'provider_id' renamed to 'service_provider_id' in the service_requests table.\n";
        } else {
            // Add the column if it doesn't exist
            $db->exec("ALTER TABLE service_requests ADD COLUMN service_provider_id INT NOT NULL AFTER patient_id");
            echo "Column 'service_provider_id' added to the service_requests table.\n";
        }
    }
    
    // Add column medicine_order_id if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'medicine_order_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN medicine_order_id INT NULL");
        echo "Column 'medicine_order_id' added to the service_requests table.\n";
    }
    
    // Add column request_type if it doesn't match the code expectations
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'request_type'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT 'medicine_delivery' AFTER service_provider_id");
        echo "Column 'request_type' added to the service_requests table.\n";
    }
    
    // Add column delivery_address if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'delivery_address'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN delivery_address TEXT NULL");
        echo "Column 'delivery_address' added to the service_requests table.\n";
    }
    
    // Add column request_details if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'request_details'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN request_details TEXT NULL");
        echo "Column 'request_details' added to the service_requests table.\n";
    }
    
    // Add column scheduled_date if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'scheduled_date'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN scheduled_date DATETIME NULL");
        echo "Column 'scheduled_date' added to the service_requests table.\n";
    }
    
    echo "\nDone!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 