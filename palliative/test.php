<?php
// Test file to verify PHP is working correctly
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PHP Test</title>
</head>
<body>
    <h1>PHP Test Page</h1>
    <p>PHP Version: <?php echo phpversion(); ?></p>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>Test completed successfully!</p>
</body>
</html> 