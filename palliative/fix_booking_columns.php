<?php
/**
 * Fix Cab Bookings Table Structure
 * This script adds missing timestamp columns to the cab_bookings table
 */

// Include database connection
require_once 'classes/Database.php';

try {
    // Get database connection
    $db = Database::getInstance();
    
    // Begin transaction
    $db->beginTransaction();
    
    echo "<h2>Fixing Cab Bookings Table Structure</h2>";
    
    // Add the confirmed_at column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM cab_bookings LIKE 'confirmed_at'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE cab_bookings ADD COLUMN confirmed_at DATETIME DEFAULT NULL");
        echo "<p>✅ Added <strong>confirmed_at</strong> column</p>";
    } else {
        echo "<p>✓ Column <strong>confirmed_at</strong> already exists</p>";
    }
    
    // Add the completed_at column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM cab_bookings LIKE 'completed_at'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE cab_bookings ADD COLUMN completed_at DATETIME DEFAULT NULL");
        echo "<p>✅ Added <strong>completed_at</strong> column</p>";
    } else {
        echo "<p>✓ Column <strong>completed_at</strong> already exists</p>";
    }
    
    // Add the cancelled_at column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM cab_bookings LIKE 'cancelled_at'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE cab_bookings ADD COLUMN cancelled_at DATETIME DEFAULT NULL");
        echo "<p>✅ Added <strong>cancelled_at</strong> column</p>";
    } else {
        echo "<p>✓ Column <strong>cancelled_at</strong> already exists</p>";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "<h3>Table structure fixed successfully!</h3>";
    echo "<p>All booking actions (confirm, complete, cancel) should now work correctly.</p>";
    echo "<p><a href='index.php?module=service&action=transport_dashboard' class='btn btn-primary'>Return to Transport Dashboard</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction in case of error - only if a transaction is active
    if (isset($db)) {
        try {
            // Check if a transaction is active before rolling back
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $rollbackException) {
            // Silent catch - we're already in an error state
        }
    }
    
    echo "<h3>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><a href='index.php?module=service&action=transport_dashboard'>Return to Transport Dashboard</a></p>";
}
?> 