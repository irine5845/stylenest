<?php
/** General helpers used across the storefront and the admin area. */

function config(?string $key = null)
{
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/config.php';
    if ($key === null) return $cfg;
    return $cfg[$key] ?? null;
}

function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $cfg = config('base_url');
    if ($cfg !== '' && $cfg !== null) return $base = rtrim($cfg, '/');
    $root = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $dir  = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
    return $base = ($root !== '' && stripos($dir, $root) === 0) ? substr($dir, strlen($root)) : '';
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $file = __DIR__ . '/../assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}
function absolute_url(string $path = ''): string
{
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function redirect(string $path): never
{
    // Full URLs and site-absolute paths ("/shop/x.php") are used as given; anything else is relative to the app.
    if (!preg_match('#^(https?:)?//#', $path) && ($path === '' || $path[0] !== '/')) $path = url($path);
    header('Location: ' . $path);
    exit;
}
/** Only allows redirects back into this site. */
function safe_next(?string $next, string $fallback = ''): string
{
    if ($next && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\')) return $next;
    return url($fallback);
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function is_ajax(): bool { return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'; }
function post(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function get(string $key, string $default = ''): string { return trim((string) ($_GET[$key] ?? $default)); }

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- flash messages ------------------------------------------------------
function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }
function flash_take(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- formatting ----------------------------------------------------------
function money($amount): string { return 'KSh ' . number_format((float) $amount, 0); }
function fmt_date($ts, bool $time = true): string
{
    $t = is_numeric($ts) ? (int) $ts : strtotime((string) $ts);
    return $t ? date($time ? 'j M Y, g:i a' : 'j M Y', $t) : '';
}
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}
function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    if (!in_array($table, ['products', 'categories'], true)) throw new InvalidArgumentException('Bad table');
    $slug = $base = slugify($base);
    $i = 2;
    while (fetch_value("SELECT id FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $ignoreId ?? 0])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }

// ---- validation ----------------------------------------------------------
function valid_email(string $email): bool { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190; }

/** Returns 2547XXXXXXXX / 2541XXXXXXXX, or null when the number is not a valid Kenyan mobile. */
function normalize_phone(string $phone): ?string
{
    $d = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^0([17]\d{8})$/', $d, $m)) $d = '254' . $m[1];
    elseif (preg_match('/^([17]\d{8})$/', $d, $m)) $d = '254' . $m[1];
    return preg_match('/^254[17]\d{8}$/', $d) ? $d : null;
}
function display_phone(?string $p): string
{
    return ($p && preg_match('/^254(\d{3})(\d{3})(\d{3})$/', $p, $m)) ? "0{$m[1]} {$m[2]} {$m[3]}" : (string) $p;
}

// ---- images --------------------------------------------------------------
function image_url(?string $path): string
{
    if (!$path) return asset('img/placeholder.svg');
    return url($path);
}
function product_primary_image(int $productId): ?string
{
    return fetch_value('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', [$productId]);
}

// ---- store rules ---------------------------------------------------------
function delivery_fee(string $county, float $subtotal): float
{
    $d = config('delivery');
    if ($d['free_over'] > 0 && $subtotal >= $d['free_over']) return 0.0;
    return (float) (strcasecmp($county, $d['local_county']) === 0 ? $d['local_fee'] : $d['other_fee']);
}

function kenya_counties(): array
{
    return ['Baringo','Bomet','Bungoma','Busia','Elgeyo-Marakwet','Embu','Garissa','Homa Bay','Isiolo','Kajiado',
        'Kakamega','Kericho','Kiambu','Kilifi','Kirinyaga','Kisii','Kisumu','Kitui','Kwale','Laikipia','Lamu',
        'Machakos','Makueni','Mandera','Marsabit','Meru','Migori','Mombasa',"Murang'a",'Nairobi','Nakuru','Nandi',
        'Narok','Nyamira','Nyandarua','Nyeri','Samburu','Siaya','Taita-Taveta','Tana River','Tharaka-Nithi',
        'Trans Nzoia','Turkana','Uasin Gishu','Vihiga','Wajir','West Pokot'];
}

function order_status_label(string $s): string
{
    return ['pending' => 'Order received', 'processing' => 'Being prepared', 'shipped' => 'On its way',
            'delivered' => 'Delivered', 'cancelled' => 'Cancelled'][$s] ?? ucfirst($s);
}
function payment_status_label(string $s): string
{
    return ['unpaid' => 'Unpaid', 'pending' => 'Awaiting payment', 'paid' => 'Paid', 'failed' => 'Payment failed',
            'refunded' => 'Refunded', 'initiated' => 'Initiated', 'success' => 'Successful', 'cancelled' => 'Cancelled'][$s] ?? ucfirst($s);
}
/** $type: 'order' labels use customer-facing order wording, 'payment' labels use payment wording. */
function badge(string $status, string $type = 'order'): string
{
    $map = ['paid' => 'ok', 'success' => 'ok', 'delivered' => 'ok', 'shipped' => 'info', 'processing' => 'info',
            'pending' => 'warn', 'initiated' => 'warn', 'unpaid' => 'warn', 'failed' => 'bad', 'cancelled' => 'bad',
            'refunded' => 'muted', 'active' => 'ok', 'unavailable' => 'bad', 'draft' => 'muted'];
    $label = match ($type) {
        'payment' => payment_status_label($status),
        'order'   => order_status_label($status),
        default   => ucfirst($status),
    };
    return '<span class="badge badge-' . ($map[$status] ?? 'muted') . '">' . e($label) . '</span>';
}

function generate_order_number(): string
{
    do {
        $n = 'SN-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    } while (fetch_value('SELECT id FROM orders WHERE order_number = ?', [$n]));
    return $n;
}

function log_action(string $action, string $details = ''): void
{
    $uid = $_SESSION['user_id'] ?? null;
    q('INSERT INTO admin_activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)',
      [$uid, substr($action, 0, 80), substr($details, 0, 500), client_ip()]);
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}
function page_link(int $page): string
{
    $q = $_GET; $q['page'] = $page;
    return '?' . http_build_query($q);
}
