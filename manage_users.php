<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

require 'db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete related records from all tables first
        $tables = [
            'environmental_impact',
            'reward_claims', 
            'recycling_logs'
            // Add any other tables with foreign keys to users here
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?");
            $stmt->execute([$id]);
        }
        
        // Now delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "User deleted successfully!";
        header("Location: manage_users.php");
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
        header("Location: manage_users.php");
        exit();
    }
}

if (isset($_GET['make_admin'])) {
    $id = intval($_GET['make_admin']);
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success_message'] = "User promoted to administrator!";
    header("Location: manage_users.php");
    exit();
}

if (isset($_GET['remove_admin'])) {
    $id = intval($_GET['remove_admin']);
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = ? AND id != ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $_SESSION['success_message'] = "Admin privileges removed!";
    header("Location: manage_users.php");
    exit();
}

$search = $_GET['search'] ?? '';
$user_type = $_GET['user_type'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$sort = $_GET['sort'] ?? 'created_at_desc';

$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.barangay,
        u.user_type,
        u.points,
        u.is_admin,
        u.created_at,
        COUNT(rl.id) as recycling_count,
        COALESCE(SUM(rl.weight), 0) as total_recycled,
        COALESCE(SUM(rl.revenue_generated), 0) as total_earnings,
        COALESCE(SUM(rl.points_earned), 0) as total_points_earned,
        COUNT(rc.id) as reward_count,
        COALESCE(SUM(rc.points_used), 0) as total_points_used,
        MAX(rl.recycled_at) as last_activity
    FROM users u
    LEFT JOIN recycling_logs rl ON u.id = rl.user_id AND rl.status = 'approved'
    LEFT JOIN reward_claims rc ON u.id = rc.user_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.barangay LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($user_type) && in_array($user_type, ['Household', 'Business'])) {
    $query .= " AND u.user_type = ?";
    $params[] = $user_type;
}

if (!empty($barangay)) {
    $query .= " AND u.barangay = ?";
    $params[] = $barangay;
}

$query .= " GROUP BY u.id, u.name, u.email, u.barangay, u.user_type, u.points, u.is_admin, u.created_at";

switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY u.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY u.name DESC";
        break;
    case 'points_asc':
        $query .= " ORDER BY u.points ASC";
        break;
    case 'points_desc':
        $query .= " ORDER BY u.points DESC";
        break;
    case 'recycled_asc':
        $query .= " ORDER BY total_recycled ASC";
        break;
    case 'recycled_desc':
        $query .= " ORDER BY total_recycled DESC";
        break;
    case 'earnings_asc':
        $query .= " ORDER BY total_earnings ASC";
        break;
    case 'earnings_desc':
        $query .= " ORDER BY total_earnings DESC";
        break;
    case 'created_at_asc':
        $query .= " ORDER BY u.created_at ASC";
        break;
    default:
        $query .= " ORDER BY u.created_at DESC";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$barangays = $pdo->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);

$total_points = array_sum(array_column($users, 'points'));
$total_recycled = array_sum(array_column($users, 'total_recycled'));
$total_earnings = array_sum(array_column($users, 'total_earnings'));
$active_users = count(array_filter($users, function($user) {
    return $user['recycling_count'] > 0;
}));
$household_count = count(array_filter($users, fn($u) => $u['user_type'] === 'Household'));
$business_count = count(array_filter($users, fn($u) => $u['user_type'] === 'Business'));
$admin_count = count(array_filter($users, fn($u) => $u['is_admin'] == 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                <li class="nav-item"><a class="nav-link" href="admin.php">← Back to Admin</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">👥 User Management</h2>
        <div class="text-muted">
            Total: <strong><?= count($users) ?></strong> users
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">👥</h5>
                    <h4 class="mb-0"><?= count($users) ?></h4>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-primary mb-1">♻</h5>
                    <h4 class="mb-0"><?= $active_users ?></h4>
                    <small class="text-muted">Active Users</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-warning mb-1">🏠</h5>
                    <h4 class="mb-0"><?= $household_count ?></h4>
                    <small class="text-muted">Households</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-info mb-1">🏢</h5>
                    <h4 class="mb-0"><?= $business_count ?></h4>
                    <small class="text-muted">Businesses</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-danger mb-1">⭐</h5>
                    <h4 class="mb-0"><?= number_format($total_points) ?></h4>
                    <small class="text-muted">Total Points</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <h5 class="text-success mb-1">💰</h5>
                    <h4 class="mb-0">₱<?= number_format($total_earnings, 0) ?></h4>
                    <small class="text-muted">Total Earnings</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, barangay..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">User Type</label>
                    <select name="user_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Household" <?= $user_type === 'Household' ? 'selected' : '' ?>>Household</option>
                        <option value="Business" <?= $user_type === 'Business' ? 'selected' : '' ?>>Business</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Barangay</label>
                    <select name="barangay" class="form-control">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $brgy): ?>
                            <option value="<?= htmlspecialchars($brgy) ?>" <?= $barangay === $brgy ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brgy) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="created_at_desc" <?= $sort === 'created_at_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="created_at_asc" <?= $sort === 'created_at_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                        <option value="points_desc" <?= $sort === 'points_desc' ? 'selected' : '' ?>>Most Points</option>
                        <option value="points_asc" <?= $sort === 'points_asc' ? 'selected' : '' ?>>Fewest Points</option>
                        <option value="recycled_desc" <?= $sort === 'recycled_desc' ? 'selected' : '' ?>>Most Recycled</option>
                        <option value="earnings_desc" <?= $sort === 'earnings_desc' ? 'selected' : '' ?>>Most Earnings</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-success">Apply Filters</button>
                        <a href="manage_users.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-success">
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Recycling Stats</th>
                            <th>Rewards</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-placeholder bg-<?= $user['user_type'] == 'Business' ? 'primary' : 'success' ?> rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                            <span class="text-white fw-bold">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                                            <?php if ($user['is_admin']): ?>
                                                <br><small class="text-danger">Administrator</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($user['email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($user['barangay']): ?>
                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($user['barangay']) ?></span>
                                    <?php else: ?>
                                        <small class="text-muted">Not set</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['user_type'] == 'Business' ? 'primary' : 'success' ?>">
                                        <?= $user['user_type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <strong class="text-warning"><?= $user['points'] ?></strong>
                                        <br>
                                        <small class="text-muted">current</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div class="d-flex justify-content-between">
                                            <span>Recycled:</span>
                                            <strong><?= number_format($user['total_recycled'], 1) ?> kg</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Earnings:</span>
                                            <strong class="text-success">₱<?= number_format($user['total_earnings'], 2) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Activities:</span>
                                            <span class="badge bg-info"><?= $user['recycling_count'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <strong class="text-danger"><?= $user['reward_count'] ?></strong>
                                        <br>
                                        <small class="text-muted">claims</small>
                                        <?php if ($user['total_points_used'] > 0): ?>
                                            <br>
                                            <small class="text-muted"><?= $user['total_points_used'] ?> pts used</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                    <?php if ($user['last_activity']): ?>
                                        <br>
                                        <small class="text-muted">Last active: <?= date('M j', strtotime($user['last_activity'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($user['recycling_count'] > 0): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm">
                                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#userDetailsModal" 
                                                data-user='<?= htmlspecialchars(json_encode($user)) ?>'>
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($user['is_admin']): ?>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?remove_admin=<?= $user['id'] ?>" class="btn btn-outline-warning btn-sm" 
                                                   onclick="return confirm('Remove admin privileges from <?= htmlspecialchars($user['name']) ?>?')">
                                                    <i class="bi bi-person-dash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm" disabled title="Current User">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="?make_admin=<?= $user['id'] ?>" class="btn btn-outline-success btn-sm" 
                                               onclick="return confirm('Make <?= htmlspecialchars($user['name']) ?> an administrator?')">
                                                <i class="bi bi-person-plus"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$user['is_admin'] || $user['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete=<?= $user['id'] ?>" class="btn btn-outline-danger btn-sm" 
                                               onclick="return confirm('WARNING: This will permanently delete user <?= htmlspecialchars($user['name']) ?> and ALL their recycling history, reward claims, and environmental impact data. This action cannot be undone!\n\nAre you sure you want to proceed?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-people display-4 d-block mb-2"></i>
                                    No users found matching your criteria.
                                    <?php if ($search || $user_type || $barangay): ?>
                                        <br>
                                        <a href="manage_users.php" class="btn btn-success mt-2">Clear Filters</a>
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
        <div class="card-body text-center">
            <h6 class="card-title">📊 Export User Data</h6>
            <div class="btn-group">
                <a href="export_users.php?type=csv&<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export as CSV
                </a>
                <a href="export_users.php?type=pdf&<?= http_build_query($_GET) ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> Export as PDF
                </a>
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var userDetailsModal = document.getElementById('userDetailsModal');
        userDetailsModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var userData = JSON.parse(button.getAttribute('data-user'));
            
            var content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>${userData.name}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>${userData.email}</td>
                            </tr>
                            <tr>
                                <td><strong>Barangay:</strong></td>
                                <td>${userData.barangay || 'Not specified'}</td>
                            </tr>
                            <tr>
                                <td><strong>User Type:</strong></td>
                                <td><span class="badge bg-${userData.user_type === 'Business' ? 'primary' : 'success'}">${userData.user_type}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Joined:</strong></td>
                                <td>${new Date(userData.created_at).toLocaleDateString()}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Recycling Statistics</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Current Points:</strong></td>
                                <td><span class="badge bg-warning">${userData.points}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Total Recycled:</strong></td>
                                <td>${parseFloat(userData.total_recycled).toFixed(1)} kg</td>
                            </tr>
                            <tr>
                                <td><strong>Total Earnings:</strong></td>
                                <td class="text-success">₱${parseFloat(userData.total_earnings).toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td><strong>Recycling Activities:</strong></td>
                                <td><span class="badge bg-info">${userData.recycling_count}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Reward Claims:</strong></td>
                                <td><span class="badge bg-danger">${userData.reward_count}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Performance Summary</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: ${userData.recycling_count > 0 ? '100' : '0'}%">
                                ${userData.recycling_count} Recycling Activities
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Avg. Earnings: ₱${userData.recycling_count > 0 ? (userData.total_earnings / userData.recycling_count).toFixed(2) : '0.00'}/activity</span>
                            <span>Avg. Weight: ${userData.recycling_count > 0 ? (userData.total_recycled / userData.recycling_count).toFixed(1) : '0.0'} kg/activity</span>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('userDetailsContent').innerHTML = content;
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>