<?php
require_once 'config.php';

// Test connections
$npms_connected = false;
$malpay_connected = false;

try {
    $npms_conn = connectDB(Config::$npms_config);
    $npms_connected = true;
    $npms_conn = null;
} catch (Exception $e) {
    $npms_connected = false;
}

try {
    $malpay_conn = connectDB(Config::$malpay_config);
    $malpay_connected = true;
    $malpay_conn = null;
} catch (Exception $e) {
    $malpay_connected = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Transaction Analytics Dashboard</h1>
            <p>Real-time monitoring of NPMS and MALPAY transactions</p>
            
            <div class="connection-status">
                <div class="status-badge <?php echo $npms_connected ? 'status-connected' : 'status-disconnected'; ?>">
                    NPMS: <?php echo $npms_connected ? '✅ Connected' : '❌ Disconnected'; ?>
                </div>
                <div class="status-badge <?php echo $malpay_connected ? 'status-connected' : 'status-disconnected'; ?>">
                    MALPAY: <?php echo $malpay_connected ? '✅ Connected' : '❌ Disconnected'; ?>
                </div>
            </div>
            
            <button class="refresh-btn" id="refreshBtn">🔄 Refresh Data</button>
        </div>
        
        <div class="tabs">
            <button class="tab" data-tab="npms">📊 NPMS Wallet Analytics</button>
            <button class="tab active" data-tab="malpay">💳 MALPAY Transaction Analytics</button>
            <div class="tab-timer">🔄 Auto-switch to NPMS in 5:00</div>
        </div>