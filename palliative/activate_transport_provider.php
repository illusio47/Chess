<?php
/**
 * Activate Transport Provider
 * This script updates the status of transportation service providers from 'pending' to 'active'
 */

// Include configuration and database connection
require_once 'config/config.php';
require_once 'classes/Database.php';

// Get database connection
$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Update transportation providers status to active
    $stmt = $db->prepare("
        UPDATE service_providers 
        SET status = 'active' 
        WHERE service_type = 'transportation' AND status = 'pending'
    ");
    $stmt->execute();
    
    $affected_rows = $stmt->rowCount();
    
    // Commit transaction
    $db->commit();
    
    echo "Success! Updated $affected_rows transportation service providers to active status.<br>";
    echo "You can now refresh the Book Cab page to see the activated transportation providers.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "Error: " . $e->getMessage();
}
?> 