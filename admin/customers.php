<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();

$db = getDB();
$user = currentUser();
$activePage = 'customers';
$pageTitle  = 'Customer Records';
$breadcrumb = ['Customers' => null];

// Search & pagination
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = $search ? "WHERE u.name ILIKE ? OR u.email ILIKE ? OR u.phone ILIKE ?" : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN customers c ON c.user_id=u.id $where AND u.role='customer'");
$total->execute($search ? $params : []);
$totalCount = (int)$total->fetchColumn();

$pg = paginate($totalCount, $perPage, $page);
$stmt = $db->prepare("
    SELECT u.*, c.id AS customer_id, c.address,
           (SELECT COUNT(*) FROM orders WHERE customer_id=c.id) AS order_count
    FROM users u
    LEFT JOIN customers c ON c.user_id=u.id
    $where AND u.role='customer'
    ORDER BY u.created_at DESC
    LIMIT {$pg['perPage']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-people-fill text-pink me-2"></i>Customer Records</h3>
    <div class="subtitle"><?= $totalCount ?> customer(s) registered</div>
  </div>
  <?php if ($user['role'] === 'admin'): ?>
  <a href="<?= BASE_URL ?>/admin/customer_edit.php" class="btn btn-fashion">
    <i class="bi bi-plus-lg me-2"></i>Add Customer
  </a>
  <?php endif; ?>
</div>

<div class="card-studio">
  <div class="card-header">
    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
      <div class="search-box flex-grow-1" style="max-width:340px;">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" name="q" placeholder="Search name, email, phone…" value="<?= clean($search) ?>">
      </div>
      <button class="btn btn-fashion btn-sm">Search</button>
      <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-outline-secondary btn-sm">Clear</a>
      <?php endif; ?>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0" id="customersTable">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
            <th>Address</th><th>Orders</th><th>Joined</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><?= $c['id'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="sidebar-avatar" style="width:32px;height:32px;font-size:.8rem;">
                  <?= strtoupper(substr($c['name'],0,1)) ?>
                </div>
                <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $c['customer_id'] ?>" class="fw-600">
                  <?= clean($c['name']) ?>
                </a>
              </div>
            </td>
            <td><?= clean($c['email']) ?></td>
            <td><?= clean($c['phone']) ?></td>
            <td class="text-muted small"><?= clean($c['address'] ?? '—') ?></td>
            <td><span class="badge bg-pink-soft text-pink border" style="border-color:var(--pink-200)!important;"><?= $c['order_count'] ?></span></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/admin/customer_view.php?id=<?= $c['customer_id'] ?>"
                   class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/customer_edit.php?id=<?= $c['id'] ?>"
                   class="btn btn-sm btn-outline-fashion" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/admin/measurement_form.php?customer_id=<?= $c['customer_id'] ?>"
                   class="btn btn-sm btn-outline-primary" title="Measurements"><i class="bi bi-rulers"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?>
          <tr><td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-person-x-fill fs-1 text-pink-soft d-block mb-2"></i>
            No customers found<?= $search ? ' for <b>' . clean($search) . '</b>' : '' ?>.
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-body border-top d-flex justify-content-between align-items-center">
    <small class="text-muted">Showing <?= count($customers) ?> of <?= $totalCount ?> customers</small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $page-1 ?>">‹</a></li>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <?php if ($page < $pg['pages']): ?>
        <li class="page-item"><a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $page+1 ?>">›</a></li>
      <?php endif; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
