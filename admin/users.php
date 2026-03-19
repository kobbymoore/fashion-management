<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireAdmin();
$db = getDB();
$activePage = 'users';
$pageTitle  = 'Manage Users';
$breadcrumb = ['Users'=>null];

if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $db->prepare("UPDATE users SET is_active=NOT is_active WHERE id=?")->execute([$tid]);
    auditLog('toggle_user',"Toggled user #$tid");
    setFlash('success','User status updated.'); redirect(BASE_URL.'/admin/users.php');
}

$users = $db->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div><h3><i class="bi bi-person-badge-fill text-pink me-2"></i>Manage Users</h3><div class="subtitle">System accounts and roles</div></div>
  <a href="<?= BASE_URL ?>/admin/user_form.php" class="btn btn-fashion"><i class="bi bi-person-plus-fill me-2"></i>Add Team Member</a>
</div>

<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u):
          $roleCls = ['admin'=>'danger','staff'=>'primary','customer'=>'success'][$u['role']] ?? 'secondary';
        ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td class="d-flex align-items-center gap-2">
              <div class="sidebar-avatar" style="width:32px;height:32px;font-size:.8rem;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
              <strong><?= clean($u['name']) ?></strong>
            </td>
            <td><?= clean($u['email']) ?></td>
            <td><?= clean($u['phone']) ?></td>
            <td><span class="badge bg-<?= $roleCls ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
            <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/admin/user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-fashion"><i class="bi bi-pencil"></i></a>
                <?php if ($u['id'] != currentUser()['id']): ?>
                <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm <?= $u['is_active']?'btn-outline-warning':'btn-outline-success' ?>"
                   data-confirm="<?= $u['is_active']?'Deactivate':'Activate' ?> this user?">
                  <i class="bi bi-<?= $u['is_active']?'pause-circle':'play-circle' ?>"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
