<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

require 'db.php';

if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $pdo->beginTransaction();
    try {
        // Update recycling log status
        $stmt = $pdo->prepare("UPDATE recycling_logs SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        
        // Also update verification status
        $stmt = $pdo->prepare("UPDATE recycling_verification SET status = 'approved', verified_at = NOW() WHERE recycling_log_id = ?");
        $stmt->execute([$id]);
        
        // Award points to user
        $log_stmt = $pdo->prepare("SELECT user_id, points_earned, weight FROM recycling_logs WHERE id = ?");
        $log_stmt->execute([$id]);
        $log = $log_stmt->fetch();
        
        if ($log) {
            $stmt = $pdo->prepare("UPDATE users SET points = points + ?, total_recycled = total_recycled + ? WHERE id = ?");
            $stmt->execute([$log['points_earned'], $log['weight'], $log['user_id']]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Submission approved successfully! Points awarded to user.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error approving submission: " . $e->getMessage();
    }
    header("Location: monitoring_waste.php");
    exit();
}

if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $pdo->beginTransaction();
    try {
        // Update recycling log status
        $stmt = $pdo->prepare("UPDATE recycling_logs SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        
        // Also update verification status
        $stmt = $pdo->prepare("UPDATE recycling_verification SET status = 'rejected', verified_at = NOW() WHERE recycling_log_id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Submission rejected successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error rejecting submission: " . $e->getMessage();
    }
    header("Location: monitoring_waste.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->beginTransaction();
    try {
        // First delete from verification table (child table)
        $stmt = $pdo->prepare("DELETE FROM recycling_verification WHERE recycling_log_id = ?");
        $stmt->execute([$id]);
        
        // Then delete from recycling_logs (parent table)
        $stmt = $pdo->prepare("DELETE FROM recycling_logs WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Submission deleted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting submission: " . $e->getMessage();
    }
    header("Location: monitoring_waste.php");
    exit();
}

if (isset($_GET['process_reward'])) {
    $id = intval($_GET['process_reward']);
    $pdo->prepare("UPDATE reward_claims SET status = 'processed' WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = "Reward marked as processed!";
    header("Location: monitoring_waste.php#rewards");
    exit();
}

if (isset($_GET['complete_reward'])) {
    $id = intval($_GET['complete_reward']);
    $pdo->prepare("UPDATE reward_claims SET status = 'completed' WHERE id = ?")->execute([$id]);
    $_SESSION['success'] = "Reward marked as completed!";
    header("Location: monitoring_waste.php#rewards");
    exit();
}

// Display success/error messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

$recycling_query = "
    SELECT 
        rl.*, 
        u.name AS username, 
        u.email,
        u.barangay,
        rc.category_name,
        rc.icon,
        rc.color
    FROM recycling_logs rl 
    LEFT JOIN users u ON rl.user_id = u.id 
    LEFT JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE 1=1
";

$recycling_params = [];
if (!empty($search)) {
    $recycling_query .= " AND (u.name LIKE ? OR u.email LIKE ? OR rc.category_name LIKE ?)";
    $search_param = "%$search%";
    $recycling_params[] = $search_param;
    $recycling_params[] = $search_param;
    $recycling_params[] = $search_param;
}

if (!empty($status_filter) && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $recycling_query .= " AND rl.status = ?";
    $recycling_params[] = $status_filter;
}

if (!empty($category_filter)) {
    $recycling_query .= " AND rc.category_name = ?";
    $recycling_params[] = $category_filter;
}

if (!empty($date_from)) {
    $recycling_query .= " AND DATE(rl.recycled_at) >= ?";
    $recycling_params[] = $date_from;
}

if (!empty($date_to)) {
    $recycling_query .= " AND DATE(rl.recycled_at) <= ?";
    $recycling_params[] = $date_to;
}

switch ($sort) {
    case 'oldest':
        $recycling_query .= " ORDER BY rl.recycled_at ASC";
        break;
    case 'weight_asc':
        $recycling_query .= " ORDER BY rl.weight ASC";
        break;
    case 'weight_desc':
        $recycling_query .= " ORDER BY rl.weight DESC";
        break;
    case 'points_asc':
        $recycling_query .= " ORDER BY rl.points_earned ASC";
        break;
    case 'points_desc':
        $recycling_query .= " ORDER BY rl.points_earned DESC";
        break;
    default:
        $recycling_query .= " ORDER BY rl.recycled_at DESC";
        break;
}

$recycling_stmt = $pdo->prepare($recycling_query);
$recycling_stmt->execute($recycling_params);
$recycling_logs = $recycling_stmt->fetchAll(PDO::FETCH_ASSOC);

$reward_query = "
    SELECT 
        rc.*, 
        u.name, 
        u.email,
        u.barangay
    FROM reward_claims rc 
    JOIN users u ON rc.user_id = u.id 
    WHERE 1=1
";

$reward_params = [];

if (!empty($search)) {
    $reward_query .= " AND (u.name LIKE ? OR u.email LIKE ? OR rc.reward_title LIKE ?)";
    $reward_params[] = $search_param;
    $reward_params[] = $search_param;
    $reward_params[] = $search_param;
}

$reward_status_filter = $_GET['reward_status'] ?? '';
if (!empty($reward_status_filter) && in_array($reward_status_filter, ['pending', 'processed', 'completed'])) {
    $reward_query .= " AND rc.status = ?";
    $reward_params[] = $reward_status_filter;
}

$reward_query .= " ORDER BY rc.claimed_at DESC";

$reward_stmt = $pdo->prepare($reward_query);
$reward_stmt->execute($reward_params);
$reward_claims = $reward_stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT category_name FROM recycling_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_COLUMN);

$total_submissions = count($recycling_logs);
$pending_count = count(array_filter($recycling_logs, fn($log) => $log['status'] === 'pending'));
$approved_count = count(array_filter($recycling_logs, fn($log) => $log['status'] === 'approved'));
$rejected_count = count(array_filter($recycling_logs, fn($log) => $log['status'] === 'rejected'));
$total_weight = array_sum(array_column($recycling_logs, 'weight'));
$total_revenue = array_sum(array_column($recycling_logs, 'revenue_generated'));
$total_points = array_sum(array_column($recycling_logs, 'points_earned'));

$total_rewards = count($reward_claims);
$pending_rewards = count(array_filter($reward_claims, fn($claim) => $claim['status'] === 'pending'));
$processed_rewards = count(array_filter($reward_claims, fn($claim) => $claim['status'] === 'processed'));
$completed_rewards = count(array_filter($reward_claims, fn($claim) => $claim['status'] === 'completed'));
$total_points_used = array_sum(array_column($reward_claims, 'points_used'));

$recent_activities = $pdo->query("
    SELECT 
        rl.*, 
        u.name AS username, 
        rc.category_name,
        rc.icon
    FROM recycling_logs rl 
    JOIN users u ON rl.user_id = u.id 
    JOIN recycling_categories rc ON rl.category_id = rc.id
    ORDER BY rl.recycled_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycling Monitoring - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="admin.php">♻ EcoMina Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="admin.php">← Back to Admin</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Success/Error Messages -->
    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ <?= $successMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?= $errorMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">🗑️ Recycling Monitoring</h2>
        <div class="text-muted">
            <small>Last updated: <?= date('M j, g:i A') ?></small>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-primary mb-1">📥</h5>
                    <h4 class="mb-0"><?= $total_submissions ?></h4>
                    <small class="text-muted">Total Submissions</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-warning mb-1">⏳</h5>
                    <h4 class="mb-0"><?= $pending_count ?></h4>
                    <small class="text-muted">Pending Review</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">♻</h5>
                    <h4 class="mb-0"><?= number_format($total_weight, 0) ?>kg</h4>
                    <small class="text-muted">Total Recycled</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-info mb-1">💰</h5>
                    <h4 class="mb-0">₱<?= number_format($total_revenue, 0) ?></h4>
                    <small class="text-muted">Total Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-danger mb-1">🎁</h5>
                    <h4 class="mb-0"><?= $total_rewards ?></h4>
                    <small class="text-muted">Reward Claims</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-warning mb-1">⭐</h5>
                    <h4 class="mb-0"><?= number_format($total_points) ?></h4>
                    <small class="text-muted">Points Awarded</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6 class="card-title mb-3">🔍 Filter & Search Submissions</h6>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="User, email, material..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $category_filter === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Sort</label>
                    <select name="sort" class="form-control">
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recent</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="weight_desc" <?= $sort === 'weight_desc' ? 'selected' : '' ?>>Heaviest</option>
                        <option value="weight_asc" <?= $sort === 'weight_asc' ? 'selected' : '' ?>>Lightest</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <a href="monitoring_waste.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-lg border-0 rounded-3 mb-5">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">♻ Recycling Submissions</h4>
            <span class="badge bg-light text-dark">
                <?= $total_submissions ?> total • <?= $pending_count ?> pending
            </span>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead class="table-success">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Material</th>
                        <th>Weight</th>
                        <th>Points</th>
                        <th>Revenue</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recycling_logs as $log): ?>
                        <tr class="<?= $log['status'] === 'pending' ? 'table-warning' : ($log['status'] === 'rejected' ? 'table-danger' : '') ?>">
                            <td>
                                <small class="text-muted">#<?= $log['id'] ?></small>
                            </td>
                            <td>
                                <div>
                                    <strong><?= $log['username'] ?? "Guest" ?></strong>
                                    <?php if ($log['barangay']): ?>
                                        <br>
                                        <small class="text-muted"><?= $log['barangay'] ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?= $log['email'] ?? "N/A" ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span style="font-size: 1.3em; color: <?= $log['color'] ?>"><?= $log['icon'] ?></span>
                                    <div class="ms-2">
                                        <strong><?= $log['category_name'] ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= $log['weight'] ?> kg</strong>
                            </td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-star-fill"></i> <?= $log['points_earned'] ?>
                                </span>
                            </td>
                            <td class="text-success">
                                <strong>₱<?= number_format($log['revenue_generated'], 2) ?></strong>
                            </td>
                            <td>
                                <small><?= date('M j, Y', strtotime($log['recycled_at'])) ?></small>
                                <br>
                                <small class="text-muted"><?= date('g:i A', strtotime($log['recycled_at'])) ?></small>
                            </td>
                            <td>
                                <?php 
                                $status_config = [
                                    'pending' => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock'],
                                    'approved' => ['class' => 'bg-success', 'icon' => 'bi-check-circle'],
                                    'rejected' => ['class' => 'bg-danger', 'icon' => 'bi-x-circle']
                                ];
                                $config = $status_config[$log['status']] ?? ['class' => 'bg-secondary', 'icon' => 'bi-question'];
                                ?>
                                <span class="badge <?= $config['class'] ?>">
                                    <i class="bi <?= $config['icon'] ?>"></i> <?= ucfirst($log['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($log['status'] === 'pending'): ?>
                                        <a href="?approve=<?= $log['id'] ?>" class="btn btn-success" 
                                           title="Approve Submission"
                                           onclick="return confirm('Approve this recycling submission? Points will be awarded to the user.')">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                        <a href="?reject=<?= $log['id'] ?>" class="btn btn-danger" 
                                           title="Reject Submission"
                                           onclick="return confirm('Reject this recycling submission? This action cannot be undone.')">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php elseif ($log['status'] === 'approved'): ?>
                                        <span class="btn btn-outline-success" disabled title="Approved">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="btn btn-outline-danger" disabled title="Rejected">
                                            <i class="bi bi-x-lg"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#submissionDetailsModal" 
                                            data-submission='<?= htmlspecialchars(json_encode($log)) ?>'
                                            title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <a href="?delete=<?= $log['id'] ?>" class="btn btn-outline-danger"
                                       onclick="return confirm('Permanently delete this submission? This will also remove verification records. This action cannot be undone.')"
                                       title="Delete Submission">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recycling_logs)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                No recycling submissions found.
                                <?php if ($search || $status_filter || $category_filter): ?>
                                    <br>
                                    <a href="monitoring_waste.php" class="btn btn-success mt-2">Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reward Claims Section -->
    <div id="rewards" class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">🎁 Reward Claims Management</h4>
            <span class="badge bg-light text-dark">
                <?= $total_rewards ?> total • <?= $pending_rewards ?> pending
            </span>
        </div>
        <div class="card-body">
            <!-- Reward Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <form method="GET" class="d-flex">
                        <select name="reward_status" class="form-control me-2" onchange="this.form.submit()">
                            <option value="">All Reward Status</option>
                            <option value="pending" <?= $reward_status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processed" <?= $reward_status_filter === 'processed' ? 'selected' : '' ?>>Processed</option>
                            <option value="completed" <?= $reward_status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Reward</th>
                            <th>Points Used</th>
                            <th>Claimed Date</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reward_claims as $claim): ?>
                            <tr class="<?= $claim['status'] === 'pending' ? 'table-warning' : '' ?>">
                                <td>
                                    <small class="text-muted">#<?= $claim['id'] ?></small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= $claim['name'] ?></strong>
                                        <?php if ($claim['barangay']): ?>
                                            <br>
                                            <small class="text-muted"><?= $claim['barangay'] ?></small>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= $claim['email'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-success"><?= $claim['reward_title'] ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-star-fill"></i> <?= $claim['points_used'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= date('M j, Y', strtotime($claim['claimed_at'])) ?></small>
                                    <br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($claim['claimed_at'])) ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $reward_status_config = [
                                        'pending' => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock'],
                                        'processed' => ['class' => 'bg-info', 'icon' => 'bi-gear'],
                                        'completed' => ['class' => 'bg-success', 'icon' => 'bi-check-circle']
                                    ];
                                    $reward_config = $reward_status_config[$claim['status']] ?? ['class' => 'bg-secondary', 'icon' => 'bi-question'];
                                    ?>
                                    <span class="badge <?= $reward_config['class'] ?>">
                                        <i class="bi <?= $reward_config['icon'] ?>"></i> <?= ucfirst($claim['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($claim['status'] === 'pending'): ?>
                                            <a href="?process_reward=<?= $claim['id'] ?>" class="btn btn-info" 
                                               title="Mark as Processed"
                                               onclick="return confirm('Mark this reward as processed?')">
                                                <i class="bi bi-gear"></i> Process
                                            </a>
                                        <?php elseif ($claim['status'] === 'processed'): ?>
                                            <a href="?complete_reward=<?= $claim['id'] ?>" class="btn btn-success" 
                                               title="Mark as Completed"
                                               onclick="return confirm('Mark this reward as completed?')">
                                                <i class="bi bi-check-lg"></i> Complete
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-outline-success" disabled title="Completed">
                                                <i class="bi bi-check-lg"></i>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#rewardDetailsModal" 
                                                data-reward='<?= htmlspecialchars(json_encode($claim)) ?>'
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reward_claims)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-gift display-4 d-block mb-2"></i>
                                    No reward claims found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h6 class="text-success">⚡ Quick Actions</h6>
                    <div class="btn-group w-100">
                        <a href="?status=pending" class="btn btn-warning">
                            <i class="bi bi-clock"></i> View Pending (<?= $pending_count ?>)
                        </a>
                        <a href="admin_dashboard.php" class="btn btn-info">
                            <i class="bi bi-graph-up"></i> View Analytics
                        </a>
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h6 class="text-primary">📊 Summary</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">Approval Rate</small>
                            <h6 class="mb-0 text-success"><?= $total_submissions > 0 ? number_format(($approved_count / $total_submissions) * 100, 1) : 0 ?>%</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Avg. Weight</small>
                            <h6 class="mb-0 text-info"><?= $total_submissions > 0 ? number_format($total_weight / $total_submissions, 1) : 0 ?>kg</h6>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Reward Rate</small>
                            <h6 class="mb-0 text-warning"><?= $total_points > 0 ? number_format(($total_points_used / $total_points) * 100, 1) : 0 ?>%</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="submissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Recycling Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="submissionDetailsContent">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rewardDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Reward Claim Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rewardDetailsContent">
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var submissionModal = document.getElementById('submissionDetailsModal');
        submissionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var submissionData = JSON.parse(button.getAttribute('data-submission'));
            
            var content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>User Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>${submissionData.username || 'Guest'}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>${submissionData.email || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Barangay:</strong></td>
                                <td>${submissionData.barangay || 'Not specified'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Submission Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Material:</strong></td>
                                <td>
                                    <span style="font-size: 1.2em; color: ${submissionData.color}">${submissionData.icon}</span>
                                    ${submissionData.category_name}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Weight:</strong></td>
                                <td><strong>${submissionData.weight} kg</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Points Earned:</strong></td>
                                <td><span class="badge bg-warning">${submissionData.points_earned}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Revenue:</strong></td>
                                <td class="text-success"><strong>₱${parseFloat(submissionData.revenue_generated).toFixed(2)}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Timeline</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Submitted:</strong></td>
                                <td>${new Date(submissionData.recycled_at).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-${submissionData.status === 'approved' ? 'success' : submissionData.status === 'rejected' ? 'danger' : 'warning'}">
                                        ${submissionData.status.charAt(0).toUpperCase() + submissionData.status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('submissionDetailsContent').innerHTML = content;
        });
        
        var rewardModal = document.getElementById('rewardDetailsModal');
        rewardModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var rewardData = JSON.parse(button.getAttribute('data-reward'));
            
            var content = `
                <div class="row">
                    <div class="col-12">
                        <h6>User Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>${rewardData.name}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>${rewardData.email}</td>
                            </tr>
                            <tr>
                                <td><strong>Barangay:</strong></td>
                                <td>${rewardData.barangay || 'Not specified'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <h6>Reward Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Reward:</strong></td>
                                <td class="text-success"><strong>${rewardData.reward_title}</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Points Used:</strong></td>
                                <td><span class="badge bg-danger">${rewardData.points_used}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Claimed:</strong></td>
                                <td>${new Date(rewardData.claimed_at).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-${rewardData.status === 'completed' ? 'success' : rewardData.status === 'processed' ? 'info' : 'warning'}">
                                        ${rewardData.status.charAt(0).toUpperCase() + rewardData.status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('rewardDetailsContent').innerHTML = content;
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>