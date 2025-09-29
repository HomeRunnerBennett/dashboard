<?php
require_once 'config.php';

// Increase execution time temporarily to avoid timeout
ini_set('max_execution_time', 300);

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_date_end = $_GET['date_end'] ?? $filter_date;
$filter_merchant = $_GET['merchant'] ?? 'all';

// Validate date range
if (strtotime($filter_date_end) < strtotime($filter_date)) {
    $filter_date_end = $filter_date;
}

try {
    $conn = connectDB(Config::$malpay_config);
    
    // Build WHERE clause for filters - ONLY OUT transactions (successful completions)
    $where_clause = "WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? AND l.LogType = 'OUT'";
    $params = [$filter_date, $filter_date_end];
    
    if ($filter_merchant !== 'all') {
        $where_clause .= " AND l.MerchantName = ?";
        $params[] = $filter_merchant;
    }
    
    // Get overall statistics for the filtered period - ONLY OUT transactions
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
    
    // Get merchant statistics - ONLY OUT transactions with amounts
    $merchant_stats_sql = "
        SELECT 
            l.MerchantName,
            COUNT(*) as out_count,
            SUM(l.Amount) as total_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? AND l.LogType = 'OUT'
        GROUP BY l.MerchantName
        ORDER BY out_count DESC
    ";
    
    $merchant_stats = $conn->prepare($merchant_stats_sql);
    $merchant_stats->execute([$filter_date, $filter_date_end]);
    $merchant_stats_data = $merchant_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total transactions per merchant (both IN and OUT) for comparison
    $total_merchant_stats_sql = "
        SELECT 
            MerchantName,
            COUNT(*) as total_count,
            SUM(CASE WHEN LogType = 'OUT' THEN 1 ELSE 0 END) as out_count,
            SUM(CASE WHEN LogType = 'IN' THEN 1 ELSE 0 END) as in_count,
            SUM(CASE WHEN LogType = 'OUT' THEN Amount ELSE 0 END) as out_amount,
            SUM(CASE WHEN LogType = 'IN' THEN Amount ELSE 0 END) as in_amount
        FROM MerchantLogs 
        WHERE CAST(LogDate as DATE) BETWEEN ? AND ?
        GROUP BY MerchantName
        ORDER BY total_count DESC
    ";
    
    $total_merchant_stats = $conn->prepare($total_merchant_stats_sql);
    $total_merchant_stats->execute([$filter_date, $filter_date_end]);
    $total_merchant_data = $total_merchant_stats->fetchAll(PDO::FETCH_ASSOC);
    
    // Get hourly trends for OUT transactions only with amounts
    $hourly_sql = "
        SELECT 
            DATEPART(HOUR, l.LogDate) as hour,
            COUNT(*) as out_count,
            SUM(l.Amount) as out_amount
        FROM MerchantLogs l
        WHERE CAST(l.LogDate as DATE) BETWEEN ? AND ? AND l.LogType = 'OUT'
        GROUP BY DATEPART(HOUR, l.LogDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute([$filter_date, $filter_date_end]);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent OUT logs for the filtered period
    $recent_sql = "
        SELECT TOP 20 
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
    
    // Get unique merchants for filter dropdown
    $merchants_sql = "SELECT DISTINCT MerchantName FROM MerchantLogs ORDER BY MerchantName";
    $merchants = $conn->query($merchants_sql)->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Prepare data for export
$export_data = [
    'date_range' => $filter_date . ' to ' . $filter_date_end,
    'stats' => $stats,
    'merchants' => $merchant_stats_data,
    'hourly_data' => $hourly_data
];
?>

<div id="malpay" class="tab-content active">
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
                <button class="export-btn" id="printReport">📄 Print Report</button>
                <button class="export-btn" id="exportPDF" onclick="exportToPDF('malpay')">📊 Export PDF</button>
                <button class="export-btn" id="exportCSV">📋 Export CSV</button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Completed Transactions</h3>
            <div class="stat-number"><?php echo number_format($stats['total_out_logs']); ?></div>
            <p>Successful OUT logs</p>
        </div>
        
        <div class="stat-card success">
            <h3>Total Amount</h3>
            <div class="stat-number">MK<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
            <p>From completed transactions</p>
        </div>
        
        <div class="stat-card pending">
            <h3>Avg Response Time</h3>
            <div class="stat-number"><?php echo number_format($stats['avg_response_time'] ?? 0, 2); ?>ms</div>
            <p>Processing time</p>
        </div>
        
        <div class="stat-card incomplete">
            <h3>Active Merchants</h3>
            <div class="stat-number"><?php echo number_format(count($merchant_stats_data)); ?></div>
            <p>With completed transactions</p>
        </div>
    </div>

    <!-- Merchant Analytics -->
    <h3 class="section-title">Merchant Performance - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
    <div class="merchant-grid">
        <?php foreach($merchant_stats_data as $merchant): 
            // Find total transactions for this merchant for comparison
            $total_for_merchant = 0;
            $in_for_merchant = 0;
            $in_amount_for_merchant = 0;
            foreach($total_merchant_data as $total_merchant) {
                if ($total_merchant['MerchantName'] === $merchant['MerchantName']) {
                    $total_for_merchant = $total_merchant['total_count'];
                    $in_for_merchant = $total_merchant['in_count'];
                    $in_amount_for_merchant = $total_merchant['in_amount'];
                    break;
                }
            }
            
            // Calculate success rate
            $success_rate = $total_for_merchant > 0 ? ($merchant['out_count'] / $total_for_merchant) * 100 : 0;
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
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($in_for_merchant); ?></div>
                    <div class="stat-label">Requests</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">MK<?php echo number_format($merchant['total_amount'], 2); ?></div>
                    <div class="stat-label">Amount</div>
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
            <h3 class="section-title">Completed Transactions by Hour</h3>
            <canvas id="malpayHourlyChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
            <h3 class="section-title">Transaction Amount by Merchant</h3>
            <canvas id="malpayMerchantChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Completed Transactions -->
    <div class="recent-transactions">
        <h3 class="section-title">Recent Completed Transactions - <?php echo date('M j, Y', strtotime($filter_date)); echo $filter_date !== $filter_date_end ? ' to ' . date('M j, Y', strtotime($filter_date_end)) : ''; ?></h3>
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
</div>

<!-- Hidden div for export data -->
<div id="exportData" style="display: none;" data-export='<?php echo json_encode($export_data); ?>'></div>

<script>
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    // Apply filter button
    document.getElementById('applyFilter').addEventListener('click', function() {
        const date = document.getElementById('filterDate').value;
        const dateEnd = document.getElementById('filterDateEnd').value;
        const merchant = document.getElementById('filterMerchant').value;
        
        const params = new URLSearchParams({
            date: date,
            date_end: dateEnd,
            merchant: merchant
        });
        
        window.location.href = '?' + params.toString();
    });

    // Enter key support for filters
    [document.getElementById('filterDate'), document.getElementById('filterDateEnd'), document.getElementById('filterMerchant')]
        .forEach(element => {
            element.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('applyFilter').click();
                }
            });
        });

    // Export functionality
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });

    document.getElementById('exportCSV').addEventListener('click', function() {
        const exportData = JSON.parse(document.getElementById('exportData').dataset.export);
        exportToCSV(exportData, 'malpay');
    });

    // MALPAY Charts
    const hourlyCtx = document.getElementById('malpayHourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($hourly_data, 'hour')); ?>,
            datasets: [
                {
                    label: 'Completed Transactions',
                    data: <?php echo json_encode(array_column($hourly_data, 'out_count')); ?>,
                    backgroundColor: '#27ae60',
                    borderColor: '#27ae60',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Amount (MK)',
                    data: <?php echo json_encode(array_column($hourly_data, 'out_amount')); ?>,
                    type: 'line',
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Completed Transactions and Amount by Hour' }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Hour of Day' }
                },
                y: {
                    title: { display: true, text: 'Number of Transactions' },
                    beginAtZero: true,
                    position: 'left'
                },
                y1: {
                    title: { display: true, text: 'Amount (MK)' },
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Merchant Amount Chart
    const merchantCtx = document.getElementById('malpayMerchantChart').getContext('2d');
    new Chart(merchantCtx, {
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
});

// PDF Export function
function exportToPDF(dashboardType) {
    const exportData = JSON.parse(document.getElementById('exportData').dataset.export);
    
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
    csvContent += "MalPay Transaction Report," + data.date_range + "\n\n";
    
    // Summary Statistics
    csvContent += "SUMMARY STATISTICS\n";
    csvContent += "Completed Transactions," + (data.stats.total_out_logs || 0) + "\n";
    csvContent += "Total Amount,MK" + (data.stats.total_amount ? Number(data.stats.total_amount).toFixed(2) : '0.00') + "\n";
    csvContent += "Average Response Time," + (data.stats.avg_response_time ? Number(data.stats.avg_response_time).toFixed(2) + 'ms' : '0ms') + "\n";
    csvContent += "Active Merchants," + data.merchants.length + "\n\n";
    
    // Merchant Data
    csvContent += "MERCHANT PERFORMANCE\n";
    csvContent += "Merchant,Completed Transactions,Total Amount\n";
    
    data.merchants.forEach(merchant => {
        csvContent += `"${merchant.MerchantName}",${merchant.out_count},MK${Number(merchant.total_amount).toFixed(2)}\n`;
    });
    
    csvContent += "\n";
    
    // Hourly Data
    csvContent += "HOURLY BREAKDOWN\n";
    csvContent += "Hour,Completed Transactions,Total Amount\n";
    
    data.hourly_data.forEach(hour => {
        csvContent += `${hour.hour}:00,${hour.out_count},MK${Number(hour.out_amount).toFixed(2)}\n`;
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
</script>