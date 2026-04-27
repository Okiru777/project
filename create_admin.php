<?php
session_start();
require 'db.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE is_admin = 1 LIMIT 1");
$stmt->execute();
$existing_admin = $stmt->fetch();

if ($existing_admin) {
    echo "<div class='container mt-5'>
            <div class='alert alert-success text-center'>
                <h4>✅ Admin account already exists!</h4>
                <p>Admin Email: <strong>" . htmlspecialchars($existing_admin['email']) . "</strong></p>
                <p>You can <a href='login_admin.php' class='alert-link'>login as Admin</a> using the existing credentials.</p>
            </div>
          </div>";
    exit();
}

$admin_name = "System Administrator";
$admin_email = "admin@ecomina.ph";
$admin_password = "admin123"; 

$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, points, is_admin, user_type) VALUES (?, ?, ?, 0, 1, 'Business')");
    $stmt->execute([$admin_name, $admin_email, $hashed_password]);
    
    $success = true;
} catch (PDOException $e) {
    $success = false;
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h3 class="text-success mb-4">🔧 Create Admin Account</h3>
                    
                    <?php if (isset($success) && $success): ?>
                        <div class="alert alert-success">
                            <h4>🎉 Admin account created successfully!</h4>
                            <div class="mt-3">
                                <p><strong>Email:</strong> <?= $admin_email ?></p>
                                <p><strong>Password:</strong> <?= $admin_password ?></p>
                                <div class="alert alert-warning mt-3">
                                    <strong>⚠️ Important:</strong> Change the default password after first login!
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="login_admin.php" class="btn btn-success btn-lg">Login as Admin</a>
                            </div>
                        </div>
                    <?php elseif (isset($success) && !$success): ?>
                        <div class="alert alert-danger">
                            <h4>❌ Error creating admin account!</h4>
                            <p><?= htmlspecialchars($error) ?></p>
                            <a href="homepage.php" class="btn btn-secondary">Return to Homepage</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!isset($success)): ?>
                        <div class="alert alert-info">
                            <p>This page creates the initial administrator account for the EcoMina system.</p>
                            <p>Click the button below to create the default admin account.</p>
                            
                            <form method="POST" action="" class="mt-3">
                                <button type="submit" class="btn btn-success btn-lg" name="create_admin">
                                    Create Default Admin Account
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="homepage.php" class="text-muted">← Back to Homepage</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>