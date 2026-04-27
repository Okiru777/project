<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? 'EcoMina User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Updates - EcoMina</title>
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
    <h2 class="mb-4">📢 Community Updates & Announcements</h2>

    <div class="announcement">
        <h5>🎉 New Reward System Launched!</h5>
        <small class="text-muted">Posted: <?= date('F j, Y') ?> - 9:00 AM</small>
        <p>We're excited to announce our new mobile load rewards system! Convert your recycling points directly to mobile credits for all networks.</p>
        <div class="mt-2">
            <span class="badge bg-success">New Feature</span>
            <span class="badge bg-info">Rewards</span>
        </div>
    </div>

    <div class="announcement">
        <h5>📈 Increased Rates for Metal Cans!</h5>
        <small class="text-muted">Posted: <?= date('F j, Y', strtotime('-3 days')) ?> - 2:30 PM</small>
        <p>Metal cans now earn <strong>₱22 per kg</strong>! Aluminum and tin cans are among our highest-paying recyclable materials.</p>
        <div class="mt-2">
            <span class="badge bg-warning text-dark">Rate Update</span>
        </div>
    </div>

    <div class="announcement">
        <h5>🏆 Top Recycler of the Month</h5>
        <small class="text-muted">Posted: <?= date('F j, Y', strtotime('-1 week')) ?> - 10:15 AM</small>
        <p>Congratulations to our top recyclers! Special bonuses awarded to users who recycled over 50kg this month.</p>
        <div class="mt-2">
            <span class="badge bg-primary">Achievement</span>
        </div>
    </div>

    <div class="announcement">
        <h5>🔧 System Maintenance</h5>
        <small class="text-muted">Posted: <?= date('F j, Y', strtotime('-2 weeks')) ?> - 4:45 PM</small>
        <p>Monthly system maintenance scheduled for this weekend. The system will be unavailable for 2 hours on Sunday from 2:00 AM to 4:00 AM.</p>
        <div class="mt-2">
            <span class="badge bg-secondary">Maintenance</span>
        </div>
    </div>

    <div class="announcement">
        <h5>🌱 Community Clean-up Drive</h5>
        <small class="text-muted">Posted: <?= date('F j, Y', strtotime('-3 weeks')) ?> - 8:30 AM</small>
        <p>Join our community clean-up drive this Saturday! Earn double points for all waste collected during the event.</p>
        <div class="mt-2">
            <span class="badge bg-success">Event</span>
            <span class="badge bg-info">Double Points</span>
        </div>
    </div>

    <div class="announcement">
        <h5>♻ New Recycling Categories Added!</h5>
        <small class="text-muted">Posted: <?= date('F j, Y', strtotime('-5 days')) ?> - 11:00 AM</small>
        <p>We've optimized our recycling categories for better efficiency. Focus on plastic bottles (₱18/kg) and metal cans (₱22/kg) for the best returns!</p>
        <div class="mt-2">
            <span class="badge bg-success">Optimization</span>
            <span class="badge bg-primary">Categories</span>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Waste Management System</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>