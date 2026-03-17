<?php
// includes/customer_header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = currentUser();
$notifCount = unreadCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'My Account') ?> – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="customer-body">

<header class="customer-header">
  <div class="container">
    <nav class="navbar navbar-dark p-0">
      <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <i class="bi bi-scissors me-2"></i><?= SITE_NAME ?>
      </a>
      <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>/customer/orders.php" class="btn btn-outline-light btn-sm d-none d-sm-flex align-items-center gap-1">
          <i class="bi bi-bag-plus"></i> New Order
        </a>
        <div class="dropdown">
          <button class="btn btn-link text-white d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <div class="sidebar-avatar" style="width:34px;height:34px;font-size:.85rem;"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <span class="d-none d-sm-block"><?= clean($user['name']) ?></span>
            <i class="bi bi-chevron-down" style="font-size:.75rem;"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
            <li><h6 class="dropdown-header"><?= clean($user['email']) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/customer/dashboard.php"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/customer/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/customer/order_history.php"><i class="bi bi-clock-history me-2"></i>My Orders</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Sign Out</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </div>
</header>

<!-- Nav tabs -->
<div class="bg-white border-bottom shadow-sm">
  <div class="container">
    <nav class="nav nav-pills py-1 gap-1">
      <?php $p = $activePage??''; ?>
      <a href="<?= BASE_URL ?>/customer/dashboard.php" class="nav-link <?= $p==='dashboard'?'btn-fashion text-white':'' ?>"><i class="bi bi-grid me-1"></i>Dashboard</a>
      <a href="<?= BASE_URL ?>/customer/orders.php"    class="nav-link <?= $p==='order'?'btn-fashion text-white':'' ?>"><i class="bi bi-bag-plus me-1"></i>Place Order</a>
      <a href="<?= BASE_URL ?>/customer/order_history.php" class="nav-link <?= $p==='history'?'btn-fashion text-white':'' ?>"><i class="bi bi-clock-history me-1"></i>My Orders</a>
      <a href="<?= BASE_URL ?>/customer/profile.php"   class="nav-link <?= $p==='profile'?'btn-fashion text-white':'' ?>"><i class="bi bi-person me-1"></i>Profile</a>
    </nav>
  </div>
</div>

<div class="container py-4">
  <?= renderFlash() ?>
