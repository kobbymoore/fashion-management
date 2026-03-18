<?php
// includes/sidebar.php – Admin Sidebar Component
// Requires: $activePage variable to be set by parent page
$user = currentUser();
$initial = strtoupper(substr($user['name'], 0, 1));
$notifCount = unreadCount();
?>
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-brand">
    <a href="<?= BASE_URL ?>/admin/dashboard.php">
      <i class="bi bi-scissors"></i>
      <div>
        <?= SITE_NAME ?>
        <small>Management System</small>
      </div>
    </a>
  </div>

  <div class="sidebar-user">
    <div class="sidebar-avatar"><?= $initial ?></div>
    <div class="sidebar-user-info">
      <strong><?= clean($user['name']) ?></strong>
      <span><?= ucfirst($user['role']) ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link <?= ($activePage??'')==='dashboard'?'active':'' ?>">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>

    <div class="nav-section-label">Customers</div>
    <a href="<?= BASE_URL ?>/admin/customers.php" class="nav-link <?= ($activePage??'')==='customers'?'active':'' ?>">
      <i class="bi bi-people-fill"></i> Customer Records
    </a>
    <a href="<?= BASE_URL ?>/admin/measurements.php" class="nav-link <?= ($activePage??'')==='measurements'?'active':'' ?>">
      <i class="bi bi-rulers"></i> Measurements
    </a>

    <div class="nav-section-label">Operations</div>
    <a href="<?= BASE_URL ?>/admin/orders.php" class="nav-link <?= ($activePage??'')==='orders'?'active':'' ?>">
      <i class="bi bi-bag-fill"></i> Orders
      <?php
      $db = getDB();
      $pendCnt = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
      if ($pendCnt > 0): ?>
        <span class="badge bg-warning text-dark"><?= $pendCnt ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/inventory.php" class="nav-link <?= ($activePage??'')==='inventory'?'active':'' ?>">
      <i class="bi bi-box-seam-fill"></i> Inventory
      <?php
      $lowStock = $db->query("SELECT COUNT(*) FROM fabrics WHERE quantity_yards <= reorder_level")->fetchColumn();
      if ($lowStock > 0): ?>
        <span class="badge bg-danger"><?= $lowStock ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/sales.php" class="nav-link <?= ($activePage??'')==='sales'?'active':'' ?>">
      <i class="bi bi-cash-stack"></i> Sales
    </a>
    <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-link <?= ($activePage??'')==='reports'?'active':'' ?>">
      <i class="bi bi-file-earmark-bar-graph-fill"></i> Reports
    </a>
    <a href="<?= BASE_URL ?>/admin/styles.php" class="nav-link <?= ($activePage??'')==='styles'?'active':'' ?>">
      <i class="bi bi-stars"></i> Manage Catalogue
    </a>

    <?php if (hasRole('admin')): ?>
    <div class="nav-section-label">Admin</div>
    <a href="<?= BASE_URL ?>/admin/users.php" class="nav-link <?= ($activePage??'')==='users'?'active':'' ?>">
      <i class="bi bi-person-badge-fill"></i> Manage Users
    </a>
    <a href="<?= BASE_URL ?>/admin/audit_log.php" class="nav-link <?= ($activePage??'')==='audit'?'active':'' ?>">
      <i class="bi bi-journal-check"></i> Audit Log
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/index.php" target="_blank">
      <i class="bi bi-globe"></i> View Website
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="mt-2">
      <i class="bi bi-box-arrow-left"></i> Sign Out
    </a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
