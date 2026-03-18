<?php
require_once __DIR__ . '/../includes/auth_guard.php';
requireStaff();
$db = getDB();
$activePage = 'styles';
$pageTitle  = 'Catalogue Management';
$breadcrumb = ['Catalogue' => null];

// Handle delete
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Optional: Delete physical image file if it's not a default one
    $db->prepare("DELETE FROM styles WHERE id=?")->execute([$did]);
    auditLog('delete_style', "Deleted style #$did");
    setFlash('success', 'Style removed from catalogue.');
    redirect(BASE_URL . '/admin/styles.php');
}

$styles = $db->query("SELECT * FROM styles ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h3><i class="bi bi-stars text-pink me-2"></i>Catalogue Management</h3>
    <div class="subtitle"><?= count($styles) ?> total fashion styles</div>
  </div>
  <a href="<?= BASE_URL ?>/admin/styles_edit.php" class="btn btn-fashion">
    <i class="bi bi-plus-lg me-2"></i>Add New Style
  </a>
</div>

<div class="card-studio">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-studio mb-0">
        <thead>
          <tr>
            <th>Preview</th>
            <th>Name</th>
            <th>Description</th>
            <th>Base Price</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($styles as $s): ?>
          <tr>
            <td style="width: 80px;">
              <div class="style-preview-mini" style="width:60px; height:60px; border-radius:8px; overflow:hidden; background:var(--pink-50);">
                <?php 
                  $img = !empty($s['image_path']) ? $s['image_path'] : 'assets/images/styles/placeholder.png';
                  $displayImg = (strpos($img, 'http') === 0) ? $img : BASE_URL . '/' . $img;
                ?>
                <?php if (!empty($s['image_path'])): ?>
                  <img src="<?= $displayImg ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center h-100 text-pink-200">
                    <i class="bi bi-image" style="font-size:1.5rem;"></i>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="fw-600"><?= clean($s['name']) ?></div>
            </td>
            <td class="small text-muted" style="max-width:300px;">
              <?= clean(substr($s['description'], 0, 100)) ?>...
            </td>
            <td class="fw-600"><?= ghcFormat($s['base_price']) ?></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= BASE_URL ?>/admin/styles_edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-fashion">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this style?">
                  <i class="bi bi-trash"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$styles): ?>
          <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-collection fs-1 d-block mb-2"></i>No styles in catalogue.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
