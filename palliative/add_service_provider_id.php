<?php
// Include database connection
include 'classes/Database.php';

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
    
    echo "Done!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 