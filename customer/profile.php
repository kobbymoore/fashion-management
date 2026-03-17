<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();
if (hasRole('staff')) redirect(BASE_URL.'/admin/dashboard.php');
$db = getDB(); $user = currentUser();
$activePage = 'profile'; $pageTitle = 'My Profile';
$cust = $db->prepare("SELECT * FROM customers WHERE user_id=?"); $cust->execute([$user['id']]); $cust = $cust->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $pwd     = trim($_POST['password'] ?? '');
    $pwdOld  = trim($_POST['password_old'] ?? '');

    if (!$name)  $errors[] = 'Name is required.';
    if (!$phone) $errors[] = 'Phone is required.';

    if ($pwd) {
        $curHash = $db->prepare("SELECT password FROM users WHERE id=?"); $curHash->execute([$user['id']]);
        if (!password_verify($pwdOld, $curHash->fetchColumn())) $errors[] = 'Current password is incorrect.';
        elseif (strlen($pwd) < 6) $errors[] = 'New password must be at least 6 chars.';
    }

    if (!$errors) {
        $db->prepare("UPDATE users SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$user['id']]);
        if ($cust) $db->prepare("UPDATE customers SET address=? WHERE user_id=?")->execute([$address,$user['id']]);
        if ($pwd)  $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pwd,PASSWORD_DEFAULT),$user['id']]);
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['phone'] = $phone;
        auditLog('update_profile','Profile updated');
        setFlash('success','Profile updated successfully!');
        redirect(BASE_URL.'/customer/profile.php');
    }
}
// re-fetch user
$userData = $db->prepare("SELECT * FROM users WHERE id=?"); $userData->execute([$user['id']]); $userData = $userData->fetch();
require_once __DIR__ . '/../includes/customer_header.php';
?>

<div style="max-width:640px;margin:0 auto;">
  <div class="page-header">
    <div><h3><i class="bi bi-person-fill text-pink me-2"></i>My Profile</h3><div class="subtitle">Manage your account details</div></div>
  </div>

  <div class="card-studio mb-3">
    <div class="card-body text-center py-4 border-bottom">
      <div class="profile-avatar-lg"><?= strtoupper(substr($userData['name'],0,1)) ?></div>
      <h5 class="mb-0"><?= clean($userData['name']) ?></h5>
      <span class="text-muted small"><?= clean($userData['email']) ?></span>
      <div class="mt-1"><span class="badge bg-success">Customer</span></div>
    </div>
    <div class="card-body">
      <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-600">Full Name *</label>
            <input type="text" class="form-control" name="name" value="<?= clean($userData['name']) ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Email</label>
            <input type="email" class="form-control" value="<?= clean($userData['email']) ?>" disabled>
            <small class="text-muted">Email cannot be changed</small>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Phone *</label>
            <input type="tel" class="form-control" name="phone" value="<?= clean($userData['phone']) ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Address</label>
            <input type="text" class="form-control" name="address" value="<?= clean($cust['address'] ?? '') ?>">
          </div>
          <div class="col-12"><hr class="divider-pink"><h6 class="text-muted">Change Password (leave blank to keep current)</h6></div>
          <div class="col-sm-6">
            <label class="form-label fw-600">Current Password</label>
            <input type="password" class="form-control" name="password_old">
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-600">New Password</label>
            <input type="password" class="form-control" name="password" minlength="6" id="password">
          </div>
        </div>
        <hr class="divider-pink mt-3">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion"><i class="bi bi-save me-2"></i>Save Changes</button>
          <a href="<?= BASE_URL ?>/customer/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card-studio">
    <div class="card-body">
      <h6 class="text-muted mb-3"><i class="bi bi-info-circle me-2"></i>Account Information</h6>
      <div class="row g-2 small">
        <div class="col-6"><strong>Member Since</strong><p class="text-muted mb-0"><?= date('F j, Y', strtotime($userData['created_at'])) ?></p></div>
        <div class="col-6"><strong>Measurements</strong><p class="text-muted mb-0">
          <?php $mC = $db->prepare("SELECT COUNT(*) FROM measurements WHERE customer_id=?"); $mC->execute([$cust['id']]); ?>
          <?= $mC->fetchColumn() > 0 ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>On file</span>' : '<span class="text-warning">Not recorded yet (visit us)</span>' ?>
        </p></div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/customer_footer.php'; ?>
