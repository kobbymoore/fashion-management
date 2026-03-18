<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$s  = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM styles WHERE id=?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) {
        setFlash('danger', 'Style not found.');
        redirect(BASE_URL . '/admin/styles.php');
    }
}

$pageTitle  = $id ? 'Edit Style' : 'Add New Style';
$activePage = 'styles';
$breadcrumb = ['Catalogue' => BASE_URL . '/admin/styles.php', $pageTitle => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = (float)($_POST['base_price'] ?? 0);
    $imgPath = $s['image_path'] ?? '';

    if (!$name) $errors[] = 'Style name is required.';
    if ($price <= 0) $errors[] = 'Base price must be greater than zero.';

    // Handle Image Upload
    if (isset($_FILES['style_image']) && $_FILES['style_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['style_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $newName = 'style_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/images/styles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            if (move_uploaded_file($_FILES['style_image']['tmp_name'], $uploadDir . $newName)) {
                $imgPath = 'assets/images/styles/'  . $newName;
            } else {
                $errors[] = 'Failed to save uploaded image.';
            }
        } else {
            $errors[] = 'Invalid image format. Allowed: ' . implode(', ', $allowed);
        }
    }

    if (!$errors) {
        if ($id) {
            $db->prepare("UPDATE styles SET name=?, description=?, base_price=?, image_path=? WHERE id=?")
               ->execute([$name, $desc, $price, $imgPath, $id]);
            auditLog('update_style', "Updated style: $name (#$id)");
        } else {
            $db->prepare("INSERT INTO styles (name, description, base_price, image_path, is_active) VALUES (?, ?, ?, ?, TRUE)")
               ->execute([$name, $desc, $price, $imgPath]);
            auditLog('add_style', "Added new style: $name");
        }
        setFlash('success', 'Style saved successfully.');
        redirect(BASE_URL . '/admin/styles.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:800px;">
  <div class="page-header">
    <div>
      <h3><i class="bi bi-stars text-pink me-2"></i><?= $pageTitle ?></h3>
      <div class="subtitle">Customize how this style appears in your public catalogue</div>
    </div>
    <a href="<?= BASE_URL ?>/admin/styles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>

  <div class="card-studio">
    <div class="card-body">
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
          <div class="col-md-8">
            <div class="mb-3">
              <label class="form-label fw-600">Style Name *</label>
              <input type="text" class="form-control" name="name" value="<?= clean($s['name'] ?? '') ?>" placeholder="e.g. Premium Senator Suit" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-600">Base Price (GH₵) *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="base_price" value="<?= $s['base_price'] ?? 0 ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-600">Description</label>
              <textarea class="form-control" name="description" rows="5" placeholder="Describe the style, fabric options, etc."><?= clean($s['description'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="col-md-4 border-start">
            <div class="mb-3">
              <label class="form-label fw-600">Style Image</label>
              <div class="style-image-preview mb-3 p-2 border rounded text-center bg-light" style="min-height:200px; display:flex; align-items:center; justify-content:center;">
                <?php if (!empty($s['image_path'])): ?>
                  <img src="<?= BASE_URL ?>/<?= $s['image_path'] ?>" id="imgPreview" style="max-width:100%; max-height:250px; border-radius:8px;">
                <?php else: ?>
                  <div id="noImg" class="text-muted small">
                    <i class="bi bi-image fs-1 d-block mb-1"></i>
                    No image uploaded
                  </div>
                  <img id="imgPreview" style="display:none; max-width:100%; max-height:250px; border-radius:8px;">
                <?php endif; ?>
              </div>
              <input type="file" class="form-control form-control-sm" name="style_image" id="styleImageInput" accept="image/*">
              <small class="text-muted d-block mt-2">Recommended: Square aspect ratio (e.g. 800x800px). JPG, PNG, WEBP allowed.</small>
            </div>
          </div>
        </div>

        <hr class="divider-pink mt-4">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-fashion btn-lg px-4"><i class="bi bi-check-circle me-2"></i>Save Style</button>
          <a href="<?= BASE_URL ?>/admin/styles.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('styleImageInput').onchange = function (evt) {
    const [file] = this.files;
    if (file) {
        const preview = document.getElementById('imgPreview');
        const noImg = document.getElementById('noImg');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
        if(noImg) noImg.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
