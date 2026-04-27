<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

// Get filter parameters
$time_filter = $_GET['time_filter'] ?? '6months'; // 1month, 3months, 6months, 1year
$category_filter = $_GET['category_filter'] ?? 'all';

// Calculate date range based on filter
$date_ranges = [
    '1month' => 'INTERVAL 1 MONTH',
    '3months' => 'INTERVAL 3 MONTH',
    '6months' => 'INTERVAL 6 MONTH',
    '1year' => 'INTERVAL 1 YEAR'
];
$date_range = $date_ranges[$time_filter] ?? 'INTERVAL 6 MONTH';

$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$total_recycling = $pdo->query("SELECT COUNT(*) FROM recycling_logs WHERE status = 'approved'")->fetchColumn();
$total_rewards = $pdo->query("SELECT COUNT(*) FROM reward_claims")->fetchColumn();
$pending_approvals = $pdo->query("SELECT COUNT(*) FROM recycling_logs WHERE status = 'pending'")->fetchColumn();

$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_revenue), 0) FROM revenue_tracking")->fetchColumn();
$total_recycled = $pdo->query("SELECT COALESCE(SUM(weight), 0) FROM recycling_logs WHERE status = 'approved'")->fetchColumn();
$total_points = $pdo->query("SELECT COALESCE(SUM(points_earned), 0) FROM point_history")->fetchColumn();
$points_redeemed = $pdo->query("SELECT COALESCE(SUM(points_used), 0) FROM reward_claims")->fetchColumn();

// Recent activity for notifications
$recent_activities = $pdo->query("
    SELECT 
        rl.*, 
        u.name as user_name, 
        rc.category_name,
        rc.icon,
        TIMESTAMPDIFF(HOUR, rl.recycled_at, NOW()) as hours_ago
    FROM recycling_logs rl
    JOIN users u ON rl.user_id = u.id
    JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE rl.recycled_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY rl.recycled_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$category_stats = [];
$category_stmt = $pdo->query("
    SELECT 
        rc.category_name,
        rc.icon,
        rc.color,
        COALESCE(SUM(rl.weight), 0) as total_kg,
        COALESCE(SUM(rl.revenue_generated), 0) as total_revenue,
        COALESCE(SUM(rl.points_earned), 0) as total_points,
        COUNT(rl.id) as submission_count
    FROM recycling_categories rc
    LEFT JOIN recycling_logs rl ON rc.id = rl.category_id AND rl.status = 'approved'
    WHERE rc.is_active = 1 
    AND rc.category_name NOT IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    GROUP BY rc.id, rc.category_name, rc.icon, rc.color
    ORDER BY total_revenue DESC
");
while ($row = $category_stmt->fetch()) {
    $category_stats[$row['category_name']] = [
        'icon' => $row['icon'],
        'color' => $row['color'],
        'weight' => (float)$row['total_kg'],
        'revenue' => (float)$row['total_revenue'],
        'points' => (int)$row['total_points'],
        'submissions' => (int)$row['submission_count']
    ];
}

$monthly_data = [];
$month_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(rl.recycled_at, '%Y-%m') as month,
        MONTHNAME(rl.recycled_at) as month_name,
        COALESCE(SUM(rl.weight), 0) AS total_kg,
        COALESCE(SUM(rl.revenue_generated), 0) AS total_revenue,
        COUNT(rl.id) as activity_count
    FROM recycling_logs rl
    WHERE rl.recycled_at >= DATE_SUB(CURDATE(), $date_range) 
    AND rl.status = 'approved'
    AND rl.category_id NOT IN (
        SELECT id FROM recycling_categories 
        WHERE category_name IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    )
    GROUP BY DATE_FORMAT(rl.recycled_at, '%Y-%m'), MONTHNAME(rl.recycled_at)
    ORDER BY month
");
while ($row = $month_stmt->fetch()) {
    $monthly_data[$row['month_name']] = [
        'weight' => (float)$row['total_kg'],
        'revenue' => (float)$row['total_revenue'],
        'activities' => (int)$row['activity_count']
    ];
}

$user_registrations = [];
$user_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        MONTHNAME(created_at) as month_name,
        COUNT(*) as registrations
    FROM users 
    WHERE created_at >= DATE_SUB(CURDATE(), $date_range) AND is_admin = 0
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), MONTHNAME(created_at)
    ORDER BY month
");
while ($row = $user_stmt->fetch()) {
    $user_registrations[$row['month_name']] = (int)$row['registrations'];
}

$reward_trends = [];
$reward_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(claimed_at, '%Y-%m') as month,
        MONTHNAME(claimed_at) as month_name,
        COUNT(*) as claim_count,
        COALESCE(SUM(points_used), 0) as points_used
    FROM reward_claims 
    WHERE claimed_at >= DATE_SUB(CURDATE(), $date_range)
    GROUP BY DATE_FORMAT(claimed_at, '%Y-%m'), MONTHNAME(claimed_at)
    ORDER BY month
");
while ($row = $reward_stmt->fetch()) {
    $reward_trends[$row['month_name']] = [
        'claims' => (int)$row['claim_count'],
        'points' => (int)$row['points_used']
    ];
}

$top_users = $pdo->query("
    SELECT 
        u.name,
        u.barangay,
        u.user_type,
        COALESCE(SUM(rl.weight), 0) as total_recycled,
        COALESCE(SUM(rl.revenue_generated), 0) as total_earnings,
        COALESCE(SUM(rl.points_earned), 0) as total_points,
        COUNT(rl.id) as activity_count
    FROM users u
    LEFT JOIN recycling_logs rl ON u.id = rl.user_id AND rl.status = 'approved'
    WHERE u.is_admin = 0
    AND rl.category_id NOT IN (
        SELECT id FROM recycling_categories 
        WHERE category_name IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    )
    GROUP BY u.id, u.name, u.barangay, u.user_type
    ORDER BY total_recycled DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$environmental_impact = $pdo->query("
    SELECT 
        SUM(co2_saved) as total_co2,
        SUM(water_saved) as total_water,
        SUM(energy_saved) as total_energy
    FROM environmental_impact
")->fetch();

$avg_revenue_per_kg = $total_recycled > 0 ? $total_revenue / $total_recycled : 0;
$avg_per_user = $total_users > 0 ? $total_recycled / $total_users : 0;
$completion_rate = $total_recycling > 0 ? (($total_recycling - $pending_approvals) / $total_recycling) * 100 : 0;
$redemption_rate = $total_points > 0 ? ($points_redeemed / $total_points) * 100 : 0;

// Calculate trends (current period vs previous period)
$current_period_revenue = array_sum(array_column($monthly_data, 'revenue'));
$previous_period_revenue = $pdo->query("
    SELECT COALESCE(SUM(revenue_generated), 0) 
    FROM recycling_logs 
    WHERE recycled_at BETWEEN DATE_SUB(DATE_SUB(CURDATE(), $date_range), $date_range) 
    AND DATE_SUB(CURDATE(), $date_range)
    AND status = 'approved'
")->fetchColumn();

$revenue_trend = $previous_period_revenue > 0 ? 
    (($current_period_revenue - $previous_period_revenue) / $previous_period_revenue) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics Dashboard - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-neutral { color: #6c757d; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .small-chart {
            height: 200px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin.php">♻ EcoMina Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link position-relative" href="verify_recycling.php">
                        🔍 Verify
                        <?php if ($pending_approvals > 0): ?>
                            <span class="notification-badge"><?= $pending_approvals > 9 ? '9+' : $pending_approvals ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link" href="admin.php">← Back to Admin</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Header with Filters -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-2">📊 Admin Analytics Dashboard</h2>
            <p class="text-muted mb-0">Comprehensive overview of recycling system performance and trends</p>
        </div>
        <div class="col-md-4">
            <div class="filter-section">
                <form method="GET" class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Time Period</label>
                        <select name="time_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="1month" <?= $time_filter == '1month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="3months" <?= $time_filter == '3months' ? 'selected' : '' ?>>Last 3 Months</option>
                            <option value="6months" <?= $time_filter == '6months' ? 'selected' : '' ?>>Last 6 Months</option>
                            <option value="1year" <?= $time_filter == '1year' ? 'selected' : '' ?>>Last Year</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Export Data</label>
                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="exportDashboard()">
                            📥 Export Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">👥</h5>
                    <h4 class="mb-0"><?= $total_users ?></h4>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card">
                <div class="card-body py-3">
                    <h5 class="text-primary mb-1">♻</h5>
                    <h4 class="mb-0"><?= number_format($total_recycled, 0) ?>kg</h4>
                    <small class="text-muted">Recycled</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card">
                <div class="card-body py-3">
                    <h5 class="text-warning mb-1">💰</h5>
                    <h4 class="mb-0">₱<?= number_format($total_revenue, 0) ?></h4>
                    <small class="text-muted">Revenue</small>
                    <?php if ($revenue_trend != 0): ?>
                        <small class="<?= $revenue_trend > 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $revenue_trend > 0 ? '↗' : '↘' ?> <?= number_format(abs($revenue_trend), 1) ?>%
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card">
                <div class="card-body py-3">
                    <h5 class="text-info mb-1">⭐</h5>
                    <h4 class="mb-0"><?= number_format($total_points / 1000, 1) ?>K</h4>
                    <small class="text-muted">Points</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card bg-warning text-dark">
                <div class="card-body py-3">
                    <h5 class="mb-1">🔍</h5>
                    <h4 class="mb-0"><?= $pending_approvals ?></h4>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm stat-card">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">🏆</h5>
                    <h4 class="mb-0"><?= $total_rewards ?></h4>
                    <small class="text-muted">Rewards</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h6 class="text-success">Avg Revenue/kg</h6>
                    <h3>₱<?= number_format($avg_revenue_per_kg, 2) ?></h3>
                    <small class="text-muted">Per kilogram</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h6 class="text-primary">Avg per User</h6>
                    <h3><?= number_format($avg_per_user, 1) ?>kg</h3>
                    <small class="text-muted">Recycled per user</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h6 class="text-warning">Completion Rate</h6>
                    <h3><?= number_format($completion_rate, 1) ?>%</h3>
                    <small class="text-muted">Approved submissions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center stat-card">
                <div class="card-body">
                    <h6 class="text-info">Redemption Rate</h6>
                    <h3><?= number_format($redemption_rate, 1) ?>%</h3>
                    <small class="text-muted">Points redeemed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Charts Section -->
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Monthly Performance Chart -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">📈 Monthly Recycling Performance</h6>
                    <small><?= ucfirst(str_replace('months', ' months', $time_filter)) ?></small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Category Performance Chart -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">🏆 Category Performance</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Environmental Impact -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">🌍 Environmental Impact</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small>CO₂ Emissions Saved</small>
                            <strong class="text-success"><?= number_format($environmental_impact['total_co2'] ?? 0, 0) ?> kg</strong>
                        </div>
                        <small class="text-muted">Equivalent to <?= number_format(($environmental_impact['total_co2'] ?? 0) / 2000, 1) ?> cars off the road</small>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Water Conserved</small>
                            <strong class="text-primary"><?= number_format(($environmental_impact['total_water'] ?? 0) / 1000, 0) ?> kL</strong>
                        </div>
                        <small class="text-muted">Enough for <?= number_format(($environmental_impact['total_water'] ?? 0) / 150000, 0) ?> households</small>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small>Energy Saved</small>
                            <strong class="text-warning"><?= number_format($environmental_impact['total_energy'] ?? 0, 0) ?> kWh</strong>
                        </div>
                        <small class="text-muted">Power for <?= number_format(($environmental_impact['total_energy'] ?? 0) / 300, 0) ?> homes/month</small>
                    </div>
                </div>
            </div>

            <!-- Top Categories -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">🥇 Top Categories by Revenue</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $top_categories = array_slice($category_stats, 0, 5, true);
                    foreach ($top_categories as $name => $category): 
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <span style="font-size: 1.1em;"><?= $category['icon'] ?></span>
                                    <small><strong><?= $name ?></strong></small>
                                </div>
                                <small class="text-success">₱<?= number_format($category['revenue'], 0) ?></small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php 
                                $max_revenue = max(array_column($category_stats, 'revenue'));
                                $width = $max_revenue > 0 ? ($category['revenue'] / $max_revenue) * 100 : 0;
                                ?>
                                <div class="progress-bar" style="width: <?= $width ?>%; background-color: <?= $category['color'] ?>"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted"><?= number_format($category['weight'], 1) ?> kg</small>
                                <small class="text-muted"><?= $category['submissions'] ?> subs</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Reward Trends -->
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">🎁 Reward Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container small-chart">
                        <canvas id="rewardChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Total redeemed: <?= number_format($points_redeemed) ?> points
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity & Top Users -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">🕒 Recent Activity (24h)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="fw-bold"><?= htmlspecialchars($activity['user_name']) ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <?= $activity['icon'] ?> <?= $activity['category_name'] ?> • 
                                            <?= number_format($activity['weight'], 1) ?>kg
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?= $activity['hours_ago'] == 0 ? 'Just now' : $activity['hours_ago'] . 'h ago' ?>
                                    </small>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-success">+<?= $activity['points_earned'] ?> pts</small>
                                    <small class="text-warning">₱<?= number_format($activity['revenue_generated'], 2) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <small>No recent activity</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">👑 Top Recycling Users</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-success">
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Recycled</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($top_users, 0, 5) as $index => $user): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'danger' : 'info')) ?>">
                                                #<?= $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><strong><?= htmlspecialchars($user['name']) ?></strong></small>
                                            <br>
                                            <small class="text-muted"><?= $user['barangay'] ?></small>
                                        </td>
                                        <td>
                                            <strong><?= number_format($user['total_recycled'], 1) ?> kg</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= $user['total_points'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Growth Chart -->
    <?php if (!empty($user_registrations)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">📊 User Growth & Engagement</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Monthly Performance Chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($monthly_data)) ?>,
            datasets: [
                {
                    label: 'Weight (kg)',
                    data: <?= json_encode(array_column($monthly_data, 'weight')) ?>,
                    backgroundColor: '#28a745',
                    borderColor: '#218838',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenue (₱)',
                    data: <?= json_encode(array_column($monthly_data, 'revenue')) ?>,
                    backgroundColor: '#17a2b8',
                    borderColor: '#138496',
                    borderWidth: 1,
                    yAxisID: 'y1',
                    type: 'line'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue (₱)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + value;
                        }
                    }
                }
            }
        }
    });

    // Category Performance Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($category_stats)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($category_stats, 'revenue')) ?>,
                backgroundColor: <?= json_encode(array_column($category_stats, 'color')) ?>,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = <?= $total_revenue ?>;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ₱${value.toFixed(0)} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Reward Trends Chart
    new Chart(document.getElementById('rewardChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($reward_trends)) ?>,
            datasets: [{
                label: 'Reward Claims',
                data: <?= json_encode(array_column($reward_trends, 'claims')) ?>,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255,193,7,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // User Growth Chart
    <?php if (!empty($user_registrations)): ?>
    new Chart(document.getElementById('userChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($user_registrations)) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode(array_values($user_registrations)) ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Export functionality
    function exportDashboard() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Add title
        doc.setFontSize(20);
        doc.text('EcoMina Analytics Report', 20, 20);
        doc.setFontSize(12);
        doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 20, 30);
        doc.text(`Time Period: <?= ucfirst(str_replace('months', ' months', $time_filter)) ?>`, 20, 40);
        
        // Add summary statistics
        doc.text('Summary Statistics:', 20, 60);
        doc.text(`Total Users: <?= $total_users ?>`, 30, 70);
        doc.text(`Total Recycled: <?= number_format($total_recycled, 0) ?> kg`, 30, 80);
        doc.text(`Total Revenue: ₱<?= number_format($total_revenue, 0) ?>`, 30, 90);
        doc.text(`Pending Approvals: <?= $pending_approvals ?>`, 30, 100);
        
        // Save the PDF
        doc.save('EcoMina-Analytics-Report-<?= date('Y-m-d') ?>.pdf');
    }
</script>

<!-- Include jsPDF for export functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>