<?php
require_once 'config.php';

// Get filter parameters - using the new parameter names from script.js
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_date_end = $_GET['date_end'] ?? $filter_date;
$filter_status = $_GET['status'] ?? 'all';

// Validate date range
if (strtotime($filter_date_end) < strtotime($filter_date)) {
    $filter_date_end = $filter_date;
}

try {
    $conn = connectDB(Config::$npms_config);
    
    // Build WHERE clause for filters - Only transactions where Token starts with 'Token1'
    $where_clause = "WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? AND Token LIKE 'Token1%'";
    $params = [$filter_date, $filter_date_end];
    
    if ($filter_status !== 'all') {
        $where_clause .= " AND TransactionStatus = ?";
        $params[] = $filter_status;
    }
    
    // Get overall statistics for the filtered period - Only Token1 transactions
    $stats_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN TransactionStatus = 'successful' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN TransactionStatus = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN TransactionStatus = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(TransactionAmount) as total_amount,
            AVG(Commission) as avg_commission
        FROM TokenTransactions 
        $where_clause
    ";
    
    $stats = $conn->prepare($stats_sql);
    $stats->execute($params);
    $stats = $stats->fetch(PDO::FETCH_ASSOC);
    
    // Get failed transactions (Token doesn't start with 'Token1')
    $failed_stats_sql = "
        SELECT 
            COUNT(*) as failed_token_count,
            SUM(TransactionAmount) as failed_token_amount
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? 
        AND Token NOT LIKE 'Token1%'
    ";
    
    $failed_stats = $conn->prepare($failed_stats_sql);
    $failed_stats->execute([$filter_date, $filter_date_end]);
    $failed_stats = $failed_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate success rate based on Token filter
    $total_all_transactions = ($stats['total_transactions'] ?? 0) + ($failed_stats['failed_token_count'] ?? 0);
    $token_success_rate = $total_all_transactions > 0 ? (($stats['total_transactions'] ?? 0) / $total_all_transactions) * 100 : 0;
    
    // ALERT SYSTEM INTEGRATION - Check for failed transactions threshold
    $npms_failed_count = $failed_stats['failed_token_count'] ?? 0;
    if ($npms_failed_count > 5) {
        require_once 'send_alert.php';
        $alert_details = [
            'valid_count' => $stats['total_transactions'] ?? 0,
            'total_amount' => $stats['total_amount'] ?? 0,
            'services_affected' => count($service_data)
        ];
        checkAndSendAlerts('npms', $npms_failed_count, $filter_date, $alert_details);
    }
    
    // Get hourly trends for Token1 transactions only
    $hourly_sql = "
        SELECT 
            DATEPART(HOUR, TransactionDate) as hour,
            COUNT(*) as count,
            SUM(CASE WHEN TransactionStatus = 'successful' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN TransactionStatus = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(TransactionAmount) as amount
        FROM TokenTransactions 
        $where_clause
        GROUP BY DATEPART(HOUR, TransactionDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute($params);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service statistics for Token1 transactions only
    $service_sql = "
        SELECT 
            ServiceName,
            COUNT(*) as count,
            SUM(CASE WHEN TransactionStatus = 'successful' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN TransactionStatus = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(TransactionAmount) as amount,
            AVG(Commission) as avg_commission
        FROM TokenTransactions 
        $where_clause
        GROUP BY ServiceName
        ORDER BY count DESC
    ";
    
    $service_data = $conn->prepare($service_sql);
    $service_data->execute($params);
    $service_data = $service_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent Token1 transactions
    $recent_sql = "
        SELECT 
            TransactionID,
            Token,
            TransactionAmount,
            TransactionDate,
            TransactionStatus,
            ServiceName,
            AccountNumber,
            Commission,
            ResponseMessage
        FROM TokenTransactions 
        $where_clause
        ORDER BY TransactionDate DESC
    ";
    
    $recent_transactions = $conn->prepare($recent_sql);
    $recent_transactions->execute($params);
    $recent_transactions = $recent_transactions->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent failed transactions (Token doesn't start with 'Token1')
    $recent_failed_sql = "
        SELECT 
            TransactionID,
            Token,
            TransactionAmount,
            TransactionDate,
            TransactionStatus,
            ServiceName,
            AccountNumber,
            Commission,
            ResponseMessage
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? 
        AND Token NOT LIKE 'Token1%'
        ORDER BY TransactionDate DESC
    ";
    
    $recent_failed = $conn->prepare($recent_failed_sql);
    $recent_failed->execute([$filter_date, $filter_date_end]);
    $recent_failed = $recent_failed->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Prepare data for export
$export_data = [
    'date_range' => $filter_date . ' to ' . $filter_date_end,
    'stats' => $stats,
    'failed_stats' => $failed_stats,
    'token_success_rate' => $token_success_rate,
    'services' => $service_data,
    'hourly_data' => $hourly_data,
    'recent_failed' => $recent_failed
];
?>

<div id="npms" class="tab-content">
    <!-- Filters -->
    <div class="filters">
        <div class="filter-group">
            <label for="filterDate">Start Date:</label>
            <input type="date" id="filterDate" name="filterDate" value="<?php echo htmlspecialchars($filter_date); ?>">
            
            <label for="filterDateEnd">End Date:</label>
            <input type="date" id="filterDateEnd" name="filterDateEnd" value="<?php echo htmlspecialchars($filter_date_end); ?>">
            
            <label for="filterStatus">Status:</label>
            <select id="filterStatus" name="filterStatus">
                <option value="all">All Status</option>
                <option value="successful" <?php echo $filter_status === 'successful' ? 'selected' : ''; ?>>Successful</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
            </select>
            
            <button class="filter-btn" id="applyFilter">Apply Filters</button>
            
            <div class="export-buttons">
                <button class="export-btn" id="printReportNpms">📄 Print Report</button>
                <button class="export-btn" id="exportPDFNpms" onclick="exportToPDF('npms')">📊 Export PDF</button>
                <button class="export-btn" id="exportCSVNpms">📋 Export CSV</button>
            </div>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php 
    require_once 'alert_tracker.php';

    $npms_failed_count = $failed_stats['failed_token_count'] ?? 0;
    $tracker = new AlertTracker();
    $showAlert = $npms_failed_count > 5;
    $alertSentToday = !$tracker->shouldSendAlert('npms', $filter_date, $npms_failed_count);
    ?>

    <?php if ($showAlert): ?>
    <div class="alert-notification">
        <div class="alert-banner warning">
            <h3>🚨 High Token Failure Rate Detected</h3>
            <p>
                <?php echo $npms_failed_count; ?> failed transactions with invalid Token format detected.
                <?php if ($alertSentToday): ?>
                    <br>Alert was already sent to IT and Service Desk teams today.
                <?php else: ?>
                    <br>Alert has been sent to IT and Service Desk teams.
                <?php endif; ?>
            </p>
            <p><small>Last checked: <?php echo date('Y-m-d H:i:s'); ?></small></p>
            <button class="manual-alert-btn" onclick="resendAlert('npms')">
                Resend Alert Manually
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total Transactions</h3>
            <div class="stat-number"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
            <p>Valid Token1 transactions</p>
        </div>
        
        <div class="stat-card success">
            <h3>Token Success Rate</h3>
            <div class="stat-number"><?php echo number_format($token_success_rate, 1); ?>%</div>
            <p>Valid Token1 format</p>
        </div>
        
        <div class="stat-card failed">
            <h3>Invalid Token</h3>
            <div class="stat-number"><?php echo number_format($failed_stats['failed_token_count'] ?? 0); ?></div>
            <p>Non-Token1 format</p>
        </div>
        
        <div class="stat-card amount">
            <h3>Total Amount</h3>
            <div class="stat-number">MK<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
            <p>From valid transactions</p>
        </div>
    </div>

    <!-- Service Analytics -->
    <h3 class="section-title">Service Performance - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
    <div class="service-grid">
        <?php foreach($service_data as $service): 
            $success_rate = $service['count'] > 0 ? ($service['successful'] / $service['count']) * 100 : 0;
        ?>
        <div class="service-card">
            <div class="service-header">
                <div class="service-name"><?php echo htmlspecialchars($service['ServiceName']); ?></div>
                <div class="success-rate <?php echo $success_rate >= 80 ? 'high' : ($success_rate >= 60 ? 'medium' : 'low'); ?>">
                    <?php echo number_format($success_rate, 1); ?>%
                </div>
            </div>
            <div class="service-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($service['count']); ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($service['successful']); ?></div>
                    <div class="stat-label">Successful</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($service['failed']); ?></div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">MK<?php echo number_format($service['amount'], 2); ?></div>
                    <div class="stat-label">Amount</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 class="section-title">Hourly Transaction Trends</h3>
            <canvas id="npmsHourlyChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
            <h3 class="section-title">Transaction Amount by Service</h3>
            <canvas id="npmsServiceChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Valid Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Valid Token1 Transactions - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Token</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Commission</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction['TransactionID']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['Token']); ?></td>
                        <td>MK<?php echo number_format($transaction['TransactionAmount'], 2); ?></td>
                        <td><?php echo date('H:i:s', strtotime($transaction['TransactionDate'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['ServiceName']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($transaction['TransactionStatus']); ?>">
                                <?php echo htmlspecialchars($transaction['TransactionStatus']); ?>
                            </span>
                        </td>
                        <td>MK<?php echo number_format($transaction['Commission'], 2); ?></td>
                        <td>
                            <?php if (!empty($transaction['ResponseMessage'])): ?>
                            <button onclick="showResponse(`<?php echo addslashes($transaction['ResponseMessage']); ?>`)">
                                View
                            </button>
                            <?php else: ?>
                            <em>No response</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Clean Gap -->
    <div style="margin-top: 30px;"></div>

    <!-- Recent Failed Token Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Failed Token Transactions (Invalid Token Format) - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Token</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Commission</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_failed)): ?>
                        <?php foreach($recent_failed as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['TransactionID']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['Token']); ?></td>
                            <td>MK<?php echo number_format($transaction['TransactionAmount'], 2); ?></td>
                            <td><?php echo date('H:i:s', strtotime($transaction['TransactionDate'])); ?></td>
                            <td><?php echo htmlspecialchars($transaction['ServiceName']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($transaction['TransactionStatus']); ?>">
                                    <?php echo htmlspecialchars($transaction['TransactionStatus']); ?>
                                </span>
                            </td>
                            <td>MK<?php echo number_format($transaction['Commission'], 2); ?></td>
                            <td>
                                <?php if (!empty($transaction['ResponseMessage'])): ?>
                                <button onclick="showResponse(`<?php echo addslashes($transaction['ResponseMessage']); ?>`)">
                                    View
                                </button>
                                <?php else: ?>
                                <em>No response</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #7f8c8d; padding: 20px;">
                                No failed token transactions found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden div for export data -->
<div id="exportDataNpms" style="display: none;" data-export='<?php echo json_encode($export_data); ?>'></div>

<script>
// Filter functionality - REMOVED (now handled by script.js)
// All filter functionality is now managed by the centralized script.js

// Prepare hourly data for chart
const hourlyLabels = <?php echo json_encode(array_column($hourly_data, 'hour')); ?>;
const totalHourlyData = <?php echo json_encode(array_column($hourly_data, 'count')); ?>;
const successfulHourlyData = <?php echo json_encode(array_column($hourly_data, 'successful')); ?>;
const failedHourlyData = <?php echo json_encode(array_column($hourly_data, 'failed')); ?>;

// NPMS Charts
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize charts if this tab is active
    if (document.getElementById('npms').classList.contains('active')) {
        initializeNpmsCharts();
    }
});

function initializeNpmsCharts() {
    const hourlyCtx = document.getElementById('npmsHourlyChart');
    if (hourlyCtx) {
        new Chart(hourlyCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: [
                    {
                        label: 'Total Transactions',
                        data: totalHourlyData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Successful',
                        data: successfulHourlyData,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Failed',
                        data: failedHourlyData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Transaction Trends by Hour' }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Hour of Day' }
                    },
                    y: {
                        title: { display: true, text: 'Number of Transactions' },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Service Amount Chart
    const serviceCtx = document.getElementById('npmsServiceChart');
    if (serviceCtx) {
        new Chart(serviceCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($service_data, 'ServiceName')); ?>,
                datasets: [{
                    label: 'Transaction Amount (MK)',
                    data: <?php echo json_encode(array_column($service_data, 'amount')); ?>,
                    backgroundColor: '#9b59b6',
                    borderColor: '#8e44ad',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Transaction Amount by Service' }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Service' }
                    },
                    y: {
                        title: { display: true, text: 'Amount (MK)' },
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Export functionality
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('printReportNpms');
    const csvBtn = document.getElementById('exportCSVNpms');
    
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    if (csvBtn) {
        csvBtn.addEventListener('click', function() {
            const exportData = JSON.parse(document.getElementById('exportDataNpms').dataset.export);
            exportToCSV(exportData, 'npms');
        });
    }
});

// PDF Export function
function exportToPDF(dashboardType) {
    const exportDataElement = document.getElementById('exportDataNpms');
    if (!exportDataElement) return;
    
    const exportData = JSON.parse(exportDataElement.dataset.export);
    
    // Create a form and submit it to the PDF generation endpoint
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_pdf.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'export_data';
    input.value = JSON.stringify(exportData);
    form.appendChild(input);
    
    const dashboardInput = document.createElement('input');
    dashboardInput.type = 'hidden';
    dashboardInput.name = 'dashboard';
    dashboardInput.value = dashboardType;
    form.appendChild(dashboardInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// CSV Export function
function exportToCSV(data, dashboardType) {
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Header
    csvContent += "NPMS Transaction Report (Token1 Filtered)," + data.date_range + "\n\n";
    
    // Summary Statistics
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Valid Token1 Transactions," + (data.stats.total_transactions || 0) + "\n";
    csvContent += "Invalid Token Transactions," + (data.failed_stats.failed_token_count || 0) + "\n";
    csvContent += "Token Success Rate," + data.token_success_rate.toFixed(1) + "%\n";
    csvContent += "Total Amount from Valid,MK" + (data.stats.total_amount ? Number(data.stats.total_amount).toFixed(2) : '0.00') + "\n";
    csvContent += "Total Amount from Invalid,MK" + (data.failed_stats.failed_token_amount ? Number(data.failed_stats.failed_token_amount).toFixed(2) : '0.00') + "\n\n";
    
    // Service Data
    csvContent += "SERVICE PERFORMANCE\n";
    csvContent += "Service,Total,Successful,Failed,Amount,Avg Commission\n";
    
    data.services.forEach(service => {
        csvContent += `"${service.ServiceName}",${service.count},${service.successful},${service.failed},MK${Number(service.amount).toFixed(2)},MK${Number(service.avg_commission).toFixed(2)}\n`;
    });
    
    csvContent += "\n";
    
    // Hourly Data
    csvContent += "HOURLY BREAKDOWN\n";
    csvContent += "Hour,Total,Successful,Failed,Amount\n";
    
    data.hourly_data.forEach(hour => {
        csvContent += `${hour.hour}:00,${hour.count},${hour.successful},${hour.failed},MK${Number(hour.amount).toFixed(2)}\n`;
    });
    
    csvContent += "\n";
    
    // Failed Transactions Data
    csvContent += "RECENT INVALID TOKEN TRANSACTIONS\n";
    csvContent += "Transaction ID,Token,Amount,Time,Service,Status,Commission,Response\n";
    
    data.recent_failed.forEach(transaction => {
        csvContent += `"${transaction.TransactionID}","${transaction.Token}",MK${Number(transaction.TransactionAmount).toFixed(2)},"${transaction.TransactionDate}","${transaction.ServiceName}","${transaction.TransactionStatus}",MK${Number(transaction.Commission).toFixed(2)},"${transaction.ResponseMessage}"\n`;
    });
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `npms_report_${data.date_range.replace(' to ', '_')}.csv`);
    document.body.appendChild(link);
    
    link.click();
    document.body.removeChild(link);
}

// Print styles
function applyPrintStyles() {
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            .filters, .export-buttons, button {
                display: none !important;
            }
            .section-title {
                color: #000 !important;
                font-weight: bold;
            }
            .stat-card {
                break-inside: avoid;
            }
            .service-grid {
                display: block !important;
            }
            .service-card {
                margin-bottom: 10px;
                break-inside: avoid;
            }
        }
    `;
    document.head.appendChild(style);
}

applyPrintStyles();

// Manual alert resend function
function resendAlert(dashboardType) {
    if (confirm('This will send a manual alert email to IT and Service Desk. Continue?')) {
        // Show loading state
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Sending...';
        button.disabled = true;
        
        // Send AJAX request
        fetch('trigger_alert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'dashboard=' + dashboardType
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message + '\n\nThis was a manual alert trigger.');
                // Update the alert message
                const alertText = button.closest('.alert-banner').querySelector('p');
                alertText.innerHTML = alertText.innerHTML.replace('Alert was already sent', 'Manual alert sent') + '<br><strong>Manual alert sent at: ' + new Date().toLocaleString() + '</strong>';
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Error sending alert: ' + error.message);
        })
        .finally(() => {
            // Reset button
            button.textContent = 'Resend Alert Manually';
            button.disabled = false;
        });
    }
}

// Reinitialize charts when tab becomes active
document.addEventListener('tabChanged', function(e) {
    if (e.detail.tabName === 'npms') {
        setTimeout(initializeNpmsCharts, 100);
    }
});
</script>