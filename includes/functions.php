<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// ─── Flash Messages ──────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = [
        'success' => 'check-circle-fill',
        'danger'  => 'exclamation-triangle-fill',
        'warning' => 'exclamation-circle-fill',
        'info'    => 'info-circle-fill',
    ];
    $icon = $icons[$flash['type']] ?? 'info-circle-fill';
    return '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-' . $icon . '"></i>
        <span>' . htmlspecialchars($flash['message']) . '</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// ─── Input Sanitisation ──────────────────────────────────────
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ─── Redirect ────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ─── Current User ────────────────────────────────────────────
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    // Basic check + ensure the user array has the expected ID
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function hasRole(string $role): bool {
    $user = currentUser();
    if (!$user) return false;
    if ($role === 'staff') return in_array($user['role'], ['staff', 'admin']);
    return $user['role'] === $role;
}

// ─── Audit Logger ────────────────────────────────────────────
function auditLog(string $action, string $details = ''): void {
    $db   = getDB();
    $user = currentUser();
    $uid  = $user ? (int)$user['id'] : null;
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?,?,?,?)');
    $stmt->execute([$uid, $action, $details, $ip]);
}

// ─── Notifications ────────────────────────────────────────────
function addNotification(int $userId, string $message): void {
    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO notifications (user_id, message) VALUES (?,?)');
    $stmt->execute([$userId, $message]);
}

function unreadCount(): int {
    if (!isLoggedIn()) return 0;
    $db   = getDB();
    $user = currentUser();
    $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
    $stmt->execute([$user['id']]);
    return (int)$stmt->fetchColumn();
}

// ─── Pagination ──────────────────────────────────────────────
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'perPage' => $perPage, 'current' => $current, 'pages' => $pages,
            'offset' => ($current - 1) * $perPage];
}

// ─── Status Badge ────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'pending'     => 'warning',
        'approved'    => 'info',
        'in-progress' => 'primary',
        'completed'   => 'success',
        'cancelled'   => 'danger',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . ucfirst($status) . '</span>';
}

// ─── Currency ────────────────────────────────────────────────
function ghcFormat(float $amount): string {
    return 'GH₵ ' . number_format($amount, 2);
}

// ─── Time Ago ────────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60)   . 'm ago';
    if ($diff < 86400)  return floor($diff/3600)  . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}
