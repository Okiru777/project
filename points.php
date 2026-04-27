<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

$user_stmt = $pdo->prepare("SELECT points, total_recycled FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_stats = $user_stmt->fetch();

$current_points = $user_stats['points'] ?? 0;
$total_recycled = $user_stats['total_recycled'] ?? 0;

$history_stmt = $pdo->prepare("
    SELECT 
        ph.date_earned,
        rc.category_name,
        rc.icon,
        rc.color,
        ph.weight_kg,
        ph.points_earned,
        ph.activity_type
    FROM point_history ph
    JOIN recycling_categories rc ON ph.category_id = rc.id
    WHERE ph.user_id = ? 
    ORDER BY ph.date_earned DESC
");
$history_stmt->execute([$user_id]);
$point_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_earned = 0;
$points_by_category = [];
$points_by_month = [];

foreach ($point_history as $entry) {
    $total_earned += $entry['points_earned'];
    $category = $entry['category_name'];
    if (!isset($points_by_category[$category])) {
        $points_by_category[$category] = [
            'icon' => $entry['icon'],
            'color' => $entry['color'],
            'total_points' => 0,
            'total_weight' => 0
        ];
    }
    $points_by_category[$category]['total_points'] += $entry['points_earned'];
    $points_by_category[$category]['total_weight'] += $entry['weight_kg'];
    $month = date('F Y', strtotime($entry['date_earned']));
    if (!isset($points_by_month[$month])) {
        $points_by_month[$month] = 0;
    }
    $points_by_month[$month] += $entry['points_earned'];
}

$reward_stmt = $pdo->prepare("
    SELECT 
        reward_title,
        points_used,
        claimed_at,
        status
    FROM reward_claims 
    WHERE user_id = ? 
    ORDER BY claimed_at DESC
");
$reward_stmt->execute([$user_id]);
$reward_history = $reward_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_redeemed = 0;
foreach ($reward_history as $reward) {
    $total_redeemed += $reward['points_used'];
}

$net_points = $total_earned - $total_redeemed;

$conversion_rate = 0.10;
$points_value = $current_points * $conversion_rate;
$potential_rewards = [
    ['points' => 100, 'reward' => '₱10 Mobile Load'],
    ['points' => 200, 'reward' => '₱20 Mobile Load'],
    ['points' => 500, 'reward' => '₱50 Mobile Load'],
    ['points' => 1000, 'reward' => '₱100 Mobile Load']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Points Tracker - EcoMina</title>
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
    <h2 class="mb-4">⭐ Recycling Points Tracker</h2>

    <div class="alert alert-info">
        <strong>Hello, <?= htmlspecialchars($user_name) ?>!</strong> Track your recycling points and see how close you are to exciting rewards.
    </div>
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="points-box">
                <div class="fs-6">Available Points</div>
                <div class="fs-3"><?= $current_points ?></div>
                <small class="text-muted">Ready to redeem</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="value-box">
                <div class="fs-6">Total Earned</div>
                <div class="fs-3"><?= $total_earned ?></div>
                <small class="text-muted">Lifetime points</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="text-success">♻ Recycled</h5>
                    <h3><?= number_format($total_recycled, 1) ?> kg</h3>
                    <small class="text-muted">Total materials</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="text-warning">🏆 Rewards</h5>
                    <h3><?= count($reward_history) ?></h3>
                    <small class="text-muted">Claimed</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">📊 Points by Material</h5>
                    <?php if (!empty($points_by_category)): ?>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <table class="table table-sm">
                                <tbody>
                                    <?php foreach ($points_by_category as $category => $data): ?>
                                        <tr>
                                            <td>
                                                <span style="font-size: 1.1em;"><?= $data['icon'] ?></span>
                                                <?= $category ?>
                                            </td>
                                            <td class="text-end">
                                                <strong><?= $data['total_points'] ?> pts</strong>
                                                <br>
                                                <small class="text-muted"><?= number_format($data['total_weight'], 1) ?> kg</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <p>No points earned yet.</p>
                            <a href="categorization.php" class="btn btn-success btn-sm">Start Recycling</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">🎯 Reward Progress</h6>
                    <?php foreach ($potential_rewards as $reward): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small><?= $reward['reward'] ?></small>
                                <small><?= $reward['points'] ?> pts</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <?php 
                                $progress = min(100, ($current_points / $reward['points']) * 100);
                                $progress_class = $progress >= 100 ? 'bg-success' : 'bg-warning';
                                ?>
                                <div class="progress-bar <?= $progress_class ?>" 
                                     style="width: <?= $progress ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php if ($current_points >= $reward['points']): ?>
                                    ✅ Ready to claim!
                                <?php else: ?>
                                    <?= $reward['points'] - $current_points ?> more points needed
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($current_points > 0): ?>
                        <div class="text-center mt-3">
                            <a href="rewards.php" class="btn btn-success btn-sm w-100">
                                🏆 View All Rewards
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">📈 Points History</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-success">
                                <tr>
                                    <th>Date</th>
                                    <th>Material</th>
                                    <th>Weight</th>
                                    <th>Points</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($point_history as $entry): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($entry['date_earned'])) ?></small>
                                        </td>
                                        <td>
                                            <span style="font-size: 1.1em;"><?= $entry['icon'] ?></span>
                                            <?= $entry['category_name'] ?>
                                        </td>
                                        <td><?= number_format($entry['weight_kg'], 2) ?> kg</td>
                                        <td>
                                            <span class="badge bg-success">+<?= $entry['points_earned'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $entry['activity_type'] == 'recycling' ? 'primary' : 'warning' ?>">
                                                <?= ucfirst($entry['activity_type']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($point_history)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <p>No points history yet.</p>
                                            <a href="categorization.php" class="btn btn-success">Start Recycling to Earn Points</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">🎁 Reward History</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-warning">
                                <tr>
                                    <th>Date</th>
                                    <th>Reward</th>
                                    <th>Points Used</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reward_history as $reward): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($reward['claimed_at'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= $reward['reward_title'] ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">-<?= $reward['points_used'] ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = [
                                                'pending' => 'bg-warning',
                                                'processed' => 'bg-info',
                                                'completed' => 'bg-success'
                                            ][$reward['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($reward['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reward_history)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            No rewards claimed yet.
                                            <?php if ($current_points > 0): ?>
                                                <br>
                                                <a href="rewards.php" class="btn btn-warning btn-sm mt-2">Claim Your First Reward</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">💰 Points Summary</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <small class="text-success">Earned</small>
                                <h5 class="mb-0">+<?= $total_earned ?></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <small class="text-danger">Redeemed</small>
                                <h5 class="mb-0">-<?= $total_redeemed ?></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <small class="text-primary">Current</small>
                                <h5 class="mb-0"><?= $current_points ?></h5>
                            </div>
                        </div>
                    </div>
                    <?php if ($current_points > 0): ?>
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Your points are worth approximately <strong>₱<?= number_format($points_value, 2) ?></strong>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-success">♻ Earn More Points</h5>
                    <p class="text-muted">Recycle more materials to increase your points balance</p>
                    <a href="categorization.php" class="btn btn-success">Recycle Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-warning">🏆 Redeem Points</h5>
                    <p class="text-muted">Convert your points to mobile load and other rewards</p>
                    <a href="rewards.php" class="btn btn-warning">View Rewards</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($points_by_category)): ?>
<script>
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($points_by_category)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($points_by_category, 'total_points')) ?>,
                backgroundColor: <?= json_encode(array_column($points_by_category, 'color')) ?>,
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
                    labels: {
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = <?= $total_earned ?>;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return `${label}: ${value} pts (${percentage}%)`;
                        }
                    }
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