<?php
// includes/header.php – Admin Top Header
// Requires: $pageTitle, $breadcrumb (optional)
$user = currentUser();
$notifCount = unreadCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
  <header class="top-header">
    <div class="d-flex align-items-center gap-3">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>
      <div class="page-title-area">
        <h4><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h4>
        <?php if (!empty($breadcrumb)): ?>
          <nav><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
            <?php foreach ($breadcrumb as $label => $url): ?>
              <?php if ($url): ?>
                <li class="breadcrumb-item"><a href="<?= $url ?>"><?= htmlspecialchars($label) ?></a></li>
              <?php else: ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($label) ?></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol></nav>
        <?php endif; ?>
      </div>
    </div>
    <div class="header-actions">
      <button class="btn-notif" title="Notifications" onclick="location.href='#'">
        <i class="bi bi-bell-fill"></i>
        <?php if ($notifCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
      <div class="dropdown">
        <button class="btn d-flex align-items-center gap-2 bg-transparent border-0 p-0" data-bs-toggle="dropdown">
          <div class="sidebar-avatar" style="width:36px;height:36px;font-size:.9rem;">
            <?= strtoupper(substr($user['name'],0,1)) ?>
          </div>
          <span class="d-none d-sm-block fw-500 text-dark" style="font-size:.875rem;"><?= clean($user['name']) ?></span>
          <i class="bi bi-chevron-down text-muted" style="font-size:.75rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
          <li><h6 class="dropdown-header"><?= clean($user['email']) ?></h6></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/users.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Sign Out</a></li>
        </ul>
      </div>
    </div>
  </header>
  <div class="content-wrapper">
    <?= renderFlash() ?>
