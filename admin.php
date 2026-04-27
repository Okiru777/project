<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

$name = $_SESSION['name'] ?? 'Admin';

require 'db.php';

$users_count = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$recycling_count = $pdo->query("SELECT COUNT(*) FROM recycling_logs WHERE status = 'approved'")->fetchColumn();
$rewards_count = $pdo->query("SELECT COUNT(*) FROM reward_claims")->fetchColumn();
$pending_approvals = $pdo->query("SELECT COUNT(*) FROM recycling_logs WHERE status = 'pending'")->fetchColumn();

$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_revenue), 0) FROM revenue_tracking")->fetchColumn();
$total_recycled = $pdo->query("SELECT COALESCE(SUM(weight), 0) FROM recycling_logs WHERE status = 'approved'")->fetchColumn();
$total_points = $pdo->query("SELECT COALESCE(SUM(points_earned), 0) FROM point_history")->fetchColumn();

$recent_recycling = $pdo->query("
    SELECT rl.*, u.name as user_name, rc.category_name, rc.icon
    FROM recycling_logs rl
    JOIN users u ON rl.user_id = u.id
    JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE rc.category_name NOT IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    ORDER BY rl.recycled_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recent_rewards = $pdo->query("
    SELECT rc.*, u.name as user_name
    FROM reward_claims rc
    JOIN users u ON rc.user_id = u.id
    ORDER BY rc.claimed_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$category_performance = $pdo->query("
    SELECT 
        rc.category_name,
        rc.icon,
        rc.color,
        COUNT(rl.id) as submission_count,
        COALESCE(SUM(rl.weight), 0) as total_weight,
        COALESCE(SUM(rl.revenue_generated), 0) as total_revenue
    FROM recycling_categories rc
    LEFT JOIN recycling_logs rl ON rc.id = rl.category_id AND rl.status = 'approved'
    WHERE rc.is_active = 1 
    AND rc.category_name NOT IN ('E-WASTE', 'TEXTILES', 'BATTERIES')
    GROUP BY rc.id, rc.category_name, rc.icon, rc.color
    ORDER BY total_revenue DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$environmental_impact = $pdo->query("
    SELECT 
        SUM(co2_saved) as total_co2,
        SUM(water_saved) as total_water,
        SUM(energy_saved) as total_energy
    FROM environmental_impact
")->fetch();

$avg_revenue_per_kg = $total_recycled > 0 ? $total_revenue / $total_recycled : 0;
$avg_per_user = $users_count > 0 ? $total_recycled / $users_count : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">📊 Analytics</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h1 class="mb-2">👋 Welcome, <?= htmlspecialchars($name) ?>!</h1>
                    <p class="lead mb-0">You are logged in as an <strong>Administrator</strong>. Manage the recycling system and monitor community activities.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">👥</h5>
                    <h4 class="mb-0"><?= $users_count ?></h4>
                    <small class="text-muted">Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-primary mb-1">♻</h5>
                    <h4 class="mb-0"><?= $recycling_count ?></h4>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-warning mb-1">💰</h5>
                    <h4 class="mb-0">₱<?= number_format($total_revenue, 0) ?></h4>
                    <small class="text-muted">Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-info mb-1">⭐</h5>
                    <h4 class="mb-0"><?= number_format($total_points / 1000, 1) ?>K</h4>
                    <small class="text-muted">Points</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm bg-warning text-dark">
                <div class="card-body py-3">
                    <h5 class="mb-1">🔍</h5>
                    <h4 class="mb-0"><?= $pending_approvals ?></h4>
                    <small>Pending Verification</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">🏆</h5>
                    <h4 class="mb-0"><?= $rewards_count ?></h4>
                    <small class="text-muted">Rewards</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="row g-4">
                <!-- Verification Card - Highlighted -->
                <div class="col-md-6">
                    <div class="card shadow border-0 h-100 border-warning">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="fs-2 text-warning me-3">🔍</div>
                                <div>
                                    <h5 class="card-title mb-1">Verify Submissions</h5>
                                    <p class="text-muted mb-0">Review pending recycling</p>
                                </div>
                            </div>
                            <p class="card-text flex-grow-1">Review proof images and approve or reject recycling submissions from users.</p>
                            <div class="mt-auto">
                                <?php if ($pending_approvals > 0): ?>
                                    <a href="verify_recycling.php" class="btn btn-warning w-100 fw-bold">
                                        🔍 Verify Now (<?= $pending_approvals ?>)
                                    </a>
                                <?php else: ?>
                                    <a href="verify_recycling.php" class="btn btn-outline-warning w-100">
                                        🔍 View Verification
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow border-0 h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="fs-2 text-success me-3">📊</div>
                                <div>
                                    <h5 class="card-title mb-1">Analytics Dashboard</h5>
                                    <p class="text-muted mb-0">Comprehensive system analytics</p>
                                </div>
                            </div>
                            <p class="card-text flex-grow-1">View detailed charts, reports, and performance metrics across all recycling activities.</p>
                            <div class="mt-auto">
                                <a href="admin_dashboard.php" class="btn btn-success w-100">Open Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow border-0 h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="fs-2 text-primary me-3">👥</div>
                                <div>
                                    <h5 class="card-title mb-1">User Management</h5>
                                    <p class="text-muted mb-0">Manage system users</p>
                                </div>
                            </div>
                            <p class="card-text flex-grow-1">View, edit, and manage user accounts, permissions, and recycling activities.</p>
                            <div class="mt-auto">
                                <a href="manage_users.php" class="btn btn-primary w-100">Manage Users</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow border-0 h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="fs-2 text-info me-3">🗂️</div>
                                <div>
                                    <h5 class="card-title mb-1">Categories Management</h5>
                                    <p class="text-muted mb-0">Manage recycling categories</p>
                                </div>
                            </div>
                            <p class="card-text flex-grow-1">Add, edit, or disable recycling categories and adjust points/price rates.</p>
                            <div class="mt-auto">
                                <a href="manage_categories.php" class="btn btn-info w-100">Manage Categories</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Recycling Activities -->
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">🔄 Recent Recycling Activities</h5>
                            <a href="monitoring_waste.php" class="btn btn-light btn-sm">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Material</th>
                                            <th>Weight</th>
                                            <th>Points</th>
                                            <th>Revenue</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_recycling as $activity): ?>
                                            <tr>
                                                <td>
                                                    <small><?= htmlspecialchars($activity['user_name']) ?></small>
                                                </td>
                                                <td>
                                                    <span style="font-size: 1.1em;"><?= $activity['icon'] ?></span>
                                                    <small><?= $activity['category_name'] ?></small>
                                                </td>
                                                <td><?= number_format($activity['weight'], 1) ?> kg</td>
                                                <td>
                                                    <span class="badge bg-success"><?= $activity['points_earned'] ?></span>
                                                </td>
                                                <td class="text-success">
                                                    <strong>₱<?= number_format($activity['revenue_generated'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, g:i A', strtotime($activity['recycled_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = [
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger'
                                                    ][$activity['status']] ?? 'bg-secondary';
                                                    ?>
                                                    <span class="badge <?= $status_class ?>">
                                                        <?= ucfirst($activity['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_recycling)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-3">
                                                    No recent recycling activities
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">
            <!-- System Overview -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">📈 System Overview</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Total Recycled</small>
                        <div class="d-flex justify-content-between">
                            <strong><?= number_format($total_recycled, 1) ?> kg</strong>
                            <small class="text-success">♻</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Avg per User</small>
                        <div class="d-flex justify-content-between">
                            <strong><?= number_format($avg_per_user, 1) ?> kg</strong>
                            <small class="text-primary">👤</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Avg Rate</small>
                        <div class="d-flex justify-content-between">
                            <strong>₱<?= number_format($avg_revenue_per_kg, 2) ?>/kg</strong>
                            <small class="text-warning">💰</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Environmental Impact</small>
                        <div class="d-flex justify-content-between">
                            <strong><?= number_format($environmental_impact['total_co2'] ?? 0, 0) ?> kg CO₂</strong>
                            <small class="text-success">🌱</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Categories -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">🏆 Top Categories</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($category_performance as $category): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <span style="font-size: 1.1em;"><?= $category['icon'] ?></span>
                                    <small><strong><?= $category['category_name'] ?></strong></small>
                                </div>
                                <small class="text-success">₱<?= number_format($category['total_revenue'], 0) ?></small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php 
                                $max_revenue = max(array_column($category_performance, 'total_revenue'));
                                $width = $max_revenue > 0 ? ($category['total_revenue'] / $max_revenue) * 100 : 0;
                                ?>
                                <div class="progress-bar" style="width: <?= $width ?>%; background-color: <?= $category['color'] ?>"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted"><?= number_format($category['total_weight'], 1) ?> kg</small>
                                <small class="text-muted"><?= $category['submission_count'] ?> submissions</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">⚡ Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($pending_approvals > 0): ?>
                            <a href="verify_recycling.php" class="btn btn-warning btn-sm fw-bold">
                                🔍 Verify Pending (<?= $pending_approvals ?>)
                            </a>
                        <?php else: ?>
                            <a href="verify_recycling.php" class="btn btn-outline-warning btn-sm">
                                🔍 Check Verification
                            </a>
                        <?php endif; ?>
                        <a href="manage_users.php" class="btn btn-outline-primary btn-sm">
                            👥 Add New User
                        </a>
                        <a href="manage_categories.php" class="btn btn-outline-info btn-sm">
                            🗂️ Manage Categories
                        </a>
                        <a href="admin_dashboard.php" class="btn btn-outline-success btn-sm">
                            📊 Generate Report
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Rewards -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">🎁 Recent Rewards</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($recent_rewards as $reward): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <small><strong><?= $reward['user_name'] ?></strong></small>
                                <small class="text-danger">-<?= $reward['points_used'] ?> pts</small>
                            </div>
                            <small class="text-muted"><?= $reward['reward_title'] ?></small>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?= date('M j', strtotime($reward['claimed_at'])) ?></small>
                                <span class="badge bg-<?= $reward['status'] == 'completed' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($reward['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($recent_rewards)): ?>
                        <div class="text-center text-muted py-2">
                            <small>No recent rewards</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>