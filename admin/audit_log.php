<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireAdmin();
$db = getDB();
$activePage = 'audit';
$pageTitle  = 'Audit Log';
$breadcrumb = ['Audit Log'=>null];
$page = max(1,(int)($_GET['page']??1));
$perPage = 20;
$total = (int)$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$pg = paginate($total, $perPage, $page);
$logs = $db->prepare("SELECT al.*, u.name FROM audit_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$logs->execute();
$logs = $logs->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><h3><i class="bi bi-journal-check text-pink me-2"></i>Audit Log</h3><div class="subtitle"><?= $total ?> events recorded</div></div>
</div>
<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead><tr><th>#</th><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><?= $l['id'] ?></td>
            <td><?= clean($l['name'] ?? 'System') ?></td>
            <td><code class="text-pink"><?= clean($l['action']) ?></code></td>
            <td class="small text-muted"><?= clean($l['details'] ?? '—') ?></td>
            <td class="small text-muted"><?= clean($l['ip_address'] ?? '—') ?></td>
            <td class="small text-muted"><?= date('M j, Y H:i', strtotime($l['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-body border-top d-flex justify-content-end">
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for($i=1;$i<=$pg['pages'];$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
