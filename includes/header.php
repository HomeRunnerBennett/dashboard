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
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .header-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin: 15px 0;
            flex-wrap: nowrap;
        }
        
        .connection-status {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid transparent;
            white-space: nowrap;
        }
        
        .status-connected {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .status-disconnected {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .refresh-btn {
            padding: 8px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .refresh-btn:hover {
            background: #0056b3;
        }
        
        /* Add a spacer to push refresh button to the right */
        .header-controls::after {
            content: '';
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .header-controls {
                gap: 10px;
            }
            
            .connection-status {
                gap: 10px;
            }
            
            .status-badge {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .refresh-btn {
                padding: 6px 15px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 600px) {
            .header-controls {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .header-controls::after {
                display: none;
            }
            
            .connection-status {
                order: 1;
                width: 100%;
                justify-content: center;
            }
            
            .refresh-btn {
                order: 2;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Transaction Analytics Dashboard</h1>
            <p>Real-time monitoring of NPMS and MALPAY transactions</p>
            
            <div class="header-controls">
                <div class="connection-status">
                    <div class="status-badge <?php echo $npms_connected ? 'status-connected' : 'status-disconnected'; ?>">
                        NPMS: <?php echo $npms_connected ? '✅ Connected' : '❌ Disconnected'; ?>
                    </div>
                    <div class="status-badge <?php echo $malpay_connected ? 'status-connected' : 'status-disconnected'; ?>">
                        MALPAY: <?php echo $malpay_connected ? '✅ Connected' : '❌ Disconnected'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab" data-tab="npms">📊 NPMS Wallet Analytics</button>
            <button class="tab active" data-tab="malpay">💳 MALPAY Transaction Analytics</button>
            <div class="tab-timer">🔄 Auto-switch to NPMS in 5:00</div>
        </div>