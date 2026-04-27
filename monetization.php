<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';

$user_stmt = $pdo->prepare("SELECT points, total_recycled FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_stats = $user_stmt->fetch();

$current_points = $user_stats['points'] ?? 0;
$total_recycled = $user_stats['total_recycled'] ?? 0;
$revenue_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(rl.revenue_generated), 0) as total_earnings 
    FROM recycling_logs rl
    WHERE rl.user_id = ? AND rl.status = 'approved'
");
$revenue_stmt->execute([$user_id]);
$total_earnings = $revenue_stmt->fetchColumn();
$monthly_earnings_stmt = $pdo->prepare("
    SELECT 
        MONTHNAME(rl.recycled_at) as month,
        COALESCE(SUM(rl.revenue_generated), 0) as monthly_earnings,
        COALESCE(SUM(rl.weight), 0) as monthly_weight,
        COUNT(rl.id) as monthly_activities
    FROM recycling_logs rl
    WHERE rl.user_id = ? AND YEAR(rl.recycled_at) = YEAR(CURDATE()) AND rl.status = 'approved'
    GROUP BY MONTH(rl.recycled_at)
    ORDER BY MONTH(rl.recycled_at)
");
$monthly_earnings_stmt->execute([$user_id]);
$monthly_earnings = $monthly_earnings_stmt->fetchAll(PDO::FETCH_ASSOC);
$category_earnings_stmt = $pdo->prepare("
    SELECT 
        rc.category_name,
        rc.icon,
        rc.color,
        COALESCE(SUM(rl.weight), 0) as total_weight,
        COALESCE(SUM(rl.revenue_generated), 0) as category_earnings,
        COALESCE(SUM(rl.points_earned), 0) as total_points,
        COUNT(rl.id) as submission_count
    FROM recycling_logs rl
    JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE rl.user_id = ? AND rl.status = 'approved'
    GROUP BY rc.id, rc.category_name, rc.icon, rc.color
    ORDER BY category_earnings DESC
");
$category_earnings_stmt->execute([$user_id]);
$category_earnings = $category_earnings_stmt->fetchAll(PDO::FETCH_ASSOC);
$impact_stmt = $pdo->prepare("
    SELECT 
        SUM(co2_saved) as total_co2,
        SUM(water_saved) as total_water,
        SUM(energy_saved) as total_energy
    FROM environmental_impact 
    WHERE user_id = ?
");
$impact_stmt->execute([$user_id]);
$environmental_impact = $impact_stmt->fetch();
$conversion_rate = 0.10;
$points_value = $current_points * $conversion_rate;
$avg_earning_per_kg = $total_recycled > 0 ? $total_earnings / $total_recycled : 0;
$recent_activities_stmt = $pdo->prepare("
    SELECT 
        rl.recycled_at,
        rc.category_name,
        rc.icon,
        rl.weight,
        rl.revenue_generated,
        rl.points_earned
    FROM recycling_logs rl
    JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE rl.user_id = ? AND rl.status = 'approved'
    ORDER BY rl.recycled_at DESC
    LIMIT 5
");
$recent_activities_stmt->execute([$user_id]);
$recent_activities = $recent_activities_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monetization Report - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">♻ EcoMina</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Back to Home</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h2 class="mb-4">💰 Recycling Monetization Report</h2>
    <div class="alert alert-info">
        <strong>Hello, <?= htmlspecialchars($name) ?>!</strong> Here's your complete recycling monetization summary.
    </div>
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="value-box">
                <div class="fs-6">Total Revenue</div>
                <div class="fs-3">₱<?= number_format($total_earnings, 2) ?></div>
                <small class="text-muted">From recycling</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="points-box">
                <div class="fs-6">Available Points</div>
                <div class="fs-3"><?= $current_points ?></div>
                <small class="text-muted">For rewards</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="text-success">♻ Total Recycled</h5>
                    <h3><?= number_format($total_recycled, 1) ?> kg</h3>
                    <small class="text-muted">Materials recycled</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="text-primary">📈 Avg. Rate</h5>
                    <h3>₱<?= number_format($avg_earning_per_kg, 2) ?></h3>
                    <small class="text-muted">Per kg recycled</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-success">🌱 CO₂ Saved</h5>
                    <h3><?= number_format($environmental_impact['total_co2'] ?? 0, 1) ?> kg</h3>
                    <small class="text-muted">Emissions reduced</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-primary">💧 Water Saved</h5>
                    <h3><?= number_format($environmental_impact['total_water'] ?? 0, 0) ?> L</h3>
                    <small class="text-muted">Water conserved</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-warning">⚡ Energy Saved</h5>
                    <h3><?= number_format($environmental_impact['total_energy'] ?? 0, 1) ?> kWh</h3>
                    <small class="text-muted">Energy conserved</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Earnings by Recycling Category</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-success">
                                <tr>
                                    <th>Category</th>
                                    <th>Weight</th>
                                    <th>Revenue</th>
                                    <th>Points</th>
                                    <th>Avg/kg</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_earnings as $earning): ?>
                                    <tr>
                                        <td>
                                            <span style="font-size: 1.2em;"><?= $earning['icon'] ?></span>
                                            <strong><?= $earning['category_name'] ?></strong>
                                        </td>
                                        <td><?= number_format($earning['total_weight'], 1) ?> kg</td>
                                        <td class="text-success">
                                            <strong>₱<?= number_format($earning['category_earnings'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= $earning['total_points'] ?> pts</span>
                                        </td>
                                        <td>
                                            <?php 
                                            $avg_per_kg = $earning['total_weight'] > 0 ? $earning['category_earnings'] / $earning['total_weight'] : 0;
                                            ?>
                                            <small class="text-muted">₱<?= number_format($avg_per_kg, 2) ?>/kg</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($category_earnings)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            No recycling activities yet. <a href="categorization.php">Start recycling to earn revenue!</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Recent Recycling Activities</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-info">
                                <tr>
                                    <th>Date</th>
                                    <th>Material</th>
                                    <th>Weight</th>
                                    <th>Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j', strtotime($activity['recycled_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span style="font-size: 1.1em;"><?= $activity['icon'] ?></span>
                                            <small><?= $activity['category_name'] ?></small>
                                        </td>
                                        <td><?= number_format($activity['weight'], 1) ?> kg</td>
                                        <td class="text-success">
                                            <strong>₱<?= number_format($activity['revenue_generated'], 2) ?></strong>
                                            <br>
                                            <small class="text-muted">+<?= $activity['points_earned'] ?> pts</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_activities)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            No recent activities
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-body text-center">
                    <h6>🚀 Boost Your Earnings</h6>
                    <div class="row mt-3">
                        <div class="col-6">
                            <a href="categorization.php" class="btn btn-success btn-sm w-100 mb-2">
                                ♻ Recycle More
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="rewards.php" class="btn btn-warning btn-sm w-100 mb-2">
                                🏆 Redeem Points
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($monthly_earnings)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Monthly Recycling Performance</h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h5 class="text-success">💡 Maximize Your Recycling Revenue</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li>✅ <strong>Focus on High-Value Materials:</strong> Metal cans (₱22/kg) and plastic bottles (₱18/kg) earn the most</li>
                                <li>✅ <strong>Clean Your Materials:</strong> Clean recyclables get better prices</li>
                                <li>✅ <strong>Separate Properly:</strong> Proper sorting increases value</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li>✅ <strong>Recycle Regularly:</strong> Consistent recycling builds steady income</li>
                                <li>✅ <strong>Track Performance:</strong> Monitor which materials earn you the most</li>
                                <li>✅ <strong>Redeem Points:</strong> Convert points to mobile load for immediate benefits</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($monthly_earnings)): ?>
<script>
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($monthly_earnings, 'month')) ?>,
            datasets: [
                {
                    label: 'Revenue (₱)',
                    data: <?= json_encode(array_column($monthly_earnings, 'monthly_earnings')) ?>,
                    backgroundColor: '#28a745',
                    borderColor: '#218838',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Weight (kg)',
                    data: <?= json_encode(array_column($monthly_earnings, 'monthly_weight')) ?>,
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
                        text: 'Revenue (₱)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + value;
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Weight (kg)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
</script>
<?php endif; ?>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>