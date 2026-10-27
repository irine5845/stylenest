<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('account.php');

$errors = []; $v = ['name' => '', 'email' => '', 'phone' => ''];
$next = $_POST['next'] ?? get('next');
if (is_post()) {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = post($k);
    $pw = (string) ($_POST['password'] ?? ''); $pw2 = (string) ($_POST['password2'] ?? '');
    $v['email'] = strtolower($v['email']);
    if (mb_strlen($v['name']) < 2) $errors['name'] = 'Enter your full name.';
    if (!valid_email($v['email'])) $errors['email'] = 'Enter a valid email address.';
    elseif (fetch_value('SELECT id FROM users WHERE email = ?', [$v['email']])) $errors['email'] = 'An account with this email already exists. Try signing in.';
    $phone = null;
    if ($v['phone'] !== '') { $phone = normalize_phone($v['phone']); if (!$phone) $errors['phone'] = 'Enter a valid Kenyan mobile number.'; }
    if ($m = password_problem($pw)) $errors['password'] = $m;
    elseif ($pw !== $pw2) $errors['password2'] = 'The passwords do not match.';
    if (empty($_POST['terms'])) $errors['terms'] = 'Please agree so we can store your details to run your account.';

    if (!$errors) {
        q('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,?)', [$v['name'], $v['email'], $phone, password_hash($pw, PASSWORD_DEFAULT), 'customer']);
        $u = fetch_one('SELECT * FROM users WHERE id = ?', [insert_id()]);
        login_user($u);
        flash('success', 'Your account is ready. Welcome to ' . config('app_name') . '.');
        redirect(safe_next($next, 'account.php'));
    }
}
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
function re(array $e, string $k): string { return isset($e[$k]) ? '<div class="error" role="alert">' . e($e[$k]) . '</div>' : ''; }
?>
<div class="wrap auth">
  <div class="page-head" style="text-align:center"><h1>Create your account</h1><p class="muted">Save your details, track orders and check out faster.</p></div>
  <div class="panel">
    <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label for="name">Full name</label><input id="name" name="name" type="text" value="<?= e($v['name']) ?>" autocomplete="name" required><?= re($errors, 'name') ?></div>
      <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e($v['email']) ?>" autocomplete="email" required><?= re($errors, 'email') ?></div>
      <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>"><label for="phone">Phone <span class="muted">(optional)</span></label><input id="phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" autocomplete="tel" placeholder="0712 345 678"><?= re($errors, 'phone') ?></div>
      <div class="field <?= isset($errors['password']) ? 'has-error' : '' ?>"><label for="pw">Password</label><input id="pw" name="password" type="password" autocomplete="new-password" required><div class="hint">At least 8 characters, with a letter and a number.</div><?= re($errors, 'password') ?></div>
      <div class="field <?= isset($errors['password2']) ? 'has-error' : '' ?>"><label for="pw2">Confirm password</label><input id="pw2" name="password2" type="password" autocomplete="new-password" required><?= re($errors, 'password2') ?></div>
      <div class="field <?= isset($errors['terms']) ? 'has-error' : '' ?>"><label class="check"><input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?>> I agree to <?= e(config('app_name')) ?> storing my details to manage my account and orders.</label><?= re($errors, 'terms') ?></div>
      <button class="btn btn-block" type="submit">Create account</button>
    </form>
    <p style="margin:1.25rem 0 0;text-align:center">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
