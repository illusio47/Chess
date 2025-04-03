<?php
/**
 * Fix Cab Bookings Provider ID
 * This script updates all cab bookings to be linked to the transport service provider
 */

// Include configuration and database connection
require_once 'config/config.php';
require_once 'classes/Database.php';

// Get database connection
$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Check if transport service provider exists
    $stmt = $db->prepare("SELECT id FROM service_providers WHERE service_type = 'transportation' LIMIT 1");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        throw new Exception("No transportation service provider found in the database.");
    }
    
    $provider_id = $provider['id'];
    
    // Update all cab bookings to link to the transport service provider
    $stmt = $db->prepare("
        UPDATE cab_bookings 
        SET provider_id = ? 
        WHERE provider_id = 0 OR provider_id IS NULL
    ");
    $stmt->execute([$provider_id]);
    
    $affected_rows = $stmt->rowCount();
    
    // Commit transaction
    $db->commit();
    
    echo "Success! Updated $affected_rows cab bookings to link to transport service provider ID: $provider_id";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "Error: " . $e->getMessage();
}
?> 