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
      <!-- Notifications Dropdown -->
      <div class="dropdown">
        <button class="btn-notif position-relative" title="Notifications" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotifsRead()">
          <i class="bi bi-bell-fill"></i>
          <?php if ($notifCount > 0): ?>
            <span class="notif-dot" id="notifBadge"></span>
          <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 p-0" aria-labelledby="notifDropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
          <li><div class="dropdown-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <h6 class="mb-0 fw-bold">Notifications</h6>
            <span class="badge bg-pink-100 text-pink-700 rounded-pill small" id="notifCountText"><?= $notifCount ?> New</span>
          </div></li>
          <div id="notifList">
            <?php 
              $headerNotifs = getNotifications($user['id'], 10);
              if ($headerNotifs): 
                foreach ($headerNotifs as $n):
            ?>
              <li class="dropdown-item p-3 border-bottom <?= !$n['is_read'] ? 'bg-light' : '' ?>">
                <div class="d-flex gap-2">
                  <div class="notif-icon-circle <?= !$n['is_read'] ? 'bg-pink' : 'bg-secondary' ?> flex-shrink-0">
                    <i class="bi bi-info-circle text-white"></i>
                  </div>
                  <div>
                    <div class="small fw-500 mb-1"><?= clean($n['message']) ?></div>
                    <div class="text-xs text-muted"><?= timeAgo($n['created_at']) ?></div>
                  </div>
                </div>
              </li>
            <?php endforeach; else: ?>
              <li class="p-4 text-center text-muted">
                <i class="bi bi-bell-slash d-block fs-3 mb-2 opacity-50"></i>
                <div class="small">No notifications yet</div>
              </li>
            <?php endif; ?>
          </div>
          <li><a class="dropdown-item text-center small text-pink-600 py-2" href="#">View all notifications</a></li>
        </ul>
      </div>

      <script>
      function markNotifsRead() {
          const badge = document.getElementById('notifBadge');
          const countText = document.getElementById('notifCountText');
          if (!badge && countText.innerText === '0 New') return;

          fetch('<?= BASE_URL ?>/api/mark_read.php')
              .then(response => response.json())
              .then(data => {
                  if (data.success) {
                      if (badge) badge.style.display = 'none';
                      if (countText) countText.innerText = '0 New';
                      // Optionally update the list styles
                      document.querySelectorAll('#notifList .dropdown-item.bg-light').forEach(item => {
                          item.classList.remove('bg-light');
                          const icon = item.querySelector('.notif-icon-circle');
                          if (icon) icon.classList.replace('bg-pink', 'bg-secondary');
                      });
                  }
              })
              .catch(err => console.error('Error marking notifications as read:', err));
      }
      </script>
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
