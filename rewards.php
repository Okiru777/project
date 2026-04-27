<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$name = $_SESSION['name'] ?? 'User';
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_points = $stmt->fetchColumn();

$rewards = [
    ["title" => "₱10 Mobile Load", "points" => 100, "network" => "All Networks"],
    ["title" => "₱20 Mobile Load", "points" => 200, "network" => "All Networks"],
    ["title" => "₱50 Mobile Load", "points" => 500, "network" => "All Networks"],
    ["title" => "₱100 Mobile Load", "points" => 1000, "network" => "All Networks"],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward System - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">EcoMina</a>
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
    <h2 class="mb-4">📱 Mobile Load Rewards</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>Welcome, <?= htmlspecialchars($name) ?>!</strong> You currently have 
        <strong class="text-success"><?= $user_points ?> points</strong>.
        <br><small>Convert your eco-points to mobile load credits!</small>
    </div>

    <div class="row g-4">
        <?php foreach ($rewards as $reward): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card reward-card shadow-sm h-100 text-center">
                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <h5 class="card-title text-success"><?= htmlspecialchars($reward["title"]) ?></h5>
                            <p class="text-muted small"><?= $reward["network"] ?></p>
                        </div>
                        <div class="mt-auto">
                            <p class="card-text">
                                <strong class="text-primary"><?= $reward["points"] ?> points</strong>
                            </p>
                            
                            <?php if ($user_points >= $reward["points"]): ?>
                                <form action="claim_reward.php" method="POST" class="mt-2">
                                    <input type="hidden" name="reward_title" value="<?= htmlspecialchars($reward["title"]) ?>">
                                    <input type="hidden" name="reward_points" value="<?= $reward["points"] ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        📱 Claim Load
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>
                                    🔒 Need <?= $reward["points"] - $user_points ?> more points
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card mt-5">
        <div class="card-body">
            <h5 class="card-title text-success">💡 How to Earn Points</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>🥤 Plastic Bottles</h6>
                        <p class="mb-1"><strong>15 points/kg</strong></p>
                        <small class="text-muted">+ ₱18 revenue share</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>🥫 Metal Cans</h6>
                        <p class="mb-1"><strong>12 points/kg</strong></p>
                        <small class="text-muted">+ ₱22 revenue share</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>📄 Paper & Cardboard</h6>
                        <p class="mb-1"><strong>8 points/kg</strong></p>
                        <small class="text-muted">+ ₱12 revenue share</small>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>🍶 Glass Containers</h6>
                        <p class="mb-1"><strong>10 points/kg</strong></p>
                        <small class="text-muted">+ ₱15 revenue share</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>📦 Mixed Materials</h6>
                        <p class="mb-1"><strong>Varies</strong></p>
                        <small class="text-muted">Check category rates</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <h6>♻ Regular Recycling</h6>
                        <p class="mb-1"><strong>Earn points daily</strong></p>
                        <small class="text-muted">Consistent recycling pays off!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Waste Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>