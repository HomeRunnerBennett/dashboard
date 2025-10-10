<?php
require_once 'config.php';

// Increase execution time temporarily to avoid timeout
ini_set('max_execution_time', 300);

// Get filter parameters - using the new parameter names from script.js
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_date_end = $_GET['date_end'] ?? $filter_date;
$filter_merchant = $_GET['merchant'] ?? 'all';

// Validate date range
if (strtotime($filter_date_end) < strtotime($filter_date)) {
    $filter_date_end = $filter_date;
}

try {
    $conn = connectDB(Config::$malpay_config);
    
    // Build WHERE clause for filters - ONLY OUT transactions with specific MetaData
    $where_clause = "WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? AND l.LogType = 'OUT' AND l.MetaData LIKE 'Total response time%'";
    $params = [$filter_date, $filter_date_end];
    
    if ($filter_merchant !== 'all') {
        $where_clause .= " AND l.MerchantName = ?";
        $params[] = $filter_merchant;
    }
    
    // Get overall statistics for the filtered period - ONLY valid OUT transactions
    $stats_sql = "
        SELECT 
            COUNT(*) as total_out_logs,
            SUM(l.Amount) as total_amount,
            AVG(l.ReponseTime) as avg_response_time
        FROM MerchantLogs l
        $where_clause
    ";
    
    $stats = $conn->prepare($stats_sql);
    $stats->execute($params);
    $stats = $stats->fetch(PDO::FETCH_ASSOC);
    
    // Get failed OUT transactions (MetaData doesn't start with "Total response time")
    $failed_stats_sql = "
        SELECT 
            COUNT(*) as failed_out_logs,
            SUM(l.Amount) as failed_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData NOT LIKE 'Total response time%'
    ";
    
    $failed_params = [$filter_date, $filter_date_end];
    if ($filter_merchant !== 'all') {
        $failed_stats_sql .= " AND l.MerchantName = ?";
        $failed_params[] = $filter_merchant;
    }
    
    $failed_stats = $conn->prepare($failed_stats_sql);
    $failed_stats->execute($failed_params);
    $failed_stats = $failed_stats->fetch(PDO::FETCH_ASSOC);
    
    // Get merchant statistics - ONLY valid OUT transactions with amounts
    $merchant_stats_sql = "
        SELECT 
            l.MerchantName,
            COUNT(*) as out_count,
            SUM(l.Amount) as total_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData LIKE 'Total response time%'
        GROUP BY l.MerchantName
        ORDER BY out_count DESC
    ";
    
    $merchant_stats = $conn->prepare($merchant_stats_sql);
    $merchant_stats->execute([$filter_date, $filter_date_end]);
    $merchant_stats_data = $merchant_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Get failed transactions per merchant
    $failed_merchant_stats_sql = "
        SELECT 
            l.MerchantName,
            COUNT(*) as failed_count,
            SUM(l.Amount) as failed_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData NOT LIKE 'Total response time%'
        GROUP BY l.MerchantName
        ORDER BY failed_count DESC
    ";
    
    $failed_merchant_stats = $conn->prepare($failed_merchant_stats_sql);
    $failed_merchant_stats->execute([$filter_date, $filter_date_end]);
    $failed_merchant_data = $failed_merchant_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total transactions per merchant (both IN and OUT) for comparison
    $total_merchant_stats_sql = "
        SELECT 
            MerchantName,
            COUNT(*) as total_count,
            SUM(CASE WHEN LogType = 'OUT' THEN 1 ELSE 0 END) as out_count,
            SUM(CASE WHEN LogType = 'IN' THEN 1 ELSE 0 END) as in_count,
            SUM(CASE WHEN LogType = 'OUT' AND MetaData LIKE 'Total response time%' THEN 1 ELSE 0 END) as successful_out_count,
            SUM(CASE WHEN LogType = 'OUT' AND MetaData NOT LIKE 'Total response time%' THEN 1 ELSE 0 END) as failed_out_count
        FROM MerchantLogs 
        WHERE CAST(LogDate as DATE) BETWEEN ? AND ?
        GROUP BY MerchantName
        ORDER BY total_count DESC
    ";
    
    $total_merchant_stats = $conn->prepare($total_merchant_stats_sql);
    $total_merchant_stats->execute([$filter_date, $filter_date_end]);
    $total_merchant_data = $total_merchant_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Get hourly trends for valid OUT transactions only with amounts
    $hourly_sql = "
        SELECT 
            DATEPART(HOUR, l.LogDate) as hour,
            COUNT(*) as out_count,
            SUM(l.Amount) as out_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData LIKE 'Total response time%'
        GROUP BY DATEPART(HOUR, l.LogDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute([$filter_date, $filter_date_end]);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get hourly trends for failed OUT transactions
    $hourly_failed_sql = "
        SELECT 
            DATEPART(HOUR, l.LogDate) as hour,
            COUNT(*) as failed_count
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData NOT LIKE 'Total response time%'
        GROUP BY DATEPART(HOUR, l.LogDate)
        ORDER BY hour
    ";
    
    $hourly_failed_data = $conn->prepare($hourly_failed_sql);
    $hourly_failed_data->execute([$filter_date, $filter_date_end]);
    $hourly_failed_data = $hourly_failed_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent valid OUT logs for the filtered period
    $recent_sql = "
        SELECT
            l.LogId,
            l.RequestCode,
            l.Amount,
            l.LogDate,
            l.MSISDN,
            l.LogType,
            l.ServiceProvider,
            l.MerchantName,
            l.BatchCode,
            l.MerchantIdentifier,
            l.AccountNumber,
            l.MetaData,
            l.ReponseTime
        FROM MerchantLogs l
        $where_clause
        ORDER BY l.LogDate DESC
    ";
    
    $recent_logs = $conn->prepare($recent_sql);
    $recent_logs->execute($params);
    $recent_logs = $recent_logs->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent failed OUT logs (MetaData doesn't start with "Total response time")
    $recent_failed_sql = "
        SELECT
            l.LogId,
            l.RequestCode,
            l.Amount,
            l.LogDate,
            l.MSISDN,
            l.LogType,
            l.ServiceProvider,
            l.MerchantName,
            l.BatchCode,
            l.MerchantIdentifier,
            l.AccountNumber,
            l.MetaData,
            l.ReponseTime
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? 
        AND l.LogType = 'OUT' 
        AND l.MetaData NOT LIKE 'Total response time%'
        ORDER BY l.LogDate DESC
    ";
    
    $recent_failed_logs = $conn->prepare($recent_failed_sql);
    $recent_failed_logs->execute([$filter_date, $filter_date_end]);
    $recent_failed_logs = $recent_failed_logs->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique merchants for filter dropdown
    $merchants_sql = "SELECT DISTINCT MerchantName FROM MerchantLogs ORDER BY MerchantName";
    $merchants = $conn->query($merchants_sql)->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Calculate success rate
$total_out_transactions = ($stats['total_out_logs'] ?? 0) + ($failed_stats['failed_out_logs'] ?? 0);
$success_rate = $total_out_transactions > 0 ? (($stats['total_out_logs'] ?? 0) / $total_out_transactions) * 100 : 0;

// ALERT SYSTEM INTEGRATION - Check for failed transactions threshold
$malpay_failed_count = $failed_stats['failed_out_logs'] ?? 0;
if ($malpay_failed_count > 5) {
    require_once 'send_alert.php';
    $alert_details = [
        'successful_count' => $stats['total_out_logs'] ?? 0,
        'total_amount' => $stats['total_amount'] ?? 0,
        'merchants_affected' => count($failed_merchant_data)
    ];
    checkAndSendAlerts('malpay', $malpay_failed_count, $filter_date, $alert_details);
}

// Prepare data for export
$export_data = [
    'date_range' => $filter_date . ' to ' . $filter_date_end,
    'stats' => $stats,
    'failed_stats' => $failed_stats,
    'success_rate' => $success_rate,
    'merchants' => $merchant_stats_data,
    'failed_merchants' => $failed_merchant_data,
    'hourly_data' => $hourly_data,
    'hourly_failed_data' => $hourly_failed_data,
    'recent_failed_logs' => $recent_failed_logs
];
?>

<div id="malpay" class="tab-content">
    <!-- Filters -->
    <div class="filters">
        <div class="filter-group">
            <label for="filterDate">Start Date:</label>
            <input type="date" id="filterDate" name="filterDate" value="<?php echo htmlspecialchars($filter_date); ?>">
            
            <label for="filterDateEnd">End Date:</label>
            <input type="date" id="filterDateEnd" name="filterDateEnd" value="<?php echo htmlspecialchars($filter_date_end); ?>">
            
            <label for="filterMerchant">Merchant:</label>
            <select id="filterMerchant" name="filterMerchant">
                <option value="all">All Merchants</option>
                <?php foreach($merchants as $merchant): ?>
                    <option value="<?php echo htmlspecialchars($merchant); ?>" <?php echo $filter_merchant === $merchant ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($merchant); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button class="filter-btn" id="applyFilter">Apply Filters</button>
            
            <div class="export-buttons">
                <button class="export-btn" id="printReportMalpay">📄 Print Report</button>
                <button class="export-btn" id="exportPDFMalpay" onclick="exportToPDF('malpay')">📊 Export PDF</button>
                <button class="export-btn" id="exportCSVMalpay">📋 Export CSV</button>
            </div>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php 
    require_once 'alert_tracker.php';

    $malpay_failed_count = $failed_stats['failed_out_logs'] ?? 0;
    $tracker = new AlertTracker();
    $showAlert = $malpay_failed_count > 5;
    $alertSentToday = !$tracker->shouldSendAlert('malpay', $filter_date, $malpay_failed_count);
    ?>

    <?php if ($showAlert): ?>
    <div class="alert-notification">
        <div class="alert-banner warning">
            <h3>🚨 High Failure Rate Detected</h3>
            <p>
                <?php echo $malpay_failed_count; ?> failed OUT transactions detected.
                <?php if ($alertSentToday): ?>
                    <br>Alert was already sent to IT and Service Desk teams today.
                <?php else: ?>
                    <br>Alert has been sent to IT and Service Desk teams.
                <?php endif; ?>
            </p>
            <p><small>Last checked: <?php echo date('Y-m-d H:i:s'); ?></small></p>
            <button class="manual-alert-btn" onclick="resendAlert('malpay')">
                Resend Alert Manually
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Successful OUT</h3>
            <div class="stat-number"><?php echo number_format($stats['total_out_logs'] ?? 0); ?></div>
            <p>Valid transactions</p>
        </div>
        
        <div class="stat-card success">
            <h3>Success Rate</h3>
            <div class="stat-number"><?php echo number_format($success_rate, 1); ?>%</div>
            <p>Based on MetaData</p>
        </div>
        
        <div class="stat-card failed">
            <h3>Failed OUT</h3>
            <div class="stat-number"><?php echo number_format($failed_stats['failed_out_logs'] ?? 0); ?></div>
            <p>Invalid MetaData</p>
        </div>
        
        <div class="stat-card amount">
            <h3>Total Amount</h3>
            <div class="stat-number">MK<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
            <p>From successful transactions</p>
        </div>
    </div>

    <!-- Merchant Analytics -->
    <h3 class="section-title">Merchant Performance - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
    <div class="merchant-grid">
        <?php foreach($merchant_stats_data as $merchant): 
            // Find total and failed transactions for this merchant for comparison
            $total_for_merchant = 0;
            $failed_for_merchant = 0;
            $in_for_merchant = 0;
            
            foreach($total_merchant_data as $total_merchant) {
                if ($total_merchant['MerchantName'] === $merchant['MerchantName']) {
                    $total_for_merchant = $total_merchant['total_count'];
                    $failed_for_merchant = $total_merchant['failed_out_count'];
                    $in_for_merchant = $total_merchant['in_count'];
                    break;
                }
            }
            
            $success_rate = ($merchant['out_count'] + $failed_for_merchant) > 0 
                ? ($merchant['out_count'] / ($merchant['out_count'] + $failed_for_merchant)) * 100 
                : 0;
        ?>
        <div class="merchant-card">
            <div class="merchant-header">
                <div class="merchant-name"><?php echo htmlspecialchars($merchant['MerchantName']); ?></div>
                <div class="success-rate <?php echo $success_rate >= 80 ? 'high' : ($success_rate >= 60 ? 'medium' : 'low'); ?>">
                    <?php echo number_format($success_rate, 1); ?>%
                </div>
            </div>
            <div class="merchant-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($merchant['out_count']); ?></div>
                    <div class="stat-label">Successful OUT</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($failed_for_merchant); ?></div>
                    <div class="stat-label">Failed OUT</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">MK<?php echo number_format($merchant['total_amount'], 2); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 class="section-title">Hourly Transaction Trends</h3>
            <canvas id="malpayHourlyChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
            <h3 class="section-title">Transaction Amount by Merchant</h3>
            <canvas id="malpayMerchantChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Successful Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Successful OUT Transactions - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Request Code</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Merchant</th>
                        <th>Account</th>
                        <th>Response Time</th>
                        <th>Log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['LogId']); ?></td>
                        <td><?php echo htmlspecialchars($log['RequestCode']); ?></td>
                        <td>MK<?php echo number_format($log['Amount'], 2); ?></td>
                        <td><?php echo date('H:i:s', strtotime($log['LogDate'])); ?></td>
                        <td><?php echo htmlspecialchars($log['MerchantName']); ?></td>
                        <td><?php echo htmlspecialchars($log['AccountNumber'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format($log['ReponseTime'], 2); ?>ms</td>
                        <td>
                            <?php if (!empty($log['MetaData'])): ?>
                            <button onclick="showMetadata(`<?php echo addslashes($log['MetaData']); ?>`)">
                                View
                            </button>
                            <?php else: ?>
                            <em>No log</em>
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

    <!-- Recent Failed Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Failed OUT Transactions (Invalid MetaData) - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Request Code</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Merchant</th>
                        <th>Account</th>
                        <th>Response Time</th>
                        <th>Log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_failed_logs)): ?>
                        <?php foreach($recent_failed_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['LogId']); ?></td>
                            <td><?php echo htmlspecialchars($log['RequestCode']); ?></td>
                            <td>MK<?php echo number_format($log['Amount'], 2); ?></td>
                            <td><?php echo date('H:i:s', strtotime($log['LogDate'])); ?></td>
                            <td><?php echo htmlspecialchars($log['MerchantName']); ?></td>
                            <td><?php echo htmlspecialchars($log['AccountNumber'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($log['ReponseTime'], 2); ?>ms</td>
                            <td>
                                <?php if (!empty($log['MetaData'])): ?>
                                <button onclick="showMetadata(`<?php echo addslashes($log['MetaData']); ?>`)">
                                    View
                                </button>
                                <?php else: ?>
                                <em>No log</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #7f8c8d; padding: 20px;">
                                No failed OUT transactions found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden div for export data -->
<div id="exportDataMalpay" style="display: none;" data-export='<?php echo json_encode($export_data); ?>'></div>

<script>
// Filter functionality - REMOVED (now handled by script.js)
// All filter functionality is now managed by the centralized script.js

// Prepare hourly data for chart
const hourlyLabels = <?php echo json_encode(array_column($hourly_data, 'hour')); ?>;
const successfulHourlyData = <?php echo json_encode(array_column($hourly_data, 'out_count')); ?>;
const failedHourlyData = <?php echo json_encode(array_column($hourly_failed_data, 'failed_count')); ?>;

// MALPAY Charts
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize charts if this tab is active
    if (document.getElementById('malpay').classList.contains('active')) {
        initializeMalpayCharts();
    }
});

function initializeMalpayCharts() {
    const hourlyCtx = document.getElementById('malpayHourlyChart');
    if (hourlyCtx) {
        new Chart(hourlyCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [
                    {
                        label: 'Successful OUT',
                        data: successfulHourlyData,
                        backgroundColor: '#27ae60',
                        borderColor: '#27ae60',
                        borderWidth: 1
                    },
                    {
                        label: 'Failed OUT',
                        data: failedHourlyData,
                        backgroundColor: '#e74c3c',
                        borderColor: '#e74c3c',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Successful vs Failed OUT Transactions by Hour' }
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

    // Merchant Amount Chart
    const merchantCtx = document.getElementById('malpayMerchantChart');
    if (merchantCtx) {
        new Chart(merchantCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($merchant_stats_data, 'MerchantName')); ?>,
                datasets: [{
                    label: 'Transaction Amount (MK)',
                    data: <?php echo json_encode(array_column($merchant_stats_data, 'total_amount')); ?>,
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Transaction Amount by Merchant' }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Merchant' }
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
    const printBtn = document.getElementById('printReportMalpay');
    const csvBtn = document.getElementById('exportCSVMalpay');
    
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    if (csvBtn) {
        csvBtn.addEventListener('click', function() {
            const exportData = JSON.parse(document.getElementById('exportDataMalpay').dataset.export);
            exportToCSV(exportData, 'malpay');
        });
    }
});

// PDF Export function
function exportToPDF(dashboardType) {
    const exportDataElement = document.getElementById('exportDataMalpay');
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
    csvContent += "MalPay Transaction Report (MetaData Filtered)," + data.date_range + "\n\n";
    
    // Summary Statistics
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Successful OUT Transactions," + (data.stats.total_out_logs || 0) + "\n";
    csvContent += "Failed OUT Transactions," + (data.failed_stats.failed_out_logs || 0) + "\n";
    csvContent += "Success Rate," + data.success_rate.toFixed(1) + "%\n";
    csvContent += "Total Amount from Successful,MK" + (data.stats.total_amount ? Number(data.stats.total_amount).toFixed(2) : '0.00') + "\n";
    csvContent += "Total Amount from Failed,MK" + (data.failed_stats.failed_amount ? Number(data.failed_stats.failed_amount).toFixed(2) : '0.00') + "\n\n";
    
    // Merchant Data
    csvContent += "MERCHANT PERFORMANCE\n";
    csvContent += "Merchant,Successful OUT,Failed OUT,Total Amount,Success Rate\n";
    
    data.merchants.forEach(merchant => {
        const failedCount = data.failed_merchants.find(m => m.MerchantName === merchant.MerchantName)?.failed_count || 0;
        const totalOut = merchant.out_count + failedCount;
        const successRate = totalOut > 0 ? ((merchant.out_count / totalOut) * 100).toFixed(1) : 0;
        
        csvContent += `"${merchant.MerchantName}",${merchant.out_count},${failedCount},MK${Number(merchant.total_amount).toFixed(2)},${successRate}%\n`;
    });
    
    csvContent += "\n";
    
    // Hourly Data
    csvContent += "HOURLY BREAKDOWN\n";
    csvContent += "Hour,Successful OUT,Failed OUT\n";
    
    data.hourly_data.forEach(hour => {
        const failedCount = data.hourly_failed_data.find(h => h.hour === hour.hour)?.failed_count || 0;
        csvContent += `${hour.hour}:00,${hour.out_count},${failedCount}\n`;
    });
    
    csvContent += "\n";
    
    // Failed Transactions Data
    csvContent += "RECENT FAILED OUT TRANSACTIONS (INVALID METADATA)\n";
    csvContent += "Log ID,Request Code,Amount,Time,Merchant,Account,Response Time,MetaData\n";
    
    data.recent_failed_logs.forEach(log => {
        csvContent += `"${log.LogId}","${log.RequestCode}",MK${Number(log.Amount).toFixed(2)},"${log.LogDate}","${log.MerchantName}","${log.AccountNumber || 'N/A'}","${log.ReponseTime}ms","${log.MetaData}"\n`;
    });
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `malpay_report_${data.date_range.replace(' to ', '_')}.csv`);
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
            .merchant-grid {
                display: block !important;
            }
            .merchant-card {
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
    if (e.detail.tabName === 'malpay') {
        setTimeout(initializeMalpayCharts, 100);
    }
});
</script>