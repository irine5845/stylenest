<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect(is_staff() ? 'admin/' : 'account.php');

$err = ''; $email = '';
$next = $_POST['next'] ?? get('next');
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email')); $pw = (string) ($_POST['password'] ?? '');
    if (login_blocked($email)) {
        $err = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MIN . ' minutes and try again.';
    } else {
        $u = fetch_one('SELECT * FROM users WHERE email = ?', [$email]);
        // Verify against a dummy hash when the user does not exist so timing does not reveal valid emails.
        $hash = $u['password_hash'] ?? '$2y$10$cYVArrN0JwYEOoK5vzGoAO3GV2NlJcqJbrieJ/V/1elA8BXKg/mda';
        if ($u && $u['is_active'] && password_verify($pw, $hash)) {
            login_clear($email);
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
            login_user($u);
            flash('success', 'Welcome back, ' . explode(' ', $u['name'])[0] . '.');
            $staff = in_array($u['role'], ['admin', 'manager'], true);
            redirect(safe_next($next, $staff ? 'admin/' : 'account.php'));
        }
        if (!$u) password_verify($pw, $hash);
        login_failed($email);
        $err = 'That email and password do not match. Please try again.';
    }
}
$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap auth">
  <div class="page-head" style="text-align:center"><h1>Sign in</h1></div>
  <div class="panel">
    <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus></div>
      <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" autocomplete="current-password" required></div>
      <button class="btn btn-block" type="submit">Sign in</button>
    </form>
    <p style="margin:1.25rem 0 0;text-align:center">New here? <a href="<?= e(url('register.php' . ($next ? '?next=' . urlencode($next) : ''))) ?>">Create an account</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
