<?php
/**
 * Database Structure Update Script
 * This will update the service_requests table to fix column names
 */

// Set content type to plain text
header('Content-Type: text/plain');
echo "Starting database structure update...\n\n";

// Include database connection
require_once 'classes/Database.php';

try {
    $db = Database::getInstance();
    echo "Connected to database successfully.\n";
    
    // 1. Check for service_provider_id column
    $updated = false;
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'service_provider_id'");
    if ($stmt->rowCount() == 0) {
        // Check if provider_id column exists
        $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'provider_id'");
        if ($stmt->rowCount() > 0) {
            // Rename the column
            $db->exec("ALTER TABLE service_requests CHANGE COLUMN provider_id service_provider_id INT NOT NULL");
            echo "SUCCESS: Renamed column 'provider_id' to 'service_provider_id' in service_requests table.\n";
            $updated = true;
        } else {
            echo "WARNING: Neither 'provider_id' nor 'service_provider_id' column found. Adding 'service_provider_id'...\n";
            $db->exec("ALTER TABLE service_requests ADD COLUMN service_provider_id INT NOT NULL AFTER patient_id");
            echo "SUCCESS: Added 'service_provider_id' column to service_requests table.\n";
            $updated = true;
        }
    } else {
        echo "SKIPPED: Column 'service_provider_id' already exists in service_requests table.\n";
    }
    
    // 2. Check for request_type column
    $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE 'request_type'");
    if ($stmt->rowCount() == 0) {
        // Add the column
        $db->exec("ALTER TABLE service_requests ADD COLUMN request_type VARCHAR(50) NOT NULL DEFAULT 'medicine_delivery' AFTER service_provider_id");
        
        // Update values based on service_type
        $db->exec("UPDATE service_requests SET request_type = 'medicine_delivery' WHERE service_type = 'medicine'");
        $db->exec("UPDATE service_requests SET request_type = 'equipment_rental' WHERE service_type = 'equipment'");
        $db->exec("UPDATE service_requests SET request_type = 'cab_booking' WHERE service_type = 'cab'");
        
        echo "SUCCESS: Added 'request_type' column to service_requests table and updated values.\n";
        $updated = true;
    } else {
        echo "SKIPPED: Column 'request_type' already exists in service_requests table.\n";
    }
    
    // 3. Check for additional required columns
    $columns_to_add = [
        'medicine_order_id' => 'INT NULL',
        'delivery_address' => 'TEXT NULL',
        'request_details' => 'TEXT NULL',
        'scheduled_date' => 'DATETIME NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        $stmt = $db->query("SHOW COLUMNS FROM service_requests LIKE '$column'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE service_requests ADD COLUMN $column $definition");
            echo "SUCCESS: Added '$column' column to service_requests table.\n";
            $updated = true;
        } else {
            echo "SKIPPED: Column '$column' already exists in service_requests table.\n";
        }
    }
    
    if (!$updated) {
        echo "\nNo changes were necessary. Database structure is already up to date.\n";
    } else {
        echo "\nDatabase structure updated successfully!\n";
    }
    
    echo "\nTesting query for service requests...\n";
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM service_requests 
        WHERE service_provider_id = 1 
        AND request_type = 'medicine_delivery'
    ");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Found $count medicine delivery service requests for provider ID 1.\n";
    
    echo "\nUpdate complete!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 