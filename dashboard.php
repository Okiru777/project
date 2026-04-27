<?php
require 'db.php';

$total_recycled = $pdo->query("SELECT COALESCE(SUM(weight), 0) as total_kg FROM recycling_logs WHERE status = 'approved'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_admin = 0")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_revenue), 0) as total_revenue FROM revenue_tracking")->fetchColumn();
$total_points = $pdo->query("SELECT COALESCE(SUM(points_earned), 0) as total_points FROM point_history")->fetchColumn();

$category_stats = [];
$category_stmt = $pdo->query("
    SELECT 
        rc.category_name,
        rc.icon,
        rc.color,
        COALESCE(SUM(rl.weight), 0) as total_kg,
        COALESCE(SUM(rl.revenue_generated), 0) as total_revenue
    FROM recycling_categories rc
    LEFT JOIN recycling_logs rl ON rc.id = rl.category_id AND rl.status = 'approved'
    WHERE rc.is_active = 1
    AND rc.category_name NOT IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    GROUP BY rc.id, rc.category_name, rc.icon, rc.color
    ORDER BY total_kg DESC
");
while ($row = $category_stmt->fetch()) {
    $category_stats[$row['category_name']] = [
        'icon' => $row['icon'],
        'color' => $row['color'],
        'weight' => (float)$row['total_kg'],
        'revenue' => (float)$row['total_revenue']
    ];
}

$monthly_data = [];
$month_stmt = $pdo->query("
    SELECT 
        MONTHNAME(rl.recycled_at) AS month, 
        COALESCE(SUM(rl.weight), 0) AS total_kg,
        COALESCE(SUM(rl.revenue_generated), 0) AS total_revenue
    FROM recycling_logs rl
    WHERE YEAR(rl.recycled_at) = YEAR(CURDATE()) 
    AND rl.status = 'approved'
    AND rl.category_id NOT IN (
        SELECT id FROM recycling_categories 
        WHERE category_name IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    )
    GROUP BY MONTH(rl.recycled_at)
    ORDER BY MONTH(rl.recycled_at)
");
while ($row = $month_stmt->fetch()) {
    $monthly_data[$row['month']] = [
        'weight' => (float)$row['total_kg'],
        'revenue' => (float)$row['total_revenue']
    ];
}

$user_registrations = [];
$user_stmt = $pdo->query("
    SELECT 
        MONTHNAME(created_at) as month, 
        COUNT(*) as registrations
    FROM users 
    WHERE YEAR(created_at) = YEAR(CURDATE()) AND is_admin = 0
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
");
while ($row = $user_stmt->fetch()) {
    $user_registrations[$row['month']] = (int)$row['registrations'];
}

$environmental_impact = $pdo->query("
    SELECT 
        SUM(co2_saved) as total_co2,
        SUM(water_saved) as total_water,
        SUM(energy_saved) as total_energy
    FROM environmental_impact
")->fetch();

$top_users = $pdo->query("
    SELECT 
        u.name,
        u.barangay,
        COALESCE(SUM(rl.weight), 0) as total_recycled,
        COALESCE(SUM(rl.revenue_generated), 0) as total_earnings,
        u.points
    FROM users u
    LEFT JOIN recycling_logs rl ON u.id = rl.user_id AND rl.status = 'approved'
    WHERE u.is_admin = 0
    AND rl.category_id NOT IN (
        SELECT id FROM recycling_categories 
        WHERE category_name IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    )
    GROUP BY u.id, u.name, u.barangay, u.points
    ORDER BY total_recycled DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$avg_per_user = $total_users > 0 ? $total_recycled / $total_users : 0;
$avg_revenue_per_kg = $total_recycled > 0 ? $total_revenue / $total_recycled : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Dashboard - EcoMina</title>
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
    <h2 class="mb-4">📊 Community Recycling Dashboard</h2>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-success">♻ Total Recycled</h5>
                    <h3><?= number_format($total_recycled, 1) ?> kg</h3>
                    <small class="text-muted">Community total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-primary">👥 Active Users</h5>
                    <h3><?= $total_users ?></h3>
                    <small class="text-muted">Recycling participants</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-warning">💰 Total Revenue</h5>
                    <h3>₱<?= number_format($total_revenue, 2) ?></h3>
                    <small class="text-muted">Generated from recycling</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-info">⭐ Points Awarded</h5>
                    <h3><?= number_format($total_points) ?></h3>
                    <small class="text-muted">Total points distributed</small>
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
                    <small class="text-muted">Emissions prevented</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-primary">💧 Water Saved</h5>
                    <h3><?= number_format(($environmental_impact['total_water'] ?? 0) / 1000, 1) ?> kL</h3>
                    <small class="text-muted">Water conserved</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-warning">⚡ Energy Saved</h5>
                    <h3><?= number_format($environmental_impact['total_energy'] ?? 0, 0) ?> kWh</h3>
                    <small class="text-muted">Energy conserved</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Recycling by Material Type</h5>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Monthly Recycling Trends</h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">🏆 Top Recyclers</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-success">
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Barangay</th>
                                    <th>Recycled</th>
                                    <th>Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $index => $user): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'danger' : 'info')) ?>">
                                                #<?= $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= $user['points'] ?> pts</small>
                                        </td>
                                        <td><?= htmlspecialchars($user['barangay']) ?></td>
                                        <td>
                                            <strong><?= number_format($user['total_recycled'], 1) ?> kg</strong>
                                        </td>
                                        <td class="text-success">
                                            <strong>₱<?= number_format($user['total_earnings'], 2) ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_users)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            No recycling data available yet.
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
                    <h5 class="card-title">📈 Community Statistics</h5>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-success">Average per User</h6>
                                <h4><?= number_format($avg_per_user, 1) ?> kg</h4>
                                <small class="text-muted">Recycled per person</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-primary">Avg. Rate</h6>
                                <h4>₱<?= number_format($avg_revenue_per_kg, 2) ?></h4>
                                <small class="text-muted">Per kg recycled</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h6 class="text-warning">User Growth</h6>
                                <h4><?= array_sum($user_registrations) ?></h4>
                                <small class="text-muted">New users this year</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h6 class="text-info">Active Months</h6>
                                <h4><?= count($monthly_data) ?></h4>
                                <small class="text-muted">Months with activity</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">💰 Material Value Ranking</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Price/kg</th>
                                    <th>Points/kg</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $material_values = $pdo->query("
                                    SELECT category_name, icon, price_per_kg, points_per_kg 
                                    FROM recycling_categories 
                                    WHERE is_active = 1 
                                    AND category_name NOT IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
                                    ORDER BY price_per_kg DESC
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($material_values as $material): 
                                ?>
                                    <tr>
                                        <td>
                                            <span style="font-size: 1.1em;"><?= $material['icon'] ?></span>
                                            <?= $material['category_name'] ?>
                                        </td>
                                        <td class="text-success">
                                            <strong>₱<?= number_format($material['price_per_kg'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?= $material['points_per_kg'] ?> pts</span>
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

    <?php if (!empty($user_registrations)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">👥 User Registration Trend</h5>
                    <div class="chart-container">
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h4 class="text-success">🌍 Community Impact</h4>
                    <p class="lead mb-0">
                        Together, we've recycled <strong><?= number_format($total_recycled, 1) ?> kg</strong> of materials, 
                        generated <strong>₱<?= number_format($total_revenue, 2) ?></strong> in revenue, 
                        and saved <strong><?= number_format($environmental_impact['total_co2'] ?? 0, 1) ?> kg</strong> of CO₂ emissions!
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($category_stats)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($category_stats, 'weight')) ?>,
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
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = <?= array_sum(array_column($category_stats, 'weight')) ?>;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value.toFixed(1)} kg (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

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
</script>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>