<?php
/** Authentication, roles and login throttling. */

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['user_id'])) return $cache = null;
    $u = fetch_one('SELECT id, name, email, phone, role, is_active FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$u || !$u['is_active']) {
        unset($_SESSION['user_id']);
        return $cache = null;
    }
    return $cache = $u;
}
function is_staff(): bool { $u = current_user(); return $u && in_array($u['role'], ['admin', 'manager'], true); }

function login_user(array $user): void
{
    session_regenerate_id(true);             // prevents session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    cart_merge_saved((int) $user['id']);
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Please sign in to continue.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $u;
}
/** $roles e.g. ['admin','manager']. Sends everyone else away. */
function require_role(array $roles): array
{
    $u = current_user();
    if (!$u) redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('<!doctype html><title>Not allowed</title><body style="font-family:sans-serif;padding:3rem"><h1>Access denied</h1><p>Your account does not have permission to open this page.</p><p><a href="' . e(url()) . '">Back to the store</a></p></body>');
    }
    return $u;
}

// ---- brute-force protection -------------------------------------------------
const LOGIN_MAX_FAILS = 5;
const LOGIN_WINDOW_MIN = 15;

function login_blocked(string $email): bool
{
    $n = fetch_value('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND email = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE)',
                     [client_ip(), strtolower($email)]);
    return (int) $n >= LOGIN_MAX_FAILS;
}
function login_failed(string $email): void
{
    q('INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)', [client_ip(), strtolower($email)]);
    q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}
function login_clear(string $email): void
{
    q('DELETE FROM login_attempts WHERE ip_address = ? AND email = ?', [client_ip(), strtolower($email)]);
}

function password_problem(string $pw): ?string
{
    if (strlen($pw) < 8) return 'Use at least 8 characters for your password.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Include at least one letter and one number in your password.';
    return null;
}
