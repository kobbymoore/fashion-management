<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireAdmin(); // Only Admin can manage roles
$db = getDB();
$activePage = 'users';

$userId = (int)($_GET['id'] ?? 0);
$isEdit = $userId > 0;
$userData = [];

if ($isEdit) {
    $stmt = $db->prepare("SELECT u.*, c.address FROM users u LEFT JOIN customers c ON c.user_id=u.id WHERE u.id=?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    if (!$userData) { setFlash('danger','User not found.'); redirect(BASE_URL.'/admin/users.php'); }
}

$pageTitle  = $isEdit ? 'Edit User' : 'Add Team Member';
$breadcrumb = ['Users'=>BASE_URL.'/admin/users.php', $pageTitle=>null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $role    = $_POST['role']    ?? 'staff';
    $address = trim($_POST['address'] ?? '');
    
    if (!$name)  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$phone) $errors[] = 'Phone is required.';

    if (!$errors) {
        $db->beginTransaction();
        try {
            if ($isEdit) {
                $db->prepare("UPDATE users SET name=?, email=?, phone=?, role=? WHERE id=?")
                   ->execute([$name, $email, $phone, $role, $userId]);
                
                // If customer, update/insert address
                if ($role === 'customer') {
                    $check = $db->prepare("SELECT id FROM customers WHERE user_id=?");
                    $check->execute([$userId]);
                    if ($check->fetch()) {
                        $db->prepare("UPDATE customers SET address=? WHERE user_id=?")->execute([$address, $userId]);
                    } else {
                        $db->prepare("INSERT INTO customers(user_id, address) VALUES(?,?)")->execute([$userId, $address]);
                    }
                }
                
                auditLog('edit_user', "Updated user #$userId ($role)");
                setFlash('success', 'User updated successfully.');
            } else {
                $pwd = $_POST['password'] ?? '';
                if (strlen($pwd) < 6) throw new Exception('Password must be at least 6 characters.');
                
                $check = $db->prepare("SELECT id FROM users WHERE email=?");
                $check->execute([$email]);
                if ($check->fetch()) throw new Exception('Email already exists.');

                $hash = password_hash($pwd, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO users(name,email,phone,password,role) VALUES(?,?,?,?,?)")
                   ->execute([$name, $email, $phone, $hash, $role]);
                $newUid = $db->lastInsertId();

                if ($role === 'customer') {
                    $db->prepare("INSERT INTO customers(user_id, address) VALUES(?,?)")
                       ->execute([$newUid, $address]);
                }

                auditLog('add_user', "Admin created user: $email as $role");
                setFlash('success', "New $role account created successfully.");
            }
            $db->commit();
            redirect(BASE_URL.'/admin/users.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:750px;">
    <div class="page-header">
        <div>
            <h3><i class="bi bi-person-plus-fill text-pink me-2"></i><?= $pageTitle ?></h3>
            <div class="subtitle"><?= $isEdit ? 'Update account details and role' : 'Create a new Staff, Admin, or Customer account' ?></div>
        </div>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="card-studio">
        <div class="card-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-600">Full Name *</label>
                        <input type="text" class="form-control" name="name" value="<?= clean($userData['name'] ?? $_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-600">Email Address *</label>
                        <input type="email" class="form-control" name="email" value="<?= clean($userData['email'] ?? $_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-600">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" value="<?= clean($userData['phone'] ?? $_POST['phone'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-600">Role</label>
                        <select class="form-select" name="role" id="roleSelector">
                            <option value="customer" <?= ($userData['role'] ?? $_POST['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="staff" <?= ($userData['role'] ?? $_POST['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>Staff Member</option>
                            <option value="admin" <?= ($userData['role'] ?? $_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>

                    <div class="col-12" id="addressField" style="<?= ($userData['role'] ?? $_POST['role'] ?? '') === 'customer' ? '' : 'display:none;' ?>">
                        <label class="form-label fw-600">Shipping Address (for customers)</label>
                        <input type="text" class="form-control" name="address" value="<?= clean($userData['address'] ?? $_POST['address'] ?? '') ?>">
                    </div>

                    <?php if (!$isEdit): ?>
                    <div class="col-md-12">
                        <label class="form-label fw-600">Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()"><i class="bi bi-eye"></i></button>
                        </div>
                        <small class="text-muted">Must be at least 6 characters.</small>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="divider-pink mt-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-fashion">
                        <i class="bi bi-save me-2"></i><?= $isEdit ? 'Update Account' : 'Create Account' ?>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const p = document.getElementById('password');
    p.type = p.type === 'password' ? 'text' : 'password';
}

document.getElementById('roleSelector').addEventListener('change', function() {
    const addr = document.getElementById('addressField');
    if (this.value === 'customer') {
        addr.style.display = 'block';
    } else {
        addr.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
