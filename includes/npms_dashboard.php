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
    
    // Build WHERE clause for filters
    $where_clause = "WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ?";
    $params = [$filter_date, $filter_date_end];
    
    if ($filter_status !== 'all') {
        $where_clause .= " AND TransactionStatus = ?";
        $params[] = $filter_status;
    }
    
    // Get overall statistics for the filtered period
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
    
    // Get hourly trends for the day
    $hourly_sql = "
        SELECT 
            DATEPART(HOUR, TransactionDate) as hour,
            COUNT(*) as count,
            SUM(CASE WHEN TransactionStatus = 'successful' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN TransactionStatus = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN TransactionStatus = 'failed' THEN 1 ELSE 0 END) as failed
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ?
        GROUP BY DATEPART(HOUR, TransactionDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute([$filter_date, $filter_date_end]);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service distribution
    $service_sql = "
        SELECT 
            Service,
            COUNT(*) as count,
            SUM(TransactionAmount) as amount
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) BETWEEN ? AND ?
        GROUP BY Service
        ORDER BY count DESC
    ";
    
    $service_data = $conn->prepare($service_sql);
    $service_data->execute([$filter_date, $filter_date_end]);
    $service_data = $service_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent transactions
    $recent_sql = "
        SELECT TOP 15 
            TransactionId,
            ReferenceNumber,
            TransactionStatus,
            TransactionAmount,
            TransactionDate,
            Service,
            WalletBalance,
            Commission,
            MerchantTransactionId
        FROM TokenTransactions 
        $where_clause
        ORDER BY TransactionDate DESC
    ";
    
    $recent_transactions = $conn->prepare($recent_sql);
    $recent_transactions->execute($params);
    $recent_transactions = $recent_transactions->fetchAll(PDO::FETCH_ASSOC);
    
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
    'services' => $service_data,
    'hourly_data' => $hourly_data
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

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total Transactions</h3>
            <div class="stat-number"><?php echo number_format($stats['total_transactions']); ?></div>
            <p><?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></p>
        </div>
        
        <div class="stat-card success">
            <h3>Successful</h3>
            <div class="stat-number"><?php echo number_format($stats['successful']); ?></div>
            <p><?php echo $stats['total_transactions'] > 0 ? round(($stats['successful']/$stats['total_transactions'])*100, 1) : 0; ?>% success rate</p>
        </div>
        
        <div class="stat-card pending">
            <h3>Pending</h3>
            <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
            <p>Awaiting processing</p>
        </div>
        
        <div class="stat-card failed">
            <h3>Failed</h3>
            <div class="stat-number"><?php echo number_format($stats['failed']); ?></div>
            <p>Requires attention</p>
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
                        <?php echo $stats['total_transactions'] > 0 ? number_format(($service['count'] / $stats['total_transactions']) * 100, 1) : 0; ?>%
                    </div>
                    <div class="stat-label">Market Share</div>
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
            <h3 class="section-title">Service Distribution</h3>
            <canvas id="npmsServiceChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Transactions - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
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
                        <th>Balance</th>
                        <th>Commission</th>
                        <th>Merchant ID</th>
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
                        <td>MK<?php echo number_format($transaction['WalletBalance'], 2); ?></td>
                        <td>MK<?php echo number_format($transaction['Commission'], 2); ?></td>
                        <td><?php echo $transaction['MerchantTransactionId']; ?></td>
                    </tr>
                    <?php endforeach; ?>
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
    // Apply filter button
    document.getElementById('applyFilterNPMS').addEventListener('click', function() {
        const date = document.getElementById('filterDate').value;
        const dateEnd = document.getElementById('filterDateEnd').value;
        const status = document.getElementById('filterStatus').value;
        
        const params = new URLSearchParams({
            date: date,
            date_end: dateEnd,
            status: status
        });
        
        window.location.href = '?' + params.toString();
    });

    // Enter key support for filters
    [document.getElementById('filterDate'), document.getElementById('filterDateEnd'), document.getElementById('filterStatus')]
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
                title: { display: true, text: 'Hourly Transaction Status Distribution' }
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
                title: { display: true, text: 'Transactions by Service Type' }
            }
        }
    });
});

// NPMS CSV Export function
function exportToCSVNPMS(data) {
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Header
    csvContent += "NPMS Transaction Report," + data.date_range + "\n\n";
    
    // Summary Statistics
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Total Transactions," + (data.stats.total_transactions || 0) + "\n";
    csvContent += "Successful," + (data.stats.successful || 0) + "\n";
    csvContent += "Pending," + (data.stats.pending || 0) + "\n";
    csvContent += "Failed," + (data.stats.failed || 0) + "\n";
    csvContent += "Total Amount,MK" + (data.stats.total_amount ? Number(data.stats.total_amount).toFixed(2) : '0.00') + "\n";
    csvContent += "Success Rate," + (data.stats.total_transactions > 0 ? ((data.stats.successful / data.stats.total_transactions) * 100).toFixed(1) + '%' : '0%') + "\n\n";
    
    // Service Data
    csvContent += "SERVICE PERFORMANCE\n";
    csvContent += "Service,Transaction Count,Total Amount,Market Share\n";
    
    data.services.forEach(service => {
        const marketShare = data.stats.total_transactions > 0 ? ((service.count / data.stats.total_transactions) * 100).toFixed(1) : 0;
        csvContent += `"${service.Service}",${service.count},MK${Number(service.amount).toFixed(2)},${marketShare}%\n`;
    });
    
    csvContent += "\n";
    
    // Hourly Data
    csvContent += "HOURLY BREAKDOWN\n";
    csvContent += "Hour,Total Transactions,Successful,Pending,Failed\n";
    
    data.hourly_data.forEach(hour => {
        csvContent += `${hour.hour}:00,${hour.count},${hour.successful},${hour.pending},${hour.failed}\n`;
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