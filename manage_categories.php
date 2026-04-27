<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login_admin.php");
    exit();
}

// Define restricted category names
$restricted_categories = ['E-WASTE', 'TEXTILES', 'BATTERIES'];

// Define available icons for recycling categories
$available_icons = [
    '🥤' => 'Plastic Bottle',
    '📄' => 'Paper',
    '📰' => 'Newspaper',
    '📦' => 'Cardboard',
    '🍶' => 'Glass',
    '🥫' => 'Can',
    '🔋' => 'Battery',
    '👕' => 'Clothing',
    '💻' => 'Electronics',
    '🧴' => 'Plastic Container',
    '🛢️' => 'Oil',
    '🌿' => 'Organic',
    '🔩' => 'Metal',
    '🧽' => 'Sponge',
    '🪣' => 'Bucket',
    '🧩' => 'Puzzle (Mixed Materials)',
    '♻️' => 'Recycling Symbol',
    '🌍' => 'Earth',
    '🌱' => 'Plant',
    '💧' => 'Water',
    '🔥' => 'Fire (Combustible)',
    '🧪' => 'Chemical',
    '🛍️' => 'Shopping Bag',
    '📱' => 'Phone',
    '💡' => 'Light Bulb',
    '🔌' => 'Plug',
    '🖨️' => 'Printer',
    '📺' => 'Television',
    '🧦' => 'Socks',
    '👖' => 'Jeans',
    '👔' => 'Shirt',
    '🧥' => 'Coat',
    '👜' => 'Handbag',
    '👟' => 'Shoes',
    '🏗️' => 'Galvanized Iron',
    '🪚' => 'Steel Bar',  // Saw blade representing steel/cutting
    '📏' => 'Steel Rod',  // Ruler representing straight steel bars
    '🪟' => 'Aluminum Frame',
    '🍱' => 'Tupperware',
    '🧴' => 'Squeeze Tubes',
    '🪣' => 'Pails',
    '🛁' => 'Basin/Tub',
    '💧' => 'Plastic Bottle (Water)',
    '🥤' => 'Plastic Bottle (Soda)',
    '🔧' => 'Metal Tools',
    '🛠️' => 'Construction Metal',
    '⚒️' => 'Hammer & Pick (Construction)',
    '🧱' => 'Brick/Construction Materials'
];

// Delete category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // Check if category has existing recycling logs
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM recycling_logs WHERE category_id = ?");
        $check_stmt->execute([$id]);
        $has_logs = $check_stmt->fetchColumn();
        
        if ($has_logs > 0) {
            $_SESSION['error'] = "Cannot delete category because it has existing recycling logs. Please deactivate it instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM recycling_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Category deleted successfully!";
            } else {
                $_SESSION['error'] = "Category not found or already deleted.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit();
}

if ($_POST['action'] ?? '' === 'add_category') {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $icon = trim($_POST['icon']);
    $points_per_kg = intval($_POST['points_per_kg']);
    $price_per_kg = floatval($_POST['price_per_kg']);
    $color = $_POST['color'] ?? '#28a745';
    
    // Check if category is restricted
    if (in_array(strtoupper($category_name), $restricted_categories)) {
        $_SESSION['error'] = "Cannot create category '$category_name'. This category is restricted.";
        header("Location: manage_categories.php");
        exit();
    }
    
    // Check if category already exists
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM recycling_categories WHERE category_name = ?");
    $check_stmt->execute([$category_name]);
    $category_exists = $check_stmt->fetchColumn();
    
    if ($category_exists > 0) {
        $_SESSION['error'] = "Category '$category_name' already exists in the system.";
        header("Location: manage_categories.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO recycling_categories (category_name, description, icon, points_per_kg, price_per_kg, color) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_name, $description, $icon, $points_per_kg, $price_per_kg, $color]);
        $_SESSION['success'] = "Category '$category_name' added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding category: " . $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit();
}

if ($_POST['action'] ?? '' === 'update_category') {
    $id = intval($_POST['id']);
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    $icon = trim($_POST['icon']);
    $points_per_kg = intval($_POST['points_per_kg']);
    $price_per_kg = floatval($_POST['price_per_kg']);
    $color = $_POST['color'] ?? '#28a745';
    $is_active = intval($_POST['is_active'] ?? 0);
    
    // Check if category is being renamed to a restricted name
    if (in_array(strtoupper($category_name), $restricted_categories)) {
        $_SESSION['error'] = "Cannot rename category to '$category_name'. This category is restricted.";
        header("Location: manage_categories.php");
        exit();
    }
    
    // Get the current category name to compare
    $current_stmt = $pdo->prepare("SELECT category_name FROM recycling_categories WHERE id = ?");
    $current_stmt->execute([$id]);
    $current_category = $current_stmt->fetchColumn();
    
    // Only check for duplicates if the category name is being changed
    if ($category_name !== $current_category) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM recycling_categories WHERE category_name = ? AND id != ?");
        $check_stmt->execute([$category_name, $id]);
        $category_exists = $check_stmt->fetchColumn();
        
        if ($category_exists > 0) {
            $_SESSION['error'] = "Category '$category_name' already exists in the system.";
            header("Location: manage_categories.php");
            exit();
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE recycling_categories SET category_name=?, description=?, icon=?, points_per_kg=?, price_per_kg=?, color=?, is_active=? WHERE id=?");
        $stmt->execute([$category_name, $description, $icon, $points_per_kg, $price_per_kg, $color, $is_active, $id]);
        $_SESSION['success'] = "Category '$category_name' updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating category: " . $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit();
}

// Delete restricted categories if they exist
try {
    $delete_stmt = $pdo->prepare("DELETE FROM recycling_categories WHERE category_name IN ('E-WASTE', 'TEXTILES', 'BATTERIES')");
    $delete_stmt->execute();
    if ($delete_stmt->rowCount() > 0) {
        $_SESSION['success'] = "Restricted categories removed successfully.";
    }
} catch (PDOException $e) {
    // Silently fail if there are foreign key constraints
}

$categories = $pdo->query("SELECT * FROM recycling_categories ORDER BY is_active DESC, category_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Recycling Categories - EcoMina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .icon-option {
            font-size: 1.5em;
            padding: 5px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        .icon-option:hover {
            background-color: #f0f0f0;
        }
        .icon-option.selected {
            background-color: #e9ecef;
            border: 2px solid #28a745;
        }
        .icon-preview {
            font-size: 2em;
            text-align: center;
            margin-bottom: 10px;
        }
        .icon-grid {
            max-height: 400px;
            overflow-y: auto;
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
                <li class="nav-item"><a class="nav-link" href="admin.php">Back to Dashboard</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h2 class="mb-4 text-success">🗂️ Manage Recycling Categories</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Add New Recycling Category</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3" id="addCategoryForm">
                <input type="hidden" name="action" value="add_category">
                <div class="col-md-3">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="category_name" class="form-control" placeholder="e.g., Plastic Bottles" required>
                    <small class="text-muted">Note: E-WASTE, TEXTILES, and BATTERIES categories are not allowed</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g., PET bottles, water bottles" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Icon</label>
                    <div class="input-group">
                        <input type="text" name="icon" id="iconInput" class="form-control" placeholder="🥤" required readonly>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#iconModal">Choose Icon</button>
                    </div>
                    <div class="icon-preview" id="iconPreview">🥤</div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Color</label>
                    <input type="color" name="color" class="form-control" value="#28a745">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Points per kg</label>
                    <input type="number" name="points_per_kg" class="form-control" min="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Price per kg (₱)</label>
                    <input type="number" step="0.01" name="price_per_kg" class="form-control" min="0" required>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">Add Recycling Category</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-success">
                        <tr>
                            <th>ID</th>
                            <th>Icon</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Points/kg</th>
                            <th>Price/kg</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= $category['id'] ?></td>
                            <td><span style="font-size: 1.5em;"><?= $category['icon'] ?></span></td>
                            <td><strong><?= $category['category_name'] ?></strong></td>
                            <td><small class="text-muted"><?= $category['description'] ?></small></td>
                            <td><?= $category['points_per_kg'] ?> pts</td>
                            <td>₱<?= number_format($category['price_per_kg'], 2) ?></td>
                            <td>
                                <?php if ($category['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-warning" data-bs-toggle="modal" 
                                            data-bs-target="#editCategoryModal" 
                                            data-id="<?= $category['id'] ?>"
                                            data-name="<?= htmlspecialchars($category['category_name']) ?>"
                                            data-description="<?= htmlspecialchars($category['description']) ?>"
                                            data-icon="<?= htmlspecialchars($category['icon']) ?>"
                                            data-color="<?= htmlspecialchars($category['color']) ?>"
                                            data-points="<?= $category['points_per_kg'] ?>"
                                            data-price="<?= $category['price_per_kg'] ?>"
                                            data-active="<?= $category['is_active'] ?>">
                                        Edit
                                    </button>
                                    <a href="?delete=<?= $category['id'] ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Are you sure you want to delete the category \"<?= htmlspecialchars($category['category_name']) ?>\"?\n\nNote: Categories with existing recycling logs cannot be deleted. You can deactivate them instead.')">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Icon Selection Modal -->
<div class="modal fade" id="iconModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select an Icon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="icon-grid">
                    <div class="row" id="iconGrid">
                        <?php foreach ($available_icons as $icon => $name): ?>
                            <div class="col-2 text-center mb-3">
                                <div class="icon-option" data-icon="<?= $icon ?>" data-name="<?= $name ?>">
                                    <div style="font-size: 2em;"><?= $icon ?></div>
                                    <small class="text-muted"><?= $name ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Edit Recycling Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                            <small class="text-muted">Note: E-WASTE, TEXTILES, and BATTERIES categories are not allowed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" id="editCategoryDescription" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <label class="form-label">Icon</label>
                            <div class="input-group">
                                <input type="text" name="icon" id="editCategoryIcon" class="form-control" required readonly>
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editIconModal">Choose Icon</button>
                            </div>
                            <div class="icon-preview" id="editIconPreview">🥤</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" id="editCategoryColor" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Points per kg</label>
                            <input type="number" name="points_per_kg" id="editCategoryPoints" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price per kg (₱)</label>
                            <input type="number" step="0.01" name="price_per_kg" id="editCategoryPrice" class="form-control" min="0" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="editCategoryActive" class="form-check-input" value="1">
                            <label class="form-check-label" for="editCategoryActive">Active Category (visible to users)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Icon Selection Modal -->
<div class="modal fade" id="editIconModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select an Icon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="icon-grid">
                    <div class="row" id="editIconGrid">
                        <?php foreach ($available_icons as $icon => $name): ?>
                            <div class="col-2 text-center mb-3">
                                <div class="icon-option" data-icon="<?= $icon ?>" data-name="<?= $name ?>">
                                    <div style="font-size: 2em;"><?= $icon ?></div>
                                    <small class="text-muted"><?= $name ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<footer class="bg-success text-white text-center py-3 mt-auto">
    <p class="mb-0">&copy; <?= date("Y") ?> LGU Mina – Eco Recycling Management System</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Icon selection for add form
        const iconOptions = document.querySelectorAll('#iconGrid .icon-option');
        const iconInput = document.getElementById('iconInput');
        const iconPreview = document.getElementById('iconPreview');
        
        iconOptions.forEach(option => {
            option.addEventListener('click', function() {
                const icon = this.getAttribute('data-icon');
                iconInput.value = icon;
                iconPreview.textContent = icon;
                
                // Update selected state
                iconOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('iconModal'));
                modal.hide();
            });
        });
        
        // Icon selection for edit form
        const editIconOptions = document.querySelectorAll('#editIconGrid .icon-option');
        const editIconInput = document.getElementById('editCategoryIcon');
        const editIconPreview = document.getElementById('editIconPreview');
        
        editIconOptions.forEach(option => {
            option.addEventListener('click', function() {
                const icon = this.getAttribute('data-icon');
                editIconInput.value = icon;
                editIconPreview.textContent = icon;
                
                // Update selected state
                editIconOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editIconModal'));
                modal.hide();
            });
        });
        
        // Edit modal initialization
        var editModal = document.getElementById('editCategoryModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('editCategoryId').value = button.getAttribute('data-id');
            document.getElementById('editCategoryName').value = button.getAttribute('data-name');
            document.getElementById('editCategoryDescription').value = button.getAttribute('data-description');
            document.getElementById('editCategoryIcon').value = button.getAttribute('data-icon');
            document.getElementById('editCategoryColor').value = button.getAttribute('data-color');
            document.getElementById('editCategoryPoints').value = button.getAttribute('data-points');
            document.getElementById('editCategoryPrice').value = button.getAttribute('data-price');
            document.getElementById('editCategoryActive').checked = button.getAttribute('data-active') === '1';
            
            // Update icon preview
            editIconPreview.textContent = button.getAttribute('data-icon');
            
            // Update selected state in edit icon modal
            editIconOptions.forEach(opt => {
                if (opt.getAttribute('data-icon') === button.getAttribute('data-icon')) {
                    opt.classList.add('selected');
                } else {
                    opt.classList.remove('selected');
                }
            });
        });
        
        // Reset selected icons when modals are closed
        document.getElementById('iconModal').addEventListener('hidden.bs.modal', function() {
            iconOptions.forEach(opt => opt.classList.remove('selected'));
        });
        
        document.getElementById('editIconModal').addEventListener('hidden.bs.modal', function() {
            // Keep the current selection for edit modal
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>