<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireAdmin();
$db = getDB();
$activePage = 'customers';
$isEdit = false;
$userId = (int)($_GET['id'] ?? 0);
$userData = [];

if ($userId) {
    $isEdit = true;
    $stmt = $db->prepare("SELECT u.*, c.id AS customer_id, c.address FROM users u LEFT JOIN customers c ON c.user_id=u.id WHERE u.id=?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    if (!$userData) { setFlash('danger','User not found.'); redirect(BASE_URL.'/admin/customers.php'); }
}

$pageTitle  = $isEdit ? 'Edit Customer' : 'Add Customer';
$breadcrumb = ['Customers'=>BASE_URL.'/admin/customers.php', $pageTitle=>null];
$errors = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $role    = $_POST['role'] ?? 'customer';

    if (!$name)  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$phone) $errors[] = 'Phone is required.';

    if (!$errors) {
        if ($isEdit) {
            $db->prepare("UPDATE users SET name=?,email=?,phone=?,role=? WHERE id=?")->execute([$name,$email,$phone,$role,$userId]);
            $db->prepare("UPDATE customers SET address=? WHERE user_id=?")->execute([$address,$userId]);
            auditLog('update_customer',"Updated user #$userId");
            setFlash('success','Customer updated successfully.');
            redirect(BASE_URL.'/admin/customers.php');
        } else {
            $pwd = $_POST['password'] ?? '';
            if (strlen($pwd) < 6) { $errors[] = 'Password must be at least 6 chars.'; goto renderForm; }
            $check = $db->prepare("SELECT id FROM users WHERE email=?"); $check->execute([$email]);
            if ($check->fetch()) { $errors[] = 'Email already exists.'; goto renderForm; }
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users(name,email,phone,password,role) VALUES(?,?,?,?,?)")->execute([$name,$email,$phone,$hash,$role]);
                $uid = $db->lastInsertId();
                $db->prepare("INSERT INTO customers(user_id,address) VALUES(?,?)")->execute([$uid,$address]);
                $db->commit();
                auditLog('add_customer',"Added customer: $email");
                setFlash('success','Customer added successfully.');
                redirect(BASE_URL.'/admin/customers.php');
            } catch(Exception $e) { $db->rollBack(); $errors[] = 'Failed to add customer.'; }
        }
    }
}
renderForm:
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:680px;">
  <div class="page-header">
    <div>
      <h3><?= $isEdit?'<i class="bi bi-pencil-square text-pink me-2"></i>Edit Customer':'<i class="bi bi-person-plus-fill text-pink me-2"></i>Add Customer' ?></h3>
      <div class="subtitle"><?= $isEdit?'Update customer profile':'Add a new customer to the system' ?></div>
    </div>
    <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
  <div class="card-studio">
    <div class="card-body">
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-600">Full Name *</label>
            <input type="text" class="form-control" name="name" value="<?= clean($userData['name'] ?? $_POST['name'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Email *</label>
            <input type="email" class="form-control" name="email" value="<?= clean($userData['email'] ?? $_POST['email'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Phone *</label>
            <input type="tel" class="form-control" name="phone" value="<?= clean($userData['phone'] ?? $_POST['phone'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Role</label>
            <select class="form-select" name="role">
              <?php foreach (['customer','staff','admin'] as $r): ?>
                <option value="<?= $r ?>" <?= ($userData['role'] ?? 'customer')===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-600">Address</label>
            <input type="text" class="form-control" name="address" value="<?= clean($userData['address'] ?? '') ?>">
          </div>
          <?php if (!$isEdit): ?>
          <div class="col-sm-6">
            <label class="form-label fw-600">Password *</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <div class="progress mt-1" style="height:4px;">
              <div class="progress-bar" id="passwordStrength" style="width:0%;transition:.3s;"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <hr class="divider-pink mt-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion">
            <i class="bi bi-save me-2"></i><?= $isEdit?'Save Changes':'Add Customer' ?>
          </button>
          <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
