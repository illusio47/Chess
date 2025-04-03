<?php
// Include database connection
include 'classes/Database.php';

// Set content type to plain text
header('Content-Type: text/plain');

try {
    $db = Database::getInstance();
    
    // Check if the column exists
    $columnExists = false;
    $stmt = $db->query("SHOW COLUMNS FROM pharmacies LIKE 'service_provider_id'");
    if ($stmt->rowCount() > 0) {
        $columnExists = true;
        echo "Column 'service_provider_id' already exists in the pharmacies table.\n";
    }
    
    if (!$columnExists) {
        // Add the column
        $db->exec("ALTER TABLE pharmacies ADD COLUMN service_provider_id INT NULL AFTER user_id");
        $db->exec("ALTER TABLE pharmacies ADD INDEX (service_provider_id)");
        
        // Update existing records - link service providers based on user_id
        $stmt = $db->prepare("
            UPDATE pharmacies p
            JOIN service_providers sp ON p.user_id = sp.user_id
            SET p.service_provider_id = sp.id
            WHERE sp.service_type IN ('pharmacy', 'medicine')
        ");
        $stmt->execute();
        
        echo "Column 'service_provider_id' added to the pharmacies table and existing records updated.\n";
    }
    
    // Fix service provider references
    $stmt = $db->prepare("
        SELECT id, user_id FROM pharmacies WHERE service_provider_id IS NULL
    ");
    $stmt->execute();
    $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pharmacies as $pharmacy) {
        // Get service provider ID for this user
        $stmt = $db->prepare("
            SELECT id FROM service_providers WHERE user_id = ?
        ");
        $stmt->execute([$pharmacy['user_id']]);
        $service_provider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service_provider) {
            // Update the pharmacy record
            $stmt = $db->prepare("
                UPDATE pharmacies 
                SET service_provider_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$service_provider['id'], $pharmacy['id']]);
            echo "Updated pharmacy ID " . $pharmacy['id'] . " with service provider ID " . $service_provider['id'] . "\n";
        } else {
            echo "No service provider found for pharmacy ID " . $pharmacy['id'] . " (user ID: " . $pharmacy['user_id'] . ")\n";
        }
    }
    
    echo "\nDone!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 