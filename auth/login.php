<?php
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = currentUser();
    redirect(BASE_URL . (hasRole('staff') ? '/admin/dashboard.php' : '/customer/dashboard.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email=? AND is_active=1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
                'phone' => $user['phone'],
            ];
            auditLog('login', 'User logged in: ' . $user['email']);
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect(BASE_URL . (in_array($user['role'], ['staff','admin']) ? '/admin/dashboard.php' : '/customer/dashboard.php'));
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
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
      <h1 class="auth-headline">Your Style,<br>Our Craft.</h1>
      <p class="auth-tagline">Premium bespoke fashion crafted just for you. Every stitch tells your story.</p>
      <div class="auth-features">
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Custom measurements</div>
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Track your orders live</div>
        <div class="auth-feature-item"><i class="bi bi-check-circle-fill"></i> Unique styles, your vision</div>
      </div>
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-card">
      <a href="<?= BASE_URL ?>/index.php" class="auth-logo-mobile d-lg-none">
        <i class="bi bi-scissors"></i> <?= SITE_NAME ?>
      </a>
      <h2 class="auth-title">Welcome Back</h2>
      <p class="auth-subtitle">Sign in to your account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="loginForm" novalidate>
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="email" name="email"
                 placeholder="you@email.com" value="<?= clean($_POST['email'] ?? '') ?>" required>
          <label for="email"><i class="bi bi-envelope me-1"></i>Email Address</label>
        </div>
        <div class="form-floating mb-3 position-relative">
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="Password" required>
          <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
          <button type="button" class="btn-eye" id="togglePassword" tabindex="-1">
            <i class="bi bi-eye-slash" id="eyeIcon"></i>
          </button>
        </div>
        <button type="submit" class="btn btn-fashion w-100 mb-3">
          <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
      </form>

      <div class="auth-divider"><span>New here?</span></div>
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-outline-fashion w-100">
        <i class="bi bi-person-plus me-2"></i>Create Account
      </a>

      <div class="auth-demo-info mt-4">
        <p class="text-muted small text-center mb-1"><strong>Demo Credentials:</strong></p>
        <div class="demo-creds">
          <span><i class="bi bi-shield-fill-check text-danger"></i> Admin: admin@fashionstudio.gh</span>
          <span><i class="bi bi-person-badge-fill text-primary"></i> Staff: staff@fashionstudio.gh</span>
          <span><i class="bi bi-person-fill text-success"></i> Customer: ama@example.com</span>
          <em class="text-muted">Password for all: Admin@1234</em>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const tp = document.getElementById('togglePassword');
  const ei = document.getElementById('eyeIcon');
  const pw = document.getElementById('password');
  tp.addEventListener('click', () => {
    const isText = pw.type === 'text';
    pw.type = isText ? 'password' : 'text';
    ei.className = isText ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
</script>
</body>
</html>
