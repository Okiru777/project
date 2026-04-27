<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

if ($_POST['action'] ?? '' === 'verify_submission') {
    $log_id = intval($_POST['log_id']);
    $status = $_POST['status'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE recycling_logs SET status = ? WHERE id = ?");
        $stmt->execute([$status, $log_id]);

        $stmt = $pdo->prepare("UPDATE recycling_verification 
                              SET status = ?, verified_at = NOW(), verified_by = ?, rejection_reason = ?
                              WHERE recycling_log_id = ?");
        $stmt->execute([$status, $admin_id, $rejection_reason, $log_id]);

        if ($status === 'approved') {
            $log_stmt = $pdo->prepare("SELECT user_id, points_earned, weight FROM recycling_logs WHERE id = ?");
            $log_stmt->execute([$log_id]);
            $log = $log_stmt->fetch();

            if ($log) {
                $stmt = $pdo->prepare("UPDATE users SET points = points + ?, total_recycled = total_recycled + ? WHERE id = ?");
                $stmt->execute([$log['points_earned'], $log['weight'], $log['user_id']]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Submission " . $status . " successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error processing verification: " . $e->getMessage();
    }
    header("Location: verify_recycling.php");
    exit();
}

$pending_stmt = $pdo->prepare("
    SELECT 
        rl.id,
        rl.user_id,
        rl.category_id,
        rl.weight,
        rl.points_earned,
        rl.revenue_generated,
        rl.proof_image,
        rl.weight_proof_image,
        rl.recycled_at,
        u.name as user_name,
        u.email as user_email,
        rc.category_name,
        rc.icon,
        rc.color
    FROM recycling_logs rl
    JOIN users u ON rl.user_id = u.id
    JOIN recycling_categories rc ON rl.category_id = rc.id
    WHERE rl.status = 'pending'
    ORDER BY rl.recycled_at ASC
");
$pending_stmt->execute();
$pending_submissions = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Recycling Submissions - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .proof-image {
            max-width: 300px;
            max-height: 250px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .proof-image:hover {
            transform: scale(1.05);
        }
        .submission-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .submission-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .image-modal img {
            max-width: 100%;
            max-height: 80vh;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .user-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin.php">EcoMina - Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="admin.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="verify_recycling.php">Verify Recycling</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_categories.php">Manage Categories</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h2 class="mb-4 text-success">🔍 Verify Recycling Submissions</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (empty($pending_submissions)): ?>
        <div class="alert alert-info text-center">
            <h4>🎉 No Pending Submissions!</h4>
            <p class="mb-0">All recycling submissions have been verified. Check back later for new submissions.</p>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>📋 Verification Guidelines:</strong>
            <ul class="mb-0">
                <li>Check if proof images clearly show the recyclable materials</li>
                <li>Verify that weight proof matches the claimed amount</li>
                <li>Reject submissions with unclear or fraudulent images</li>
                <li>Provide clear reasons for rejection</li>
            </ul>
        </div>

        <div class="row">
            <?php foreach ($pending_submissions as $submission): ?>
            <div class="col-lg-6 mb-4">
                <div class="card submission-card h-100" style="border-left-color: <?= $submission['color'] ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <span style="font-size: 1.2em;"><?= $submission['icon'] ?></span>
                            <strong><?= $submission['category_name'] ?></strong>
                        </div>
                        <span class="badge bg-warning status-badge">PENDING</span>
                    </div>
                    
                    <div class="card-body">
                        <div class="user-info mb-3">
                            <strong>👤 User:</strong> <?= htmlspecialchars($submission['user_name']) ?><br>
                            <strong>📧 Email:</strong> <?= htmlspecialchars($submission['user_email']) ?><br>
                            <strong>📅 Submitted:</strong> <?= date('M j, Y g:i A', strtotime($submission['recycled_at'])) ?>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>⚖️ Weight:</strong><br>
                                <span class="fs-5"><?= $submission['weight'] ?> kg</span>
                            </div>
                            <div class="col-6">
                                <strong>⭐ Points:</strong><br>
                                <span class="fs-5 text-success"><?= $submission['points_earned'] ?> pts</span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>💰 Revenue:</strong><br>
                                <span class="fs-5 text-primary">₱<?= number_format($submission['revenue_generated'], 2) ?></span>
                            </div>
                            <div class="col-6">
                                <strong>🆔 Log ID:</strong><br>
                                <code>#<?= $submission['id'] ?></code>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>📸 Proof Images:</strong>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <small class="text-muted d-block">Proof of Recycling</small>
                                    <img src="uploads/recycling_proofs/<?= $submission['proof_image'] ?>" 
                                         class="proof-image mt-1" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         data-image="uploads/recycling_proofs/<?= $submission['proof_image'] ?>"
                                         data-title="Proof of Recycling - <?= $submission['category_name'] ?>">
                                </div>
                            </div>
                            <?php if ($submission['weight_proof_image']): ?>
                            <div class="col-6">
                                <div class="text-center">
                                    <small class="text-muted d-block">Proof of Weight</small>
                                    <img src="uploads/recycling_proofs/<?= $submission['weight_proof_image'] ?>" 
                                         class="proof-image mt-1" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         data-image="uploads/recycling_proofs/<?= $submission['weight_proof_image'] ?>"
                                         data-title="Proof of Weight - <?= $submission['category_name'] ?>">
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-6">
                                <div class="text-center text-muted">
                                    <small class="d-block">Proof of Weight</small>
                                    <div class="proof-image mt-1 d-flex align-items-center justify-content-center bg-light">
                                        <small>No weight proof provided</small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" class="verification-form">
                            <input type="hidden" name="action" value="verify_submission">
                            <input type="hidden" name="log_id" value="<?= $submission['id'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Rejection Reason (if applicable):</strong></label>
                                <textarea name="rejection_reason" class="form-control" rows="2" 
                                          placeholder="Provide clear reason for rejection..."></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="status" value="approved" 
                                        class="btn btn-success btn-lg"
                                        onclick="return confirm('Approve this submission? Points will be awarded to the user.')">
                                    ✅ Approve & Award Points
                                </button>
                                <button type="submit" name="status" value="rejected" 
                                        class="btn btn-danger btn-lg"
                                        onclick="return confirm('Reject this submission? User will be notified.')">
                                    ❌ Reject Submission
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalTitle">Proof Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" alt="Proof image" id="modalImage" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const imageModal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        const modalTitle = document.getElementById('imageModalTitle');
        
        imageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imageSrc = button.getAttribute('data-image');
            const imageTitle = button.getAttribute('data-title');
            
            modalImage.src = imageSrc;
            modalTitle.textContent = imageTitle;
        });

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>