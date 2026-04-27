<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$reward_title = $_POST['reward_title'] ?? '';
$reward_points = (int)($_POST['reward_points'] ?? 0);

$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_points = $stmt->fetchColumn();

if ($current_points >= $reward_points) {
    $new_points = $current_points - $reward_points;
    $update = $pdo->prepare("UPDATE users SET points = ? WHERE id = ?");
    $update->execute([$new_points, $user_id]);

    $log = $pdo->prepare("INSERT INTO reward_claims (user_id, reward_title, points_used, claimed_at, status) VALUES (?, ?, ?, NOW(), 'pending')");
    $log->execute([$user_id, $reward_title, $reward_points]);

    $_SESSION['success'] = "You successfully claimed '{$reward_title}'! Your load will be processed within 24 hours.";
} else {
    $_SESSION['error'] = "Not enough points to claim the reward. You need {$reward_points} points but only have {$current_points}.";
}

header("Location: rewards.php");
exit();
?>