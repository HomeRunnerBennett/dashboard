<?php
require_once 'config.php';

// Get filter parameters
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

// Only trigger alerts when no date filter is applied (showing today's data by default)
$is_default_view = (!isset($_GET['date']) && !isset($_GET['date_end']));

if ($npms_failed_count > 5 && $is_default_view) {
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
            SUM(CASE WHEN TransactionStatus = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN TransactionStatus = 'failed' THEN 1 ELSE 0 END) as failed
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? AND Token LIKE 'Token1%'
        GROUP BY DATEPART(HOUR, TransactionDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute([$filter_date, $filter_date_end]);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service distribution for Token1 transactions only
    $service_sql = "
        SELECT 
            Service,
            COUNT(*) as count,
            SUM(TransactionAmount) as amount
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? AND Token LIKE 'Token1%'
        GROUP BY Service
        ORDER BY count DESC
    ";
    
    $service_data = $conn->prepare($service_sql);
    $service_data->execute([$filter_date, $filter_date_end]);
    $service_data = $service_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent Token1 transactions
    $recent_sql = "
        SELECT
            TransactionId,
            ReferenceNumber,
            TransactionStatus,
            TransactionAmount,
            TransactionDate,
            Service,
            WalletBalance,
            Commission,
            MerchantTransactionId,
            Token
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
            TransactionId,
            ReferenceNumber,
            TransactionStatus,
            TransactionAmount,
            TransactionDate,
            Service,
            WalletBalance,
            Commission,
            MerchantTransactionId,
            Token
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ? 
        AND Token NOT LIKE 'Token1%'
        ORDER BY TransactionDate DESC
    ";
    
    $recent_failed_transactions = $conn->prepare($recent_failed_sql);
    $recent_failed_transactions->execute([$filter_date, $filter_date_end]);
    $recent_failed_transactions = $recent_failed_transactions->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique services for filter dropdown
    $services_sql = "SELECT DISTINCT Service FROM TokenTransactions ORDER BY Service";
    $services = $conn->query($services_sql)->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die("NPMS Error: " . $e->getMessage());
}

// Prepare data for export
$export_data = [
    'date_range' => $filter_date . ' to ' . $filter_date_end,
    'stats' => $stats,
    'failed_stats' => $failed_stats,
    'token_success_rate' => $token_success_rate,
    'services' => $service_data,
    'hourly_data' => $hourly_data,
    'recent_failed_transactions' => $recent_failed_transactions
];
?>

<div id="npms" class="tab-content">
    <!-- Filters -->
    <div class="filters">
    <div class="filter-group">
        <label for="npmsFilterDate">Start Date:</label>
        <input type="date" id="npmsFilterDate" name="npmsFilterDate" value="<?php echo htmlspecialchars($filter_date); ?>">
        
        <label for="npmsFilterDateEnd">End Date:</label>
        <input type="date" id="npmsFilterDateEnd" name="npmsFilterDateEnd" value="<?php echo htmlspecialchars($filter_date_end); ?>">
        
        <label for="npmsFilterStatus">Status:</label>
        <select id="npmsFilterStatus" name="npmsFilterStatus">
            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="successful" <?php echo $filter_status === 'successful' ? 'selected' : ''; ?>>Successful</option>
            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
        </select>
        
        <button class="filter-btn" id="applyFilterNPMS">Apply Filters</button>
        
        <div class="export-buttons">
            <button class="export-btn" id="printReportNPMS">📄 Print Report</button>
            <button class="export-btn" id="exportPDFNPMS" onclick="exportToPDF('npms')">📊 Export PDF</button>
            <button class="export-btn" id="exportCSVNPMS">📋 Export CSV</button>
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
            <h3>🚨 Alert: <?php echo $npms_failed_count; ?> Invalid Tokens</h3>
            <p>High failure rate detected - Team notified</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Valid Transactions</h3>
            <div class="stat-number"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
            <p>Token starts with 'Token1'</p>
        </div>
        
        <div class="stat-card success">
            <h3>Token Success Rate</h3>
            <div class="stat-number"><?php echo number_format($token_success_rate, 1); ?>%</div>
            <p>Based on Token validation</p>
        </div>
        
        <div class="stat-card pending">
            <h3>Invalid Tokens</h3>
            <div class="stat-number"><?php echo number_format($failed_stats['failed_token_count'] ?? 0); ?></div>
            <p>Token doesn't start with 'Token1'</p>
        </div>
        
        <div class="stat-card failed">
            <h3>Total Amount</h3>
            <div class="stat-number">MK<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
            <p>From valid transactions</p>
        </div>
    </div>

    <!-- Service Analytics -->
    <h3 class="section-title">Service Performance - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
    <div class="merchant-grid">
        <?php foreach($service_data as $service): 
            $success_rate = $service['count'] > 0 ? ($stats['successful'] / $service['count']) * 100 : 0;
        ?>
        <div class="merchant-card">
            <div class="merchant-header">
                <div class="merchant-name"><?php echo htmlspecialchars($service['Service']); ?></div>
                <div class="success-rate <?php echo $success_rate >= 80 ? 'high' : ($success_rate >= 60 ? 'medium' : 'low'); ?>">
                    <?php echo number_format($service['count']); ?>
                </div>
            </div>
            <div class="merchant-stats">
                <div class="stat-item">
                    <div class="stat-value">MK<?php echo number_format($service['amount'], 2); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($service['count']); ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php echo number_format($success_rate, 1); ?>%
                    </div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 class="section-title">Hourly Transaction Trends (Valid Tokens)</h3>
            <canvas id="npmsHourlyChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
            <h3 class="section-title">Service Distribution (Valid Tokens)</h3>
            <canvas id="npmsServiceChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Valid Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Valid Transactions - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Token</th>
                        <th>Balance</th>
                        <th>Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo $transaction['TransactionId']; ?></td>
                        <td><?php echo $transaction['ReferenceNumber']; ?></td>
                        <td class="status-<?php echo $transaction['TransactionStatus']; ?>">
                            <?php echo ucfirst($transaction['TransactionStatus']); ?>
                        </td>
                        <td>MK<?php echo number_format($transaction['TransactionAmount'], 2); ?></td>
                        <td><?php echo date('H:i:s', strtotime($transaction['TransactionDate'])); ?></td>
                        <td><?php echo $transaction['Service']; ?></td>
                        <td><?php echo substr($transaction['Token'], 0, 10) . '...'; ?></td>
                        <td>MK<?php echo number_format($transaction['WalletBalance'], 2); ?></td>
                        <td>MK<?php echo number_format($transaction['Commission'], 2); ?></td>
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
        <h3 class="section-title">Recent Failed Transactions (Invalid Tokens) - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Token</th>
                        <th>Balance</th>
                        <th>Commission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_failed_transactions)): ?>
                        <?php foreach($recent_failed_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $transaction['TransactionId']; ?></td>
                            <td><?php echo $transaction['ReferenceNumber']; ?></td>
                            <td class="status-<?php echo $transaction['TransactionStatus']; ?>">
                                <?php echo ucfirst($transaction['TransactionStatus']); ?>
                            </td>
                            <td>MK<?php echo number_format($transaction['TransactionAmount'], 2); ?></td>
                            <td><?php echo date('H:i:s', strtotime($transaction['TransactionDate'])); ?></td>
                            <td><?php echo $transaction['Service']; ?></td>
                            <td><?php echo substr($transaction['Token'], 0, 10) . '...'; ?></td>
                            <td>MK<?php echo number_format($transaction['WalletBalance'], 2); ?></td>
                            <td>MK<?php echo number_format($transaction['Commission'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #7f8c8d; padding: 20px;">
                                No failed transactions found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden div for export data -->
<div id="exportDataNPMS" style="display: none;" data-export='<?php echo json_encode($export_data); ?>'></div>

<script>
 // NPMS Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    // Apply filter button for NPMS
    document.getElementById('applyFilterNPMS').addEventListener('click', function() {
        const date = document.getElementById('npmsFilterDate').value;
        const dateEnd = document.getElementById('npmsFilterDateEnd').value;
        const status = document.getElementById('npmsFilterStatus').value;
        
        const params = new URLSearchParams({
            date: date,
            date_end: dateEnd,
            status: status
        });
        
        window.location.href = '?' + params.toString();
    });

    // Enter key support for NPMS filters
    [document.getElementById('npmsFilterDate'), document.getElementById('npmsFilterDateEnd'), document.getElementById('npmsFilterStatus')]
        .forEach(element => {
            element.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('applyFilterNPMS').click();
                }
            });
        });
    
    // Export functionality
    document.getElementById('printReportNPMS').addEventListener('click', function() {
        window.print();
    });

    document.getElementById('exportCSVNPMS').addEventListener('click', function() {
        const exportData = JSON.parse(document.getElementById('exportDataNPMS').dataset.export);
        exportToCSVNPMS(exportData);
    });

    // NPMS Charts
    const npmsHourlyCtx = document.getElementById('npmsHourlyChart').getContext('2d');
    new Chart(npmsHourlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($hourly_data, 'hour')); ?>,
            datasets: [
                {
                    label: 'Successful',
                    data: <?php echo json_encode(array_column($hourly_data, 'successful')); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Pending',
                    data: <?php echo json_encode(array_column($hourly_data, 'pending')); ?>,
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Failed',
                    data: <?php echo json_encode(array_column($hourly_data, 'failed')); ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Hourly Transaction Status Distribution (Valid Tokens Only)' }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Hour of Day' }
                },
                y: {
                    title: { display: true, text: 'Number of Transactions' }
                }
            }
        }
    });

    // Service Distribution Chart
    const npmsServiceCtx = document.getElementById('npmsServiceChart').getContext('2d');
    new Chart(npmsServiceCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($service_data, 'Service')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($service_data, 'count')); ?>,
                backgroundColor: ['#2c9caf', '#4bb5c7', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: true, text: 'Transactions by Service Type (Valid Tokens Only)' }
            }
        }
    });
});

// NPMS CSV Export function
function exportToCSVNPMS(data) {
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Header
    csvContent += "NPMS Transaction Report (Token Filtered)," + data.date_range + "\n\n";
    
    // Summary Statistics
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Valid Transactions (Token1)," + (data.stats.total_transactions || 0) + "\n";
    csvContent += "Invalid Tokens," + (data.failed_stats.failed_token_count || 0) + "\n";
    csvContent += "Token Success Rate," + data.token_success_rate.toFixed(1) + "%\n";
    csvContent += "Total Amount from Valid,MK" + (data.stats.total_amount ? Number(data.stats.total_amount).toFixed(2) : '0.00') + "\n";
    csvContent += "Successful Status," + (data.stats.successful || 0) + "\n";
    csvContent += "Pending Status," + (data.stats.pending || 0) + "\n";
    csvContent += "Failed Status," + (data.stats.failed || 0) + "\n\n";
    
    // Service Data
    csvContent += "SERVICE PERFORMANCE\n";
    csvContent += "Service,Transaction Count,Total Amount,Success Rate\n";
    
    data.services.forEach(service => {
        const successRate = service.count > 0 ? ((data.stats.successful / service.count) * 100).toFixed(1) : 0;
        csvContent += `"${service.Service}",${service.count},MK${Number(service.amount).toFixed(2)},${successRate}%\n`;
    });
    
    csvContent += "\n";
    
    // Hourly Data
    csvContent += "HOURLY BREAKDOWN\n";
    csvContent += "Hour,Total Transactions,Successful,Pending,Failed\n";
    
    data.hourly_data.forEach(hour => {
        csvContent += `${hour.hour}:00,${hour.count},${hour.successful},${hour.pending},${hour.failed}\n`;
    });
    
    csvContent += "\n";
    
    // Failed Transactions Data
    csvContent += "RECENT FAILED TRANSACTIONS (INVALID TOKENS)\n";
    csvContent += "Transaction ID,Reference,Status,Amount,Time,Service,Token,Wallet Balance,Commission\n";
    
    data.recent_failed_transactions.forEach(transaction => {
        csvContent += `"${transaction.TransactionId}","${transaction.ReferenceNumber}","${transaction.TransactionStatus}",MK${Number(transaction.TransactionAmount).toFixed(2)},"${transaction.TransactionDate}","${transaction.Service}","${transaction.Token}",MK${Number(transaction.WalletBalance).toFixed(2)},MK${Number(transaction.Commission).toFixed(2)}\n`;
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
</script>