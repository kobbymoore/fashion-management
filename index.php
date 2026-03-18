<?php
require_once __DIR__ . '/config/config.php'; // v1.1
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect logged-in users
if (isLoggedIn()) {
    redirect(BASE_URL . (hasRole('staff') ? '/admin/dashboard.php' : '/customer/dashboard.php'));
}

$styles  = getDB()->query("SELECT * FROM styles WHERE is_active=TRUE LIMIT 6")->fetchAll();
$totalCustomers = getDB()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalOrders    = getDB()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$completedOrders= getDB()->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Fashion Studio GH – Bespoke custom fashion for every occasion. Order online, get measured, and receive your perfect outfit.">
  <title><?= SITE_NAME ?> – <?= SITE_TAGLINE ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ─── NAVBAR ─── -->
<nav class="public-navbar">
  <div class="container d-flex align-items-center justify-content-between">
    <a class="navbar-brand text-pink" href="<?= BASE_URL ?>/index.php">
      <i class="bi bi-scissors"></i> <?= SITE_NAME ?>
    </a>
    <div class="d-flex align-items-center gap-2">
      <a href="#styles" class="d-none d-md-inline btn btn-outline-secondary btn-sm">Our Styles</a>
      <a href="#services" class="d-none d-md-inline btn btn-outline-secondary btn-sm">Services</a>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-fashion btn-sm">Sign In</a>
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-fashion btn-sm">Get Started</a>
    </div>
  </div>
</nav>

<!-- ─── HERO ─── -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="hero-badge">
          <i class="bi bi-stars"></i> Ghana's Premium Fashion Studio
        </div>
        <h1 class="hero-title">
          Exquisite Fashion<br><span>For Everyone.</span>
        </h1>
        <p class="hero-subtitle">
          Bespoke custom outfits for men and women, crafted to your exact measurements. 
          From sharp Senator suits to elegant Gowns — your vision, our master craft.
        </p>
        <div class="hero-btns">
          <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-fashion btn-lg">
            <i class="bi bi-lock-fill me-2"></i>Start Your Order
          </a>
          <a href="#styles" class="btn btn-outline-light btn-lg">
            <i class="bi bi-eye me-2"></i>View Styles
          </a>
        </div>
      </div>
      <div class="col-lg-5 offset-lg-1 d-none d-lg-block">
        <div class="hero-floating-card" style="animation-delay:0s;">
          <i class="bi bi-bag-check-fill"></i>
          <div><div class="fc-label">Total Orders</div><div class="fc-value"><?= $totalOrders ?>+ Happy Orders</div></div>
        </div>
        <div class="hero-floating-card" style="animation-delay:1.5s;">
          <i class="bi bi-people-fill"></i>
          <div><div class="fc-label">Customers</div><div class="fc-value"><?= $totalCustomers ?>+ Satisfied Clients</div></div>
        </div>
        <div class="hero-floating-card" style="animation-delay:3s;">
          <i class="bi bi-star-fill" style="color:var(--gold);"></i>
          <div><div class="fc-label">Completed</div><div class="fc-value"><?= $completedOrders ?>+ Outfits Delivered</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── STATS BAND ─── -->
<section class="stats-band" style="background:white;padding:2.5rem 0;border-bottom:1px solid var(--pink-100);">
  <div class="container">
    <div class="row g-4 text-center">
      <?php $stats = [['bi-scissors','Expert Tailors','Every stitch by skilled hands'],['bi-rulers','Precise Measurements','Custom fit guaranteed'],['bi-bag-check','On-Time Delivery','We respect your deadlines'],['bi-stars','Premium Fabrics','Kente, Ankara, Satin & more']]; ?>
      <?php foreach ($stats as [$icon,$title,$desc]): ?>
        <div class="col-6 col-sm-3">
          <i class="bi bi-<?= $icon ?> text-pink fs-2 d-block mb-2"></i>
          <strong class="d-block"><?= $title ?></strong>
          <small class="text-muted"><?= $desc ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── STYLES GALLERY ─── -->
<section id="styles" class="py-5" style="background:var(--light);">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label">Our Catalogue</div>
      <h2 class="section-title">Curated <span>Looks</span>, Styled With Intention</h2>
      <p class="text-muted">Every piece is crafted exclusively for you, using your measurements and preferred fabrics.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($styles as $i => $s): 
        $img = !empty($s['image_path']) ? $s['image_path'] : 'assets/images/styles/placeholder.png';
      ?>
        <div class="col-6 col-md-4">
          <div class="style-card">
            <div class="style-card-img-wrapper">
              <?php 
                $displayImg = (strpos($img, 'http') === 0) ? $img : BASE_URL . '/' . $img;
              ?>
              <img src="<?= $displayImg ?>" alt="<?= clean($s['name']) ?>">
              <div class="style-card-overlay">
                <span class="badge bg-pink text-white">Bespoke</span>
              </div>
            </div>
            <div class="style-card-body">
              <div class="style-card-title"><?= clean($s['name']) ?></div>
              <div class="style-card-price"><?= ghcFormat($s['base_price']) ?></div>
              <p class="text-muted small mt-1"><?= clean(substr($s['description'],0,80)) ?>…</p>
              <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-fashion btn-sm w-100 mt-2">Order This Style</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5">
      <p class="text-muted mb-3">Don't see what you're looking for? We craft <em>any</em> custom design!</p>
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-fashion btn-lg">
        <i class="bi bi-plus-circle me-2"></i>Request a Custom Design
      </a>
    </div>
  </div>
</section>

<!-- ─── HOW IT WORKS ─── -->
<section id="services" style="background:white;padding:5rem 0;">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label">Our Process</div>
      <h2 class="section-title">Behind <span>The Look</span></h2>
    </div>
    <div class="row g-4">
      <?php $steps = [
        ['bag-plus-fill','1. Place Your Order','Browse our styles, select your fabric, and submit your order in minutes – 24/7, from anywhere.'],
        ['rulers','2. Get Measured','Visit our studio or have staff record your precise measurements for a perfect custom fit.'],
        ['scissors','3. We Create','Our master tailors craft your outfit with care, using your exact specifications and premium fabrics.'],
        ['bag-check-fill','4. Receive Your Look','Your perfect outfit is ready. Collect in-store and look absolutely stunning!'],
      ]; ?>
      <?php foreach ($steps as [$icon,$title,$desc]): ?>
        <div class="col-sm-6 col-lg-3"><div class="service-card h-100">
          <div class="service-icon"><i class="bi bi-<?= $icon ?>"></i></div>
          <h5><?= $title ?></h5>
          <p class="text-muted small"><?= $desc ?></p>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── TESTIMONIALS ─── -->
<section style="background:linear-gradient(135deg,var(--dark),var(--dark-3));padding:5rem 0;">
  <div class="container">
    <div class="text-center mb-5">
      <div class="section-label" style="color:var(--pink-300);">Client Reviews</div>
      <h2 style="color:white;font-family:'Playfair Display',serif;">What Our Clients Say</h2>
    </div>
    <div class="row g-4">
      <?php $testimonials = [
        ['Akosua M.','Kumasi','The Kente gown they made for my graduation was absolutely perfect. Every detail matched exactly what I envisioned.',5],
        ['Adjoa T.','Accra','I love how I can track my order online. The Ankara jumpsuit was ready before the deadline and fit like a dream!',5],
        ['Yaa O.','Kumasi','Professional, talented, and so attentive to detail. My bridal gown was a masterpiece. Highly recommended!',5],
      ]; ?>
      <?php foreach ($testimonials as [$name,$city,$quote,$stars]): ?>
        <div class="col-md-4"><div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);padding:1.75rem;">
          <div class="d-flex text-warning mb-3"><?= str_repeat('<i class="bi bi-star-fill me-1"></i>',$stars) ?></div>
          <p style="color:rgba(255,255,255,.8);font-style:italic;">"<?= htmlspecialchars($quote) ?>"</p>
          <div class="d-flex align-items-center gap-2 mt-3">
            <div class="sidebar-avatar" style="width:36px;height:36px;font-size:.9rem;"><?= strtoupper(substr($name,0,1)) ?></div>
            <div><strong style="color:white;font-size:.9rem;"><?= htmlspecialchars($name) ?></strong><div style="color:rgba(255,255,255,.5);font-size:.78rem;"><?= $city ?></div></div>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── CTA ─── -->
<section style="background:linear-gradient(135deg,var(--pink-500),var(--pink-700));padding:5rem 0;text-align:center;">
  <div class="container">
    <h2 style="color:white;font-size:clamp(1.8rem,3vw,2.8rem);">Ready for Your Signature Look?</h2>
    <p style="color:rgba(255,255,255,.8);font-size:1.1rem;margin-bottom:2rem;">Let's bring your style vision to life with curate, elevated fashion from Ghana's finest studio.</p>
    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-light btn-lg me-3" style="color:var(--pink-600);font-weight:700;">
      <i class="bi bi-person-plus me-2"></i>Book Your Session
    </a>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-lg">Sign In</a>
  </div>
</section>

<!-- ─── FOOTER ─── -->
<footer style="background:var(--dark);color:rgba(255,255,255,.6);padding:3rem 0 1.5rem;">
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <a href="<?= BASE_URL ?>/index.php" style="color:white;font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:.5rem;">
          <i class="bi bi-scissors" style="color:var(--pink-400);"></i> <?= SITE_NAME ?>
        </a>
        <p style="margin-top:1rem;line-height:1.8;font-size:.875rem;">Curated looks, styled with intention. Premium bespoke fashion crafted for the discerning Ghanaian woman and man.</p>
      </div>
      <div class="col-6 col-md-2">
        <strong style="color:white;font-size:.85rem;">Quick Links</strong>
        <ul class="list-unstyled mt-2" style="font-size:.85rem;">
          <li><a href="#styles" style="color:rgba(255,255,255,.6);">Our Styles</a></li>
          <li><a href="#services" style="color:rgba(255,255,255,.6);">Services</a></li>
          <li><a href="<?= BASE_URL ?>/auth/register.php" style="color:rgba(255,255,255,.6);">Register</a></li>
          <li><a href="<?= BASE_URL ?>/auth/login.php" style="color:rgba(255,255,255,.6);">Login</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-3">
        <strong style="color:white;font-size:.85rem;">Contact Us</strong>
        <ul class="list-unstyled mt-2" style="font-size:.85rem;">
          <li><i class="bi bi-geo-alt me-2" style="color:var(--pink-400);"></i>Kumasi, Ashanti Region, Ghana</li>
          <li class="mt-1"><i class="bi bi-telephone me-2" style="color:var(--pink-400);"></i>+233 553 423 057</li>
          <li class="mt-1"><i class="bi bi-envelope me-2" style="color:var(--pink-400);"></i>kobbymoore02@gmail.com</li>
        </ul>
      </div>
      <div class="col-md-3">
        <strong style="color:white;font-size:.85rem;">Opening Hours</strong>
        <ul class="list-unstyled mt-2" style="font-size:.85rem;">
          <li>Mon–Fri: 8am – 6pm</li>
          <li class="mt-1">Saturday: 9am – 4pm</li>
          <li class="mt-1">Sunday: Closed</li>
        </ul>
      </div>
    </div>
    <hr style="border-color:rgba(255,255,255,.1);">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:.8rem;">
      <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
      <span>Designed with ❤️ in Ghana | Running on Supabase & Vercel</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
