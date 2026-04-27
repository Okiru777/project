<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'];
$successMessage = '';

$categories_stmt = $pdo->query("SELECT * FROM recycling_categories WHERE is_active = 1 ORDER BY category_name");
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$metal_subcategories_stmt = $pdo->query("SELECT * FROM metal_subcategories WHERE is_active = 1 ORDER BY display_name");
$metal_subcategories = $metal_subcategories_stmt->fetchAll(PDO::FETCH_ASSOC);

$material_cards_data = [
    'Plastic Bottles' => ['icon' => '💧', 'points' => 30, 'price' => 3.00, 'description' => 'PET bottles, containers', 'unit' => 'kg'],
    'Metal' => ['icon' => '🥤', 'points' => 'variable', 'price' => 'variable', 'description' => 'Aluminum, cans, iron, steel', 'unit' => 'kg'],
    'Paper & Cardboard' => ['icon' => '📄', 'points' => 10, 'price' => 1.00, 'description' => 'Boxes, newspapers', 'unit' => 'kg'],
    'Glass Containers' => ['icon' => '🍶', 'points' => 10, 'price' => 1.00, 'description' => 'Bottles, jars', 'unit' => 'pcs']
];

$impact_factors = [
    'co2' => 2.5,    
    'water' => 50,   
    'energy' => 15   
];

$upload_dir = "uploads/recycling_proofs/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_id = intval($_POST['category_id'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $metal_subcategory = $_POST['metal_subcategory'] ?? null;
 
    $proof_image = null;
    $weight_proof_image = null;
    
    if ($weight > 0 && $category_id > 0) {
        $category_stmt = $pdo->prepare("SELECT * FROM recycling_categories WHERE id = ?");
        $category_stmt->execute([$category_id]);
        $category = $category_stmt->fetch();
        
        if (!$category) {
            $successMessage = "
                <div class='text-center'>
                    <h4 class='text-danger'>❌ Submission Failed</h4>
                </div>
                <hr>
                <strong>Error:</strong> Invalid category selected.
            ";
        } else {
            if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
                $proof_image = processUploadedImage($_FILES['proof_image'], $upload_dir, 'recycling_proof_');
            } else {
                $successMessage = "
                    <div class='text-center'>
                        <h4 class='text-danger'>❌ Submission Failed</h4>
                    </div>
                    <hr>
                    <strong>Error:</strong> Please provide proof of recycling image.
                ";
            }

            if (isset($_FILES['weight_proof_image']) && $_FILES['weight_proof_image']['error'] === UPLOAD_ERR_OK) {
                $weight_proof_image = processUploadedImage($_FILES['weight_proof_image'], $upload_dir, 'weight_proof_');
            }
            
            if ($proof_image && empty($successMessage)) {
                $points = 0;
                $revenue = 0;
                $metal_display_name = '';
                
                if ($category['category_name'] === 'Metal') {
                    if ($metal_subcategory) {
                        $metal_stmt = $pdo->prepare("SELECT * FROM metal_subcategories WHERE name = ?");
                        $metal_stmt->execute([$metal_subcategory]);
                        $metal_type = $metal_stmt->fetch();
                        
                        if ($metal_type) {
                            $points = intval($weight * $metal_type['points_per_kg']);
                            $revenue = $weight * $metal_type['price_per_kg'];
                            $metal_display_name = $metal_type['display_name'];
                        } else {
                            $successMessage = "
                                <div class='text-center'>
                                    <h4 class='text-danger'>❌ Submission Failed</h4>
                                </div>
                                <hr>
                                <strong>Error:</strong> Invalid metal type selected.
                            ";
                        }
                    } else {
                        $successMessage = "
                            <div class='text-center'>
                                <h4 class='text-danger'>❌ Submission Failed</h4>
                            </div>
                            <hr>
                            <strong>Error:</strong> Please select metal type.
                        ";
                    }
                } elseif ($category['category_name'] === 'Glass Containers') {
                    // Glass is per piece
                    $points = intval($weight * 10); // 10 points per piece
                    $revenue = $weight * 1.00; // ₱1.00 per piece
                } else {
                    // Standard calculation for other categories
                    $points = intval($weight * $category['points_per_kg']);
                    $revenue = $weight * $category['price_per_kg'];
                }
                
                // Only proceed if no errors so far
                if (empty($successMessage) && $points > 0) {
                    $co2_saved = $weight * $impact_factors['co2'];
                    $water_saved = $weight * $impact_factors['water'];
                    $energy_saved = $weight * $impact_factors['energy'];

                    $pdo->beginTransaction();
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO recycling_logs 
                            (user_id, category_id, metal_subcategory, weight, points_earned, revenue_generated, proof_image, weight_proof_image, status, recycled_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$user_id, $category_id, $metal_subcategory, $weight, $points, $revenue, $proof_image, $weight_proof_image]);
                        
                        $recycling_log_id = $pdo->lastInsertId();

                        // Insert into verification queue
                        $stmt = $pdo->prepare("INSERT INTO recycling_verification 
                            (recycling_log_id, user_id, proof_image, weight_proof_image, submitted_at, status) 
                            VALUES (?, ?, ?, ?, NOW(), 'pending')");
                        $stmt->execute([$recycling_log_id, $user_id, $proof_image, $weight_proof_image]);
                        
                        $pdo->commit();
                        
                        $successMessage = "
                            <div class='text-center'>
                                <h4 class='text-success'>✅ Waste Categorization & Verification Submitted Successfully!</h4>
                                <p class='mb-0'><strong>Your recycling submission has been received and is now in the verification queue.</strong></p>
                            </div>
                            <hr>
                            <div class='row'>
                                <div class='col-md-6'>
                                    <strong>📋 Submission Details:</strong><br>
                                    • <strong>Material:</strong> {$category['icon']} {$category['category_name']}<br>
                                    " . ($metal_display_name ? "• <strong>Metal Type:</strong> {$metal_display_name}<br>" : "") . "
                                    • <strong>" . ($category['category_name'] === 'Glass Containers' ? 'Quantity' : 'Weight') . ":</strong> {$weight} " . ($category['category_name'] === 'Glass Containers' ? 'pieces' : 'kg') . "<br>
                                    • <strong>Category:</strong> {$category['description']}
                                </div>
                                <div class='col-md-6'>
                                    <strong>💰 Potential Rewards:</strong><br>
                                    • <strong>Points:</strong> <span class='text-success'>{$points} points</span><br>
                                    • <strong>Revenue:</strong> <span class='text-primary'>₱" . number_format($revenue, 2) . "</span><br>
                                    • <strong>Status:</strong> <span class='badge bg-warning'>Pending Verification</span>
                                </div>
                            </div>
                            <hr>
                            <div class='alert alert-info mt-3'>
                                <strong>🔍 What happens next?</strong><br>
                                • Our team will review your proof images within 24 hours<br>
                                • You'll receive notification once verified<br>
                                • Points will be awarded after approval<br>
                                • Check your dashboard for status updates
                            </div>
                        ";
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        
                        // Delete uploaded files if transaction fails
                        if ($proof_image && file_exists($upload_dir . $proof_image)) {
                            unlink($upload_dir . $proof_image);
                        }
                        if ($weight_proof_image && file_exists($upload_dir . $weight_proof_image)) {
                            unlink($upload_dir . $weight_proof_image);
                        }
                        
                        $successMessage = "
                            <div class='text-center'>
                                <h4 class='text-danger'>❌ Submission Failed</h4>
                            </div>
                            <hr>
                            <strong>Error:</strong> " . $e->getMessage() . "
                        ";
                    }
                }
            } else {
                if (empty($successMessage)) {
                    $successMessage = "
                        <div class='text-center'>
                            <h4 class='text-danger'>❌ Submission Failed</h4>
                        </div>
                        <hr>
                        <strong>Error:</strong> Invalid proof image. Please try again.
                    ";
                }
            }
        }
    } else {
        $successMessage = "
            <div class='text-center'>
                <h4 class='text-danger'>❌ Submission Failed</h4>
            </div>
            <hr>
            <strong>Error:</strong> Please fill all required fields and provide proof images.
        ";
    }
}

function processUploadedImage($file, $upload_dir, $prefix) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycling Submission - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .guide-item {
            border-left: 4px solid;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .guide-icon {
            font-size: 2em;
            margin-right: 10px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            display: none;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 5px;
        }
        .upload-area {
            border: 2px dashed #28a745;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background-color: #f8fff9;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            background-color: #e8f5e8;
            border-color: #218838;
        }
        .upload-area.dragover {
            background-color: #d4edda;
            border-color: #155724;
        }
        .file-input {
            display: none;
        }
        .requirement-badge {
            font-size: 0.7em;
            margin-left: 5px;
        }
        .success-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .material-card {
            transition: transform 0.2s;
        }
        .material-card:hover {
            transform: translateY(-5px);
        }
        .text-brown {
            color: #8B4513 !important;
        }
        .metal-type-select {
            display: none;
        }
        .metal-badge {
            font-size: 0.65em;
            margin: 1px;
        }
        .category-option {
            padding: 10px;
            margin: 2px 0;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">♻ EcoMina</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="recycle.php">Recycle</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <!-- Material Cards Section -->
    <div class="guide-section mb-5">
        <h3 class="text-success mb-4">♻ Recyclable Materials</h3>
        <p class="text-muted mb-4">Choose from our accepted recyclable materials. Each material earns different points based on its recycling value and environmental impact.</p>
        
        <div class="row g-4">
            <div class="col-md-3 col-6">
                <div class="card material-card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi bi-droplet text-primary" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">Plastic Bottles</h6>
                        <p class="small text-muted mb-2">PET bottles, containers</p>
                        <div class="badge bg-success">30 pts/kg</div>
                        <div class="badge bg-primary ms-1">₱3/kg</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card material-card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi bi-cup text-warning" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">Metal</h6>
                        <p class="small text-muted mb-2">
                            <span class="badge bg-success metal-badge">Aluminum: 120 pts/kg</span>
                            <span class="badge bg-primary metal-badge">₱12/kg</span>
                            <span class="badge bg-success metal-badge">Cans: 40 pts/kg</span>
                            <span class="badge bg-primary metal-badge">₱4/kg</span>
                            <span class="badge bg-success metal-badge">Iron: 30 pts/kg</span>
                            <span class="badge bg-primary metal-badge">₱3/kg</span>
                            <span class="badge bg-success metal-badge">Steel: 100 pts/kg</span>
                            <span class="badge bg-primary metal-badge">₱10/kg</span>
                        </p>
                        <div class="badge bg-warning text-dark">Select type below</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card material-card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi bi-file-text text-brown" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">Paper & Cardboard</h6>
                        <p class="small text-muted mb-2">Boxes, newspapers</p>
                        <div class="badge bg-success">10 pts/kg</div>
                        <div class="badge bg-primary ms-1">₱1/kg</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card material-card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi bi-cup-straw text-success" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">Glass Containers</h6>
                        <p class="small text-muted mb-2">Bottles, jars</p>
                        <div class="badge bg-success">10 pts/pcs</div>
                        <div class="badge bg-primary ms-1">₱1/pcs</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">♻ Submit Recyclable Materials</h4>
        </div>
        <div class="card-body">
            <?php if ($successMessage): ?>
                <div class="alert alert-<?= strpos($successMessage, 'text-danger') !== false ? 'danger' : 'success' ?>">
                    <?= $successMessage ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-4" id="recyclingForm">
                <!-- Category and Weight Inputs -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Recycling Category <span class="text-danger">*</span></label>
                    <select name="category_id" id="categorySelect" class="form-control" required>
                        <option value="">Choose what you're recycling...</option>
                        <?php foreach ($categories as $category): 
                            $card_data = $material_cards_data[$category['category_name']] ?? [];
                            $points_display = $card_data['points'] ?? $category['points_per_kg'];
                            $price_display = $card_data['price'] ?? $category['price_per_kg'];
                            $unit = $card_data['unit'] ?? 'kg';
                        ?>
                            <option value="<?= $category['id'] ?>" 
                                    data-category-name="<?= $category['category_name'] ?>"
                                    class="category-option">
                                <?= $category['icon'] ?> <?= $category['category_name'] ?> 
                                - <?= $points_display ?> pts/<?= $unit ?> • ₱<?= number_format($price_display, 2) ?>/<?= $unit ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Metal Type Selection (only shown when Metal is selected) -->
                <div class="col-md-6 metal-type-select" id="metalTypeSelect">
                    <label class="form-label fw-bold">Metal Type <span class="text-danger">*</span></label>
                    <select name="metal_subcategory" id="metalSubcategorySelect" class="form-control">
                        <option value="">Select metal type...</option>
                        <?php foreach ($metal_subcategories as $metal): ?>
                            <option value="<?= $metal['name'] ?>">
                                <?= $metal['display_name'] ?> - <?= $metal['points_per_kg'] ?> pts/kg • ₱<?= number_format($metal['price_per_kg'], 2) ?>/kg
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold" id="weightLabel">Weight (kilograms) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.1" name="weight" id="weightInput" class="form-control" 
                           placeholder="Enter weight in kg" required>
                    <small class="text-muted" id="weightHelp">Minimum: 0.1 kg</small>
                </div>

                <!-- Proof of Recycling Image -->
                <div class="col-12">
                    <label class="form-label fw-bold">
                        📸 Proof of Recycling 
                        <span class="text-danger">*</span>
                        <span class="badge bg-danger requirement-badge">REQUIRED</span>
                    </label>
                    <div class="upload-area" onclick="document.getElementById('proofImage').click()">
                        <div class="text-success mb-2">
                            <i class="fas fa-camera fa-2x"></i>
                        </div>
                        <h6 class="text-success">Upload Proof of Recycling</h6>
                        <p class="text-muted small mb-2">
                            Take a clear photo showing the recyclable materials you're submitting
                        </p>
                        <small class="text-muted">
                            📷 Accepted: JPG, PNG, GIF, WEBP (Max 5MB)
                        </small>
                        <input type="file" name="proof_image" id="proofImage" class="file-input" 
                               accept="image/*" capture="environment" required>
                    </div>
                    <div id="proofPreview" class="image-preview">
                        <img src="" alt="Proof preview">
                    </div>
                </div>

                <!-- Proof of Weight Image -->
                <div class="col-12">
                    <label class="form-label fw-bold">
                        ⚖️ Proof of Weight 
                        <span class="badge bg-warning text-dark requirement-badge">RECOMMENDED</span>
                    </label>
                    <div class="upload-area" onclick="document.getElementById('weightProofImage').click()">
                        <div class="text-warning mb-2">
                            <i class="fas fa-weight-scale fa-2x"></i>
                        </div>
                        <h6 class="text-warning">Upload Proof of Weight</h6>
                        <p class="text-muted small mb-2">
                            Take a photo showing the weight on a scale (recommended for faster approval)
                        </p>
                        <small class="text-muted">
                            📷 Accepted: JPG, PNG, GIF, WEBP (Max 5MB)
                        </small>
                        <input type="file" name="weight_proof_image" id="weightProofImage" class="file-input" 
                               accept="image/*" capture="environment">
                    </div>
                    <div id="weightProofPreview" class="image-preview">
                        <img src="" alt="Weight proof preview">
                    </div>
                </div>

                <!-- Calculation Preview -->
                <div class="col-12">
                    <div class="alert alert-info" id="calculationPreview">
                        <strong>💡 How it works:</strong> Select a category and enter weight to see your potential earnings and environmental impact.
                    </div>
                </div>
                
                <!-- Submission Guidelines -->
                <div class="col-12">
                    <div class="alert alert-warning">
                        <strong>📋 Submission Guidelines:</strong>
                        <ul class="mb-0">
                            <li>Ensure photos are clear and well-lit</li>
                            <li>Proof of recycling must show the actual materials</li>
                            <li>Weight proof should clearly show the scale reading</li>
                            <li>Submissions require manual verification (24-48 hours)</li>
                            <li>Points will be awarded after approval</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="col-12">
                    <button type="submit" class="btn btn-success w-100 fw-bold py-3 fs-5">
                        📸 SUBMIT FOR CATEGORIZATION AND VERIFICATION
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Environmental Impact Cards -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-success">🌱 CO₂ Reduction</h5>
                    <p class="mb-1">Every kg recycled saves approximately 2.5kg of CO₂ emissions</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-primary">💧 Water Conservation</h5>
                    <p class="mb-1">Recycling saves up to 50 liters of water per kg of material</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="text-warning">⚡ Energy Savings</h5>
                    <p class="mb-1">Conserves 15 kWh of energy per kg compared to new production</p>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('categorySelect');
        const metalTypeSelect = document.getElementById('metalTypeSelect');
        const metalSubcategorySelect = document.getElementById('metalSubcategorySelect');
        const weightInput = document.getElementById('weightInput');
        const weightLabel = document.getElementById('weightLabel');
        const weightHelp = document.getElementById('weightHelp');
        const calculationPreview = document.getElementById('calculationPreview');
        const form = document.getElementById('recyclingForm');
        
        // Metal pricing data
        const metalPricing = {
            'aluminum': { points: 120, price: 12.00 },
            'metal_cans': { points: 40, price: 4.00 },
            'galvanized_iron': { points: 30, price: 3.00 },
            'steel_bar': { points: 100, price: 10.00 }
        };
        
        // Category pricing data
        const categoryPricing = {
            'Plastic Bottles': { points: 30, price: 3.00 },
            'Paper & Cardboard': { points: 10, price: 1.00 },
            'Glass Containers': { points: 10, price: 1.00, unit: 'pcs' }
        };
        
        // Image preview functionality
        const proofImageInput = document.getElementById('proofImage');
        const weightProofImageInput = document.getElementById('weightProofImage');
        const proofPreview = document.getElementById('proofPreview');
        const weightProofPreview = document.getElementById('weightProofPreview');
        
        // Proof of recycling image preview
        proofImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    proofPreview.style.display = 'block';
                    proofPreview.querySelector('img').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Proof of weight image preview
        weightProofImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    weightProofPreview.style.display = 'block';
                    weightProofPreview.querySelector('img').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Drag and drop functionality
        const uploadAreas = document.querySelectorAll('.upload-area');
        uploadAreas.forEach(area => {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = this.querySelector('input[type="file"]');
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        });
        
        // Show/hide metal type selection
        categorySelect.addEventListener('change', function() {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const categoryName = selectedOption.getAttribute('data-category-name');
            
            if (categoryName === 'Metal') {
                metalTypeSelect.style.display = 'block';
                metalSubcategorySelect.required = true;
            } else {
                metalTypeSelect.style.display = 'none';
                metalSubcategorySelect.required = false;
                metalSubcategorySelect.value = '';
            }
            
            // Update label and input for Glass Containers (pieces instead of kg)
            if (categoryName === 'Glass Containers') {
                weightLabel.innerHTML = 'Quantity (pieces) <span class="text-danger">*</span>';
                weightInput.placeholder = 'Enter quantity in pieces';
                weightInput.step = '1';
                weightInput.min = '1';
                weightHelp.textContent = 'Minimum: 1 piece';
            } else {
                weightLabel.innerHTML = 'Weight (kilograms) <span class="text-danger">*</span>';
                weightInput.placeholder = 'Enter weight in kg';
                weightInput.step = '0.01';
                weightInput.min = '0.1';
                weightHelp.textContent = 'Minimum: 0.1 kg';
            }
            
            updateCalculation();
        });
        
        // Update calculation when metal type changes
        metalSubcategorySelect.addEventListener('change', updateCalculation);
        weightInput.addEventListener('input', updateCalculation);
        
        function updateCalculation() {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const categoryName = selectedOption.getAttribute('data-category-name');
            const weight = parseFloat(weightInput.value) || 0;
            
            if (selectedOption.value && weight > 0) {
                let totalPoints = 0;
                let totalRevenue = 0;
                let calculationDetails = '';
                
                if (categoryName === 'Metal') {
                    const metalType = metalSubcategorySelect.value;
                    if (metalType && metalPricing[metalType]) {
                        const metalData = metalPricing[metalType];
                        totalPoints = Math.floor(weight * metalData.points);
                        totalRevenue = weight * metalData.price;
                        calculationDetails = `Metal Type: ${metalSubcategorySelect.options[metalSubcategorySelect.selectedIndex].text.split(' - ')[0]}`;
                    } else {
                        calculationDetails = 'Please select metal type';
                    }
                } else if (categoryPricing[categoryName]) {
                    const categoryData = categoryPricing[categoryName];
                    totalPoints = Math.floor(weight * categoryData.points);
                    totalRevenue = weight * categoryData.price;
                    calculationDetails = categoryName + (categoryData.unit ? ` (per ${categoryData.unit})` : '');
                }
                
                if (totalPoints > 0) {
                    const co2Saved = weight * 2.5;
                    const waterSaved = weight * 50;
                    const energySaved = weight * 15;
                    
                    calculationPreview.innerHTML = `
                        <strong>📊 Recycling Calculation Preview:</strong><br>
                        ✅ <strong>${totalPoints} points</strong> will be earned (after approval)<br>
                        💰 <strong>₱${totalRevenue.toFixed(2)}</strong> revenue generated<br>
                        🌱 Environmental impact: Save ${co2Saved.toFixed(1)}kg CO₂, ${waterSaved}L water, ${energySaved}kWh energy<br>
                        📋 ${calculationDetails}<br>
                        ⏳ <em>Requires verification before points are awarded</em>
                    `;
                } else {
                    calculationPreview.innerHTML = '<strong>💡 How it works:</strong> Select a category and enter weight to see your potential earnings and environmental impact.';
                }
            } else {
                calculationPreview.innerHTML = '<strong>💡 How it works:</strong> Select a category and enter weight to see your potential earnings and environmental impact.';
            }
        }
        
        // Form validation
        form.addEventListener('submit', function(e) {
            const categoryName = categorySelect.options[categorySelect.selectedIndex].getAttribute('data-category-name');
            const proofImage = document.getElementById('proofImage').files[0];
            
            if (!proofImage) {
                e.preventDefault();
                alert('Please provide proof of recycling image.');
                return false;
            }
            
            // Validate file size (5MB)
            if (proofImage.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('Proof image must be less than 5MB.');
                return false;
            }
            
            const weightProofImage = document.getElementById('weightProofImage').files[0];
            if (weightProofImage && weightProofImage.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('Weight proof image must be less than 5MB.');
                return false;
            }
            
            // Validate metal type selection
            if (categoryName === 'Metal') {
                const metalType = metalSubcategorySelect.value;
                if (!metalType) {
                    e.preventDefault();
                    alert('Please select metal type.');
                    return false;
                }
            }
            
            // Validate weight/quantity
            const weight = parseFloat(weightInput.value);
            if (categoryName === 'Glass Containers') {
                if (weight < 1) {
                    e.preventDefault();
                    alert('Minimum quantity for glass containers is 1 piece.');
                    return false;
                }
            } else {
                if (weight < 0.1) {
                    e.preventDefault();
                    alert('Minimum weight is 0.1 kg.');
                    return false;
                }
            }
        });
    });
</script>

<!-- Add Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>