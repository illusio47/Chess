<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Palliative Care Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .nav-link {
            color: #333;
        }
        .nav-link:hover {
            color: #007bff;
        }
        .table .btn-xs {
            padding: 0.1rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.5;
        }
        .table td {
            vertical-align: middle;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php?module=admin&action=dashboard">Palliative Care Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    // Initialize $current_page if not set
                    $current_page = $current_page ?? '';
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=dashboard">
                           <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'patients') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=patients">
                           <i class="fas fa-procedures"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'doctors') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=doctors">
                           <i class="fas fa-user-md"></i> Doctors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'services') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=services">
                           <i class="fas fa-cogs"></i> Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'pharmacies') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=pharmacies">
                           <i class="fas fa-pills"></i> Pharmacies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'reports') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=reports">
                           <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <?php if (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] === 'super'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page === 'admin_tokens') ? 'active' : ''; ?>" 
                           href="index.php?module=admin&action=admin_tokens">
                           <i class="fas fa-key"></i> Admin Tokens
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-light" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="index.php?module=admin&action=profile">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="index.php?module=auth&action=logout">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-3">
            <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3">
            <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</body>
</html> 