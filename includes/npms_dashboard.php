<?php
require_once 'config.php';

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? 'all';

try {
    $conn = connectDB(Config::$npms_config);
    
    // Build WHERE clause for filters
    $where_clause = "WHERE CAST(TransactionDate as DATE) = ?";
    $params = [$filter_date];
    
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
        WHERE CAST(TransactionDate as DATE) = ?
        GROUP BY DATEPART(HOUR, TransactionDate)
        ORDER BY hour
    ";
    
    $hourly_data = $conn->prepare($hourly_sql);
    $hourly_data->execute([$filter_date]);
    $hourly_data = $hourly_data->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service distribution
    $service_sql = "
        SELECT 
            Service,
            COUNT(*) as count,
            SUM(TransactionAmount) as amount
        FROM TokenTransactions 
        WHERE CAST(TransactionDate as DATE) = ?
        GROUP BY Service
        ORDER BY count DESC
    ";
    
    $service_data = $conn->prepare($service_sql);
    $service_data->execute([$filter_date]);
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
    
} catch (PDOException $e) {
    die("NPMS Error: " . $e->getMessage());
}
?>

<div id="npms" class="tab-content">
    <!-- Filters -->
    <div class="filters">
        <div class="filter-group">
            <label for="filterDate">Date:</label>
            <input type="date" id="filterDate" name="filterDate" value="<?php echo $filter_date; ?>">
            
            <label for="filterStatus">Status:</label>
            <select id="filterStatus" name="filterStatus">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="successful" <?php echo $filter_status === 'successful' ? 'selected' : ''; ?>>Successful</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
            </select>
            
            <button class="filter-btn" id="applyFilter">Apply Filters</button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total Transactions</h3>
            <div class="stat-number stat-total"><?php echo number_format($stats['total_transactions']); ?></div>
            <p><?php echo date('M j, Y', strtotime($filter_date)); ?></p>
        </div>
        
        <div class="stat-card success">
            <h3>Successful</h3>
            <div class="stat-number stat-success"><?php echo number_format($stats['successful']); ?></div>
            <p><?php echo $stats['total_transactions'] > 0 ? round(($stats['successful']/$stats['total_transactions'])*100, 1) : 0; ?>% success rate</p>
        </div>
        
        <div class="stat-card pending">
            <h3>Pending</h3>
            <div class="stat-number stat-pending"><?php echo number_format($stats['pending']); ?></div>
            <p>Awaiting processing</p>
        </div>
        
        <div class="stat-card failed">
            <h3>Failed</h3>
            <div class="stat-number stat-failed"><?php echo number_format($stats['failed']); ?></div>
            <p>Requires attention</p>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3 class="chart-title">Hourly Transaction Trends</h3>
            <canvas id="npmsHourlyChart" height="300"></canvas>
        </div>
        
        <div class="chart-container">
            <h3 class="chart-title">Service Distribution</h3>
            <canvas id="npmsServiceChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="recent-transactions">
        <h3 class="chart-title">Recent Transactions - <?php echo date('M j, Y', strtotime($filter_date)); ?></h3>
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

<script>
// NPMS Charts
document.addEventListener('DOMContentLoaded', function() {
    // Hourly Trends Chart
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
</script>