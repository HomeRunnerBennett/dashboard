<?php
require_once 'send_alert.php';

// Enable CORS for testing
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handle both POST and GET requests for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Get dashboard from POST or GET
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dashboard = $_POST['dashboard'] ?? '';
    } else {
        $dashboard = $_GET['dashboard'] ?? '';
    }
    
    // Basic validation
    if (empty($dashboard) || ($dashboard !== 'malpay' && $dashboard !== 'npms')) {
        echo json_encode(['success' => false, 'message' => 'Invalid dashboard specified. Use malpay or npms.']);
        exit;
    }
    
    // Trigger manual alert
    try {
        $result = manuallyTriggerAlert($dashboard);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Alert sent successfully to IT and Service Desk!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send alert. Check email configuration.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST or GET.']);
}
?>