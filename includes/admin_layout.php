<?php
/** Shared admin chrome. Call admin_header() after require_role(). */
function admin_header(string $title, string $active = ''): void
{
    $me = current_user();
    $nav = [
        'index' => ['Dashboard', 'index.php', ['admin', 'manager']],
        'orders' => ['Orders', 'orders.php', ['admin', 'manager']],
        'products' => ['Products', 'products.php', ['admin', 'manager']],
        'categories' => ['Categories', 'categories.php', ['admin', 'manager']],
        'inventory' => ['Inventory', 'inventory.php', ['admin', 'manager']],
        'customers' => ['Customers', 'customers.php', ['admin', 'manager']],
        'payments' => ['Payments', 'payments.php', ['admin', 'manager']],
        'reports' => ['Reports', 'reports.php', ['admin', 'manager']],
        'messages' => ['Messages', 'messages.php', ['admin', 'manager']],
        'users' => ['Staff accounts', 'users.php', ['admin']],
        'logs' => ['Activity log', 'logs.php', ['admin']],
    ];
    $unread = (int) fetch_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> | <?= e(config('app_name')) ?> Admin</title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head><body>
<div class="shell"><aside class="side"><a class="brand" href="<?= e(url('admin/')) ?>"><?= e(config('app_name')) ?></a>
<?php foreach ($nav as $k => [$label, $href, $roles]): if (!in_array($me['role'], $roles, true)) continue; ?>
  <a class="nav <?= $active === $k ? 'on' : '' ?>" href="<?= e(url('admin/' . $href)) ?>"><?= e($label) ?><?= $k === 'messages' && $unread ? ' (' . $unread . ')' : '' ?></a>
<?php endforeach; ?>
<a class="nav" href="<?= e(url()) ?>" target="_blank" rel="noopener">View store</a>
<div class="who"><?= e($me['name']) ?><br><?= e(ucfirst($me['role'])) ?>
<form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" style="color:#fff;border-color:#6b685e" type="submit">Sign out</button></form></div></aside>
<main class="main">
<?php foreach (flash_take() as $f): ?><div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach;
}
function admin_footer(): void { echo '</main></div></body></html>'; }

/** Stores an uploaded product photo safely. Returns the web path or null. SVG is never accepted. */
function save_product_image(array $file, ?string &$error = null): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload failed (code ' . $file['error'] . ').'; return null; }
    if ($file['size'] > 4 * 1024 * 1024) { $error = 'Images must be 4 MB or smaller.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) { $error = 'Only JPG, PNG or WebP images are allowed.'; return null; }
    $dir = __DIR__ . '/../uploads/products';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'u' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) { $error = 'Could not save the image.'; return null; }
    return 'uploads/products/' . $name;
}
function delete_product_image_file(string $path): void
{
    if (!preg_match('#^uploads/products/u[a-f0-9]{16}\.(jpg|png|webp)$#', $path)) return;   // never delete seeded or odd paths
    $f = __DIR__ . '/../' . $path; if (is_file($f)) @unlink($f);
}
