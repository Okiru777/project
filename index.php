<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoMina - Waste Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">♻ EcoMina</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" aria-controls="navbarNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>

<header class="bg-light p-5 text-center">
    <div class="container">
        <h1 class="display-5 text-success">Integrated Waste Management System</h1>
        <p class="lead">Turn your recyclable waste into mobile load credits!</p>
    </div>
</header>

<section class="container my-5">
    <div class="row g-4 justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">🥤</div>
                    <h5 class="card-title">1. Submit Recyclable Waste</h5>
                    <p class="card-text">Categorize and submit your plastic, metal, or other recyclable materials to earn points.</p>
                    <a href="categorization.php" class="btn btn-success">Start Here</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">⭐</div>
                    <h5 class="card-title">2. Track Your Points</h5>
                    <p class="card-text">Monitor your earned points and revenue from recycling activities.</p>
                    <a href="points.php" class="btn btn-success">View Points</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">💰</div>
                    <h5 class="card-title">3. View Earnings</h5>
                    <p class="card-text">Check your total revenue and monetization reports.</p>
                    <a href="monetization.php" class="btn btn-success">View Earnings</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">📱</div>
                    <h5 class="card-title">4. Redeem Rewards</h5>
                    <p class="card-text">Convert your points into mobile load credits instantly.</p>
                    <a href="rewards.php" class="btn btn-success">Get Load</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">📊</div>
                    <h5 class="card-title">Analytics Dashboard</h5>
                    <p class="card-text">View community recycling statistics and trends.</p>
                    <a href="dashboard.php" class="btn btn-success">View Analytics</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card feature-card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 mb-3">📢</div>
                    <h5 class="card-title">Community Updates</h5>
                    <p class="card-text">Stay informed about recycling programs and events.</p>
                    <a href="updates.php" class="btn btn-success">View Updates</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="announcement">
                <h4 class="text-success">🚀 System Features</h4>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li>✅ <strong>Easy Waste Categorization</strong> - Simple 3-step process</li>
                            <li>✅ <strong>Instant Points System</strong> - Points per kg based on material</li>
                            <li>✅ <strong>Revenue Sharing</strong> - Earn cash from recycling</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li>✅ <strong>Mobile Load Rewards</strong> - Convert points to load</li>
                            <li>✅ <strong>Real-time Tracking</strong> - Monitor your progress</li>
                            <li>✅ <strong>Community Impact</strong> - Help the environment</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Waste Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>