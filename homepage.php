<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoMina - Integrated Recycling Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="homepage.php">
            <i class="bi bi-recycle me-2"></i>EcoMina
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="loginDropdown">
                        <li><a class="dropdown-item" href="login_admin.php">
                            <i class="bi bi-shield-lock me-2"></i>Login as Admin
                        </a></li>
                        <li><a class="dropdown-item" href="login.php">
                            <i class="bi bi-person me-2"></i>Login as User
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-section bg-success text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    <i class="bi bi-recycle me-3"></i>Welcome to EcoMina
                </h1>
                <p class="lead mb-4">
                    Transform your recyclable waste into rewards while contributing to a cleaner, greener Mina community. 
                    Join our sustainable revolution today!
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="register.php" class="btn btn-light btn-lg fw-bold">
                        <i class="bi bi-person-plus me-2"></i>Join Now
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </a>
                </div>
                <div class="row mt-5 text-center">
                    <div class="col-4">
                        <h3 class="fw-bold text-warning">1+</h3>
                        <small>Active Users</small>
                    </div>
                    <div class="col-4">
                        <h3 class="fw-bold text-warning">2,500+</h3>
                        <small>Kg Recycled</small>
                    </div>
                    <div class="col-4">
                        <h3 class="fw-bold text-warning">₱50K+</h3>
                        <small>Rewards Given</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="hero-image mt-4 mt-lg-0">
                    <i class="bi bi-recycle display-1 text-warning"></i>
                    <div class="mt-3">
                        <i class="bi bi-phone text-light me-3" style="font-size: 2rem;"></i>
                        <i class="bi bi-coin text-light me-3" style="font-size: 2rem;"></i>
                        <i class="bi bi-tree text-light" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="text-success mb-3">How EcoMina Works</h2>
            <p class="lead text-muted">Simple steps to turn your recyclables into rewards</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fs-4 fw-bold">1</span>
                        </div>
                        <h5 class="text-success mb-3">Collect & Sort</h5>
                        <p class="text-muted">
                            Gather your recyclable materials and sort them by type - plastic bottles, metal cans, paper, and more.
                        </p>
                        <div class="mt-3">
                            <i class="bi bi-trash text-success me-2" style="font-size: 1.5rem;"></i>
                            <i class="bi bi-arrow-right text-muted me-2"></i>
                            <i class="bi bi-folder-symlink text-success" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fs-4 fw-bold">2</span>
                        </div>
                        <h5 class="text-success mb-3">Submit & Earn</h5>
                        <p class="text-muted">
                            Submit your recyclables through our system and earn points based on weight and material type.
                        </p>
                        <div class="mt-3">
                            <i class="bi bi-upload text-success me-2" style="font-size: 1.5rem;"></i>
                            <i class="bi bi-arrow-right text-muted me-2"></i>
                            <i class="bi bi-star text-warning" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="step-number bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fs-4 fw-bold">3</span>
                        </div>
                        <h5 class="text-success mb-3">Redeem Rewards</h5>
                        <p class="text-muted">
                            Convert your earned points into mobile load credits, cash rewards, or other exciting benefits.
                        </p>
                        <div class="mt-3">
                            <i class="bi bi-gift text-success me-2" style="font-size: 1.5rem;"></i>
                            <i class="bi bi-arrow-right text-muted me-2"></i>
                            <i class="bi bi-phone text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="text-success mb-3">Accepted Recyclable Materials</h2>
            <p class="lead text-muted">We accept various types of recyclable materials with different point values</p>
        </div>
        
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
                        <p class="small text-muted mb-2">Aluminum frame = ₱12/kg = 120 pts, metal cans = ₱4/kg = 40 pts, Galvanized iron = ₱3/kg = 30 pts, Steel bar = ₱10/kg =100 pts</p>
                        <div class="badge bg-success">Metal type pts/kg</div>
                        <div class="badge bg-primary ms-1">Metal types pricing/kg</div>
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
            
            <div class="col-md-3 col-6">
                <div class="card material-card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <i class="bi bi-plus-circle text-success" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-success">More Materials</h6>
                        <p class="small text-muted mb-2">Other recyclables</p>
                        <div class="badge bg-secondary">Varies</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-success text-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="mb-3">Why Join EcoMina?</h2>
            <p class="lead">Discover the benefits of being part of our recycling community</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-currency-dollar display-4 text-warning mb-3"></i>
                    <h5>Earn Rewards</h5>
                    <p>Get paid for your recyclables and earn points redeemable for mobile load, cash, and other rewards.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-tree display-4 text-warning mb-3"></i>
                    <h5>Help Environment</h5>
                    <p>Contribute to reducing waste, conserving resources, and protecting our local environment in Mina.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-award display-4 text-warning mb-3"></i>
                    <h5>Community Impact</h5>
                    <p>Join a growing community of environmentally conscious residents making Mina cleaner and greener.</p>
                </div>
            </div>
        </div>
        
        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-phone display-4 text-warning mb-3"></i>
                    <h5>Easy Tracking</h5>
                    <p>Monitor your recycling activities, points balance, and environmental impact through our user-friendly system.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-graph-up display-4 text-warning mb-3"></i>
                    <h5>Transparent Process</h5>
                    <p>Clear pricing, instant point calculation, and real-time updates on your recycling submissions.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-4">
                    <i class="bi bi-shield-check display-4 text-warning mb-3"></i>
                    <h5>Secure & Reliable</h5>
                    <p>Your data and earnings are protected with our secure system managed by LGU Mina.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="text-success mb-3">What Our Users Say</h2>
            <p class="lead text-muted">Hear from community members who are making a difference</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Maria Santos</h6>
                                <small class="text-muted">Household User</small>
                            </div>
                        </div>
                        <p class="text-muted">
                            "EcoMina made recycling so rewarding! I've earned enough points for my family's mobile load needs while helping keep Mina clean."
                        </p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Juan's Sari-Sari Store</h6>
                                <small class="text-muted">Business User</small>
                            </div>
                        </div>
                        <p class="text-muted">
                            "As a small business, EcoMina helps us manage our waste responsibly while earning extra income. Great initiative from LGU Mina!"
                        </p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Lito Mercado</h6>
                                <small class="text-muted">Environmental Advocate</small>
                            </div>
                        </div>
                        <p class="text-muted">
                            "The environmental impact tracking feature is amazing! I can see exactly how much CO₂ I've helped reduce through my recycling efforts."
                        </p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-warning">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h3 class="text-dark mb-3">Ready to Start Recycling?</h3>
                <p class="text-dark mb-4">
                    Join hundreds of Mina residents who are already earning rewards while making our community cleaner and greener.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="register.php" class="btn btn-success btn-lg me-2">
                    <i class="bi bi-person-plus me-2"></i>Sign Up Free
                </a>
                <a href="login.php" class="btn btn-outline-dark btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
            </div>
        </div>
    </div>
</section>

<footer class="bg-dark text-white py-4 mt-auto">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5 class="text-success mb-3">
                    <i class="bi bi-recycle me-2"></i>EcoMina
                </h5>
                <p class="text-light">
                    Transforming waste into rewards for a sustainable Mina community. 
                    Join us in building a cleaner, greener future.
                </p>
                <div class="social-links">
                    <a href="#" class="text-light me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-light me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-light me-3"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-light"><i class="bi bi-telegram"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-6 mb-4 mb-lg-0">
                <h6 class="text-success mb-3">Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="homepage.php" class="text-light text-decoration-none">Home</a></li>
                    <li><a href="login.php" class="text-light text-decoration-none">User Login</a></li>
                    <li><a href="login_admin.php" class="text-light text-decoration-none">Admin Login</a></li>
                    <li><a href="register.php" class="text-light text-decoration-none">Register</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-6 mb-4 mb-lg-0">
                <h6 class="text-success mb-3">Contact Info</h6>
                <ul class="list-unstyled text-light">
                    <li class="mb-2">
                        <i class="bi bi-geo-alt me-2 text-success"></i>
                        Mina, Iloilo, Philippines
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-telephone me-2 text-success"></i>
                        (033) 123-4567
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-envelope me-2 text-success"></i>
                        info@ecomina.ph
                    </li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-success mb-3">LGU Partnership</h6>
                <p class="text-light small">
                    EcoMina is an official initiative of the Local Government Unit of Mina, 
                    promoting sustainable waste management and community engagement.
                </p>
                <img src="img/mina_logo.png.png" alt="LGU Mina" class="img-fluid mt-2">
            </div>
        </div>
        <hr class="my-4 bg-success">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-light small">
                    &copy; <?= date("Y") ?> EcoMina - LGU Mina. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-light text-decoration-none small me-3">Privacy Policy</a>
                <a href="#" class="text-light text-decoration-none small">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
    .hero-section {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .step-number {
        font-size: 1.5rem;
        transition: transform 0.3s ease;
    }
    
    .material-card:hover .step-number {
        transform: scale(1.1);
    }
    
    .material-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .material-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    
    .avatar {
        font-size: 1.2rem;
    }
    
    .text-brown {
        color: #8B4513 !important;
    }
    
    .text-purple {
        color: #6f42c1 !important;
    }
    
    .social-links a {
        font-size: 1.2rem;
        transition: color 0.3s ease;
    }
    
    .social-links a:hover {
        color: #28a745 !important;
    }
</style>
</body>
</html>