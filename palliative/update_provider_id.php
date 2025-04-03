<?php
/**
 * Fix Cab Bookings Provider ID
 * This script updates all cab bookings to be linked to the transport service provider
 */

// Include database connection
require_once 'classes/Database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if the transport service provider exists
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE id = 1 AND service_type = 'transportation'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        throw new Exception("Transport service provider with ID 1 not found");
    }
    
    // Update all cab bookings with provider_id = 0 or NULL to link to the transport service provider (ID 1)
    $stmt = $db->prepare("
        UPDATE cab_bookings 
        SET provider_id = 1 
        WHERE provider_id = 0 OR provider_id IS NULL
    ");
    $stmt->execute();
    
    // Get the number of updated rows
    $affected_rows = $stmt->rowCount();
    
    // Commit transaction
    $db->commit();
    
    echo "Success: Updated $affected_rows cab bookings to link to the transport service provider (ID 1).<br>";
    echo "Please refresh your transport service provider dashboard to see the updated bookings.";
    
} catch (Exception $e) {
    // Rollback transaction in case of error
    if (isset($db)) {
        $db->rollBack();
    }
    
    echo "Error: " . $e->getMessage();
}
?> 