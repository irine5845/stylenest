<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'profile') {
        $name = post('name'); $phoneRaw = post('phone'); $phone = $phoneRaw !== '' ? normalize_phone($phoneRaw) : null;
        if (mb_strlen($name) < 2) $errors['name'] = 'Enter your name.';
        if ($phoneRaw !== '' && !$phone) $errors['phone'] = 'Enter a valid Kenyan mobile number.';
        if (!$errors) { q('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $me['id']]); flash('success', 'Profile updated.'); redirect('account.php'); }
    } elseif ($action === 'password') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if (!password_verify((string) ($_POST['current'] ?? ''), $row['password_hash'])) $errors['current'] = 'Your current password is not correct.';
        elseif ($m = password_problem((string) ($_POST['new'] ?? ''))) $errors['new'] = $m;
        elseif (($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')) $errors['new2'] = 'The new passwords do not match.';
        else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($_POST['new'], PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.'); redirect('account.php');
        }
    } elseif ($action === 'add_address') {
        $n = post('full_name'); $ph = normalize_phone(post('phone')); $ad = post('address'); $tw = post('town'); $co = post('county');
        if ($n === '' || !$ph || mb_strlen($ad) < 5 || $tw === '' || !in_array($co, kenya_counties(), true)) $errors['address'] = 'Please fill in every address field with valid details.';
        else {
            $first = !fetch_value('SELECT id FROM addresses WHERE user_id = ?', [$me['id']]);
            q('INSERT INTO addresses (user_id,label,full_name,phone,address,town,county,is_default) VALUES (?,?,?,?,?,?,?,?)', [$me['id'], post('label', 'Home') ?: 'Home', $n, $ph, $ad, $tw, $co, $first ? 1 : 0]);
            flash('success', 'Address saved.'); redirect('account.php');
        }
    } elseif ($action === 'delete_address') {
        q('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) post('id'), $me['id']]);
        flash('success', 'Address removed.'); redirect('account.php');
    } elseif ($action === 'default_address') {
        q('UPDATE addresses SET is_default = (id = ?) WHERE user_id = ?', [(int) post('id'), $me['id']]);
        flash('success', 'Default address updated.'); redirect('account.php');
    }
}
$user = fetch_one('SELECT * FROM users WHERE id = ?', [$me['id']]);
$addresses = fetch_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$me['id']]);
$orders = fetch_all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$me['id']]);
$pageTitle = 'My account';
require __DIR__ . '/includes/header.php';
function ae(array $e, string $k): string { return isset($e[$k]) ? '<div class="error" role="alert">' . e($e[$k]) . '</div>' : ''; }
?>
<div class="wrap" style="padding-bottom:4rem">
  <div class="page-head" style="display:flex;justify-content:space-between;align-items:end;flex-wrap:wrap;gap:1rem">
    <div><h1>Hello, <?= e(explode(' ', $user['name'])[0]) ?></h1><p class="muted" style="margin:0"><?= e($user['email']) ?></p></div>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
  </div>

  <section class="panel">
    <div class="section-head" style="margin-bottom:1rem"><h2 style="margin:0">Recent orders</h2><a href="<?= e(url('orders.php')) ?>">View all orders</a></div>
    <?php if (!$orders): ?><p class="muted">You have not placed any orders yet. <a href="<?= e(url('shop.php')) ?>">Start shopping</a></p>
    <?php else: ?><div class="table-wrap"><table class="t"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('order.php?o=' . urlencode($o['order_number']))) ?>"><?= e($o['order_number']) ?></a></td><td><?= e(fmt_date($o['created_at'], false)) ?></td><td><?= badge($o['status']) ?></td><td><?= badge($o['payment_status'], 'payment') ?></td><td class="num"><?= e(money($o['total'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>

  <div class="account-grid">
    <section class="panel">
      <h2>Profile</h2>
      <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label for="n">Full name</label><input id="n" name="name" type="text" value="<?= e($user['name']) ?>" required><?= ae($errors, 'name') ?></div>
        <div class="field"><label for="em">Email</label><input id="em" type="email" value="<?= e($user['email']) ?>" disabled><div class="hint">Contact us if you need to change your email.</div></div>
        <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>"><label for="ph">Phone</label><input id="ph" name="phone" type="tel" value="<?= e(display_phone($user['phone'])) ?>"><?= ae($errors, 'phone') ?></div>
        <button class="btn" type="submit">Save profile</button>
      </form>
    </section>
    <section class="panel">
      <h2>Change password</h2>
      <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field <?= isset($errors['current']) ? 'has-error' : '' ?>"><label for="c">Current password</label><input id="c" name="current" type="password" autocomplete="current-password" required><?= ae($errors, 'current') ?></div>
        <div class="field <?= isset($errors['new']) ? 'has-error' : '' ?>"><label for="nw">New password</label><input id="nw" name="new" type="password" autocomplete="new-password" required><?= ae($errors, 'new') ?></div>
        <div class="field <?= isset($errors['new2']) ? 'has-error' : '' ?>"><label for="nw2">Confirm new password</label><input id="nw2" name="new2" type="password" autocomplete="new-password" required><?= ae($errors, 'new2') ?></div>
        <button class="btn" type="submit">Change password</button>
      </form>
    </section>
  </div>

  <section class="panel">
    <h2>Delivery addresses</h2>
    <?php foreach ($addresses as $a): ?>
      <div class="addr"><strong><?= e($a['label']) ?></strong> <?= $a['is_default'] ? '<span class="badge badge-ok">Default</span>' : '' ?><br><?= e($a['full_name']) ?>, <?= e(display_phone($a['phone'])) ?><br><?= e($a['address']) ?>, <?= e($a['town']) ?>, <?= e($a['county']) ?>
        <div style="display:flex;gap:1rem;margin-top:.5rem">
          <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default_address"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="link-btn" type="submit">Make default</button></form><?php endif; ?>
          <form method="post" onsubmit="return confirm('Remove this address?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_address"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="link-btn" type="submit">Remove</button></form>
        </div></div>
    <?php endforeach; ?>
    <details style="margin-top:1rem" <?= isset($errors['address']) ? 'open' : '' ?>><summary style="cursor:pointer;font-weight:600">Add an address</summary>
      <form method="post" style="margin-top:1rem" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="add_address">
        <?= ae($errors, 'address') ?>
        <div class="row"><div class="field"><label for="al">Label</label><input id="al" name="label" type="text" value="Home"></div><div class="field"><label for="afn">Full name</label><input id="afn" name="full_name" type="text" value="<?= e($user['name']) ?>" required></div></div>
        <div class="row"><div class="field"><label for="ap">Phone</label><input id="ap" name="phone" type="tel" required></div><div class="field"><label for="aa">Address</label><input id="aa" name="address" type="text" required></div></div>
        <div class="row"><div class="field"><label for="at">Town or estate</label><input id="at" name="town" type="text" required></div><div class="field"><label for="ac">County</label><select id="ac" name="county"><?php foreach (kenya_counties() as $c): ?><option <?= $c === config('delivery')['local_county'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div></div>
        <button class="btn" type="submit">Save address</button>
      </form>
    </details>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
