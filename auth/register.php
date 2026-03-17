<?php
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/customer/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $pwd     = trim($_POST['password'] ?? '');
    $pwdConf = trim($_POST['password_confirm'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name)                       $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$phone)                      $errors[] = 'Phone number is required.';
    if (strlen($pwd) < 6)             $errors[] = 'Password must be at least 6 characters.';
    if ($pwd !== $pwdConf)            $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db   = getDB();
        $check = $db->prepare('SELECT id FROM users WHERE email=?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $db->beginTransaction();
            try {
                $db->prepare('INSERT INTO users (name,email,phone,password,role) VALUES (?,?,?,?,?)')
                   ->execute([$name, $email, $phone, $hash, 'customer']);
                $uid = $db->lastInsertId();
                $db->prepare('INSERT INTO customers (user_id,address) VALUES (?,?)')
                   ->execute([$uid, $address]);
                $db->commit();
                auditLog('register', "New customer: $email");
                setFlash('success', 'Account created successfully! Please log in.');
                redirect(BASE_URL . '/auth/login.php');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register – <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-wrapper">
  <div class="auth-left d-none d-lg-flex">
    <div class="auth-left-content">
      <a href="<?= BASE_URL ?>/index.php" class="auth-logo">
        <i class="bi bi-scissors"></i> <?= SITE_NAME ?>
      </a>
      <h1 class="auth-headline">Join Our<br>Style Family.</h1>
      <p class="auth-tagline">Get custom-tailored outfits crafted to your exact measurements. Order from anywhere.</p>
      <div class="auth-features">
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Free style consultation</div>
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Real-time order tracking</div>
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Secure & private</div>
      </div>
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-card">
      <a href="<?= BASE_URL ?>/index.php" class="auth-logo-mobile d-lg-none">
        <i class="bi bi-scissors"></i> <?= SITE_NAME ?>
      </a>
      <h2 class="auth-title">Create Account</h2>
      <p class="auth-subtitle">Join Fashion Studio GH today</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="row g-2">
          <div class="col-12">
            <div class="form-floating">
              <input type="text" class="form-control" id="name" name="name"
                     placeholder="Full Name" value="<?= clean($_POST['name'] ?? '') ?>" required>
              <label for="name"><i class="bi bi-person me-1"></i>Full Name</label>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating">
              <input type="email" class="form-control" id="email" name="email"
                     placeholder="Email" value="<?= clean($_POST['email'] ?? '') ?>" required>
              <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating">
              <input type="tel" class="form-control" id="phone" name="phone"
                     placeholder="Phone" value="<?= clean($_POST['phone'] ?? '') ?>" required>
              <label for="phone"><i class="bi bi-telephone me-1"></i>Phone Number</label>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating">
              <input type="text" class="form-control" id="address" name="address"
                     placeholder="Address" value="<?= clean($_POST['address'] ?? '') ?>">
              <label for="address"><i class="bi bi-geo-alt me-1"></i>Address (optional)</label>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="form-floating position-relative">
              <input type="password" class="form-control" id="password" name="password"
                     placeholder="Password" required minlength="6">
              <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="form-floating">
              <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                     placeholder="Confirm" required>
              <label for="password_confirm"><i class="bi bi-lock-fill me-1"></i>Confirm</label>
            </div>
          </div>
        </div>
        <div class="form-check mt-3 mb-3">
          <input class="form-check-input" type="checkbox" id="terms" required>
          <label class="form-check-label small" for="terms">
            I agree to the <a href="#" class="link-fashion">Terms of Service</a> and <a href="#" class="link-fashion">Privacy Policy</a>
          </label>
        </div>
        <button type="submit" class="btn btn-fashion w-100 mb-3">
          <i class="bi bi-person-check me-2"></i>Create My Account
        </button>
      </form>

      <div class="auth-divider"><span>Already have an account?</span></div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-fashion w-100">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
