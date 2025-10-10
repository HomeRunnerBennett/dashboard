<?php
class AlertTracker {
    private $trackingFile;
    
    public function __construct() {
        $this->trackingFile = __DIR__ . '/alerts_sent.json';
        $this->ensureTrackingFile();
    }
    
    private function ensureTrackingFile() {
        if (!file_exists($this->trackingFile)) {
            file_put_contents($this->trackingFile, json_encode([]));
        }
    }
    
    public function shouldSendAlert($dashboard, $date, $failedCount) {
        $alerts = $this->getAlerts();
        $alertKey = $dashboard . '_' . $date;
        
        // Check if we already sent an alert for this dashboard and date
        if (isset($alerts[$alertKey])) {
            $lastAlert = $alerts[$alertKey];
            
            // Don't send if we already sent an alert today
            if ($lastAlert['date'] === $date) {
                return false;
            }
        }
        
        // Only send if threshold is exceeded
        return $failedCount > 5;
    }
    
    public function markAlertSent($dashboard, $date, $failedCount) {
        $alerts = $this->getAlerts();
        $alertKey = $dashboard . '_' . $date;
        
        $alerts[$alertKey] = [
            'dashboard' => $dashboard,
            'date' => $date,
            'failed_count' => $failedCount,
            'sent_at' => date('Y-m-d H:i:s'),
            'sent_count' => 1
        ];
        
        file_put_contents($this->trackingFile, json_encode($alerts, JSON_PRETTY_PRINT));
        return true;
    }
    
    public function markManualAlertSent($dashboard, $date) {
        $alerts = $this->getAlerts();
        $alertKey = $dashboard . '_' . $date . '_manual';
        
        $alerts[$alertKey] = [
            'dashboard' => $dashboard,
            'date' => $date,
            'type' => 'manual',
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->trackingFile, json_encode($alerts, JSON_PRETTY_PRINT));
        return true;
    }
    
    private function getAlerts() {
        if (!file_exists($this->trackingFile)) {
            return [];
        }
        
        $content = file_get_contents($this->trackingFile);
        return json_decode($content, true) ?: [];
    }
    
    public function clearOldAlerts($days = 7) {
        $alerts = $this->getAlerts();
        $cutoffDate = date('Y-m-d', strtotime("-$days days"));
        
        $filteredAlerts = array_filter($alerts, function($alert) use ($cutoffDate) {
            return $alert['date'] >= $cutoffDate;
        });
        
        file_put_contents($this->trackingFile, json_encode($filteredAlerts, JSON_PRETTY_PRINT));
        return true;
    }
}
?>