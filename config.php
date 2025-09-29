<?php
// Database configuration
class Config {
    // NPMS Database (Now Active)
    public static $npms_config = [
        'server' => '10.3.0.37\\SQLEXPRESS',
        'database' => 'NPMSWallet',
        'username' => 'sa',
        'password' => 'malswitch*1'
    ];

    // MALPAY Database
    public static $malpay_config = [
        'server' => '10.3.0.100',
        'database' => 'BWB',
        'username' => 'sa',
        'password' => 'malswitch*1'
    ];
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection function
function connectDB($config) {
    try {
        $connection = new PDO(
            "sqlsrv:Server={$config['server']};Database={$config['database']}",
            $config['username'],
            $config['password']
        );
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $connection;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Test both connections
function testConnections() {
    $connections = [
        'NPMS' => Config::$npms_config,
        'MALPAY' => Config::$malpay_config
    ];
    
    foreach ($connections as $name => $config) {
        try {
            $conn = connectDB($config);
            echo "✅ $name: Connection successful<br>";
            $conn = null;
        } catch (Exception $e) {
            echo "❌ $name: " . $e->getMessage() . "<br>";
        }
    }
}
?>