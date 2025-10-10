<?php
require_once 'config.php';
require_once 'email_config.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AlertSystem {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }
    
    private function setupMailer() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            
            // SSL options
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Sender
            $this->mailer->setFrom(SMTP_SENDER_EMAIL, SMTP_SENDER_NAME);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Alert System Setup Error: " . $e->getMessage());
        }
    }
    
    public function sendFailedTransactionsAlert($dashboard, $failedCount, $date, $details = []) {
        try {
            // Clear previous recipients
            $this->mailer->clearAllRecipients();
            
            // Add recipients
            foreach (ALERT_RECIPIENTS as $recipient) {
                $this->mailer->addAddress($recipient);
            }
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $this->getAlertSubject($dashboard, $failedCount);
            $this->mailer->Body = $this->getAlertBody($dashboard, $failedCount, $date, $details);
            $this->mailer->AltBody = $this->getAlertTextBody($dashboard, $failedCount, $date, $details);
            
            // Send email
            $result = $this->mailer->send();
            error_log("Alert sent successfully for $dashboard - $failedCount failed transactions on $date");
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send alert: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function getAlertSubject($dashboard, $failedCount) {
        $systemName = $dashboard === 'malpay' ? 'MalPay' : 'NPMS';
        return "Action Required: $failedCount Failed Transactions in $systemName";
    }
    
    private function getAlertBody($dashboard, $failedCount, $date, $details) {
        $systemName = $dashboard === 'malpay' ? 'MalPay' : 'NPMS';
        $issueType = $dashboard === 'malpay' ? 'invalid metadata' : 'invalid token format';
        
        $successfulCount = $details['successful_count'] ?? $details['valid_count'] ?? 0;
        $totalAmount = $details['total_amount'] ?? 0;
        $successRate = $successfulCount > 0 ? round(($successfulCount / ($successfulCount + $failedCount)) * 100, 1) : 0;
        
        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { 
                        font-family: 'Segoe UI', Arial, sans-serif; 
                        line-height: 1.6; 
                        color: #2c3e50; 
                        margin: 0;
                        padding: 0;
                        background: #f8f9fa;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 20px auto; 
                        background: #ffffff;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        overflow: hidden;
                    }
                    .header { 
                        background: #2c9caf; 
                        color: white; 
                        padding: 25px 30px; 
                    }
                    .content { 
                        padding: 30px; 
                    }
                    .alert-section {
                        background: #fff3cd;
                        border-left: 4px solid #ffc107;
                        padding: 20px;
                        margin: 20px 0;
                        border-radius: 4px;
                    }
                    .metrics {
                        background: #f8f9fa;
                        padding: 20px;
                        border-radius: 6px;
                        margin: 25px 0;
                    }
                    .metric-row {
                        display: flex;
                        justify-content: space-between;
                        margin: 10px 0;
                        padding: 8px 0;
                        border-bottom: 1px solid #e9ecef;
                    }
                    .metric-value {
                        font-weight: bold;
                        color: #2c9caf;
                    }
                    .actions {
                        background: #e8f4fd;
                        padding: 20px;
                        border-radius: 6px;
                        margin: 25px 0;
                    }
                    .signature {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e9ecef;
                    }
                    .footer { 
                        text-align: center; 
                        padding: 20px;
                        background: #f8f9fa;
                        border-top: 1px solid #e9ecef;
                        font-size: 12px; 
                        color: #6c757d; 
                    }
                    h1 {
                        margin: 0;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    h2 {
                        color: #2c9caf;
                        margin: 0 0 15px 0;
                        font-size: 18px;
                    }
                    h3 {
                        margin: 0 0 10px 0;
                        font-size: 16px;
                    }
                    .btn {
                        display: inline-block;
                        background: #2c9caf;
                        color: white;
                        padding: 12px 25px;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: 500;
                        margin-top: 10px;
                    }
                    .urgent {
                        color: #e74c3c;
                        font-weight: bold;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Transaction System Alert</h1>
                    </div>
                    
                    <div class='content'>
                        <p>Hello Team,</p>
                        
                        <p>Our monitoring system has detected an elevated number of failed transactions in the <strong>$systemName</strong> system that requires your attention.</p>
                        
                        <div class='alert-section'>
                            <h3>🚨 Immediate Attention Required</h3>
                            <p>We've recorded <strong class='urgent'>$failedCount failed transactions</strong> due to <strong>$issueType</strong>. This exceeds our normal threshold and may indicate a system issue affecting our services.</p>
                        </div>

                        <div class='metrics'>
                            <h3>📊 System Metrics</h3>
                            <div class='metric-row'>
                                <span>Failed Transactions:</span>
                                <span class='metric-value'>$failedCount</span>
                            </div>
                            <div class='metric-row'>
                                <span>Successful Transactions:</span>
                                <span class='metric-value'>$successfulCount</span>
                            </div>
                            <div class='metric-row'>
                                <span>Success Rate:</span>
                                <span class='metric-value'>$successRate%</span>
                            </div>
                            <div class='metric-row'>
                                <span>Date:</span>
                                <span class='metric-value'>$date</span>
                            </div>
                        </div>

                        <div class='actions'>
                            <h3>🎯 Required Actions</h3>
                            
                            <p><strong>For Service Desk:</strong><br>
                            • Notify relevant stakeholders about potential service disruption<br>
                            • Monitor customer impact and communications</p>
                            
                            <p><strong>For System Administrators:</strong><br>
                            • Investigate the root cause of the $issueType issue<br>
                            • Check system logs and API connectivity<br>
                            • Verify service health and performance metrics</p>
                            
                            <a href='{$this->getDashboardUrl()}' class='btn'>View Detailed Dashboard</a>
                        </div>

                        <div class='signature'>
                            <p>Best regards,<br>
                            <strong>Transaction Monitoring System</strong><br>
                            NITEL Malawi</p>
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>This is an automated alert. Please do not reply to this email.</p>
                        <p>If you need immediate assistance, contact the IT operations team.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
    
    private function getAlertTextBody($dashboard, $failedCount, $date, $details) {
        $systemName = $dashboard === 'malpay' ? 'MalPay' : 'NPMS';
        $issueType = $dashboard === 'malpay' ? 'invalid metadata' : 'invalid token format';
        $successfulCount = $details['successful_count'] ?? $details['valid_count'] ?? 0;
        $successRate = $successfulCount > 0 ? round(($successfulCount / ($successfulCount + $failedCount)) * 100, 1) : 0;
        
        return "
ACTION REQUIRED: $failedCount Failed Transactions in $systemName

Hello Team,

Our monitoring system has detected an elevated number of failed transactions in the $systemName system that requires your attention.

🚨 IMMEDIATE ATTENTION REQUIRED
We've recorded $failedCount failed transactions due to $issueType. This exceeds our normal threshold and may indicate a system issue affecting our services.

📊 SYSTEM METRICS
Failed Transactions: $failedCount
Successful Transactions: $successfulCount
Success Rate: $successRate%
Date: $date

🎯 REQUIRED ACTIONS

For Service Desk:
• Notify relevant stakeholders about potential service disruption
• Monitor customer impact and communications

For System Administrators:
• Investigate the root cause of the $issueType issue
• Check system logs and API connectivity
• Verify service health and performance metrics

View Detailed Dashboard: {$this->getDashboardUrl()}

Best regards,
Transaction Monitoring System
NITEL Malawi

This is an automated alert. Please do not reply to this email.
If you need immediate assistance, contact the IT operations team.
        ";
    }
    
    private function getDashboardUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/';
        return "$protocol://$host$script";
    }
}

// Function to check and send alerts
function checkAndSendAlerts($dashboard, $failedCount, $date, $details = []) {
    require_once 'alert_tracker.php';
    $tracker = new AlertTracker();
    
    // Clean old alerts periodically
    if (rand(1, 10) === 1) { // 10% chance on each request to clean up
        $tracker->clearOldAlerts(7); // Keep 7 days of history
    }
    
    if ($tracker->shouldSendAlert($dashboard, $date, $failedCount)) {
        $alertSystem = new AlertSystem();
        $result = $alertSystem->sendFailedTransactionsAlert($dashboard, $failedCount, $date, $details);
        
        if ($result) {
            $tracker->markAlertSent($dashboard, $date, $failedCount);
        }
        
        return $result;
    }
    
    return false;
}

// Function to manually trigger alert (for testing)
function manuallyTriggerAlert($dashboard, $date = null) {
    require_once 'alert_tracker.php';
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    $tracker = new AlertTracker();
    
    // Simulate high failure count for testing
    $failedCount = 10;
    $details = [
        'successful_count' => 50,
        'total_amount' => 150000,
        'merchants_affected' => 5,
        'services_affected' => 3
    ];
    
    $alertSystem = new AlertSystem();
    $result = $alertSystem->sendFailedTransactionsAlert($dashboard, $failedCount, $date, $details);
    
    if ($result) {
        $tracker->markManualAlertSent($dashboard, $date);
    }
    
    return $result;
}
?>