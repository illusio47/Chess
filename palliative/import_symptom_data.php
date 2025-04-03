<?php
/**
 * Import Symptom Data Script
 * This script imports sample data for symptoms, diseases, and their relationships
 */

require_once 'config/database.php';

try {
    // Check if symptoms table is empty
    $stmt = $conn->prepare("SELECT COUNT(*) FROM symptoms");
    $stmt->execute();
    $symptomCount = $stmt->fetchColumn();
    
    if ($symptomCount > 0) {
        echo "Symptoms data already exists. No import needed.";
        exit;
    }
    
    // Import sample data
    $sampleDataPath = __DIR__ . '/sql/sample_symptoms_diseases.sql';
    if (!file_exists($sampleDataPath)) {
        echo "Sample data file not found at: $sampleDataPath";
        exit;
    }
    
    $sql = file_get_contents($sampleDataPath);
    $conn->exec($sql);
    
    echo "Sample symptom and disease data has been successfully imported!";
    
} catch (PDOException $e) {
    echo "Error importing data: " . $e->getMessage();
}
?>