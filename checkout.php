<?php
require __DIR__ . '/includes/bootstrap.php';

$cart = cart_detail();
if (!$cart['items']) { flash('info', 'Your bag is empty.'); redirect('cart.php'); }

$me = current_user();
$counties = kenya_counties();
$saved = $me ? fetch_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [$me['id']]) : [];
$def = $saved[0] ?? null;

$v = [
    'name'  => $def['full_name'] ?? ($me['name'] ?? ''),
    'email' => $me['email'] ?? '',
    'phone' => $def['phone'] ?? ($me['phone'] ?? ''),
    'address' => $def['address'] ?? '', 'town' => $def['town'] ?? '', 'county' => $def['county'] ?? config('delivery')['local_county'],
    'payment_method' => 'mpesa', 'mpesa_phone' => '', 'notes' => '', 'save_address' => '',
];
$errors = [];

if (is_post()) {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = post($k, (string) $v[$k]);
    if (!isset($_POST['payment_method'])) $v['payment_method'] = 'mpesa';

    if (mb_strlen($v['name']) < 2)                  $errors['name'] = 'Enter your full name.';
    $phone = normalize_phone($v['phone']);
    if (!$phone)                                    $errors['phone'] = 'Enter a valid Kenyan mobile number, like 0712 345 678.';
    if ($v['email'] !== '' && !valid_email($v['email'])) $errors['email'] = 'That email address does not look right.';
    if (mb_strlen($v['address']) < 5)               $errors['address'] = 'Enter the street, building or landmark for delivery.';
    if (mb_strlen($v['town']) < 2)                  $errors['town'] = 'Enter your town or estate.';
    if (!in_array($v['county'], $counties, true))   $errors['county'] = 'Choose your county.';
    if (!in_array($v['payment_method'], ['mpesa', 'cod'], true)) $errors['payment_method'] = 'Choose how you would like to pay.';
    $mpesaPhone = $phone;
    if ($v['payment_method'] === 'mpesa' && $v['mpesa_phone'] !== '') {
        $mpesaPhone = normalize_phone($v['mpesa_phone']);
        if (!$mpesaPhone) $errors['mpesa_phone'] = 'Enter the Safaricom number that will pay, like 0712 345 678.';
    }
    if (mb_strlen($v['notes']) > 500) $errors['notes'] = 'Please keep delivery notes under 500 characters.';

    if (!$errors) {
        $res = create_order([
            'name' => $v['name'], 'email' => strtolower($v['email']), 'phone' => $phone, 'address' => $v['address'],
            'town' => $v['town'], 'county' => $v['county'], 'notes' => $v['notes'],
            'payment_method' => $v['payment_method'], 'mpesa_phone' => $mpesaPhone,
        ], $cart['items'], $me ? (int) $me['id'] : null);

        if (!$res['ok']) { flash('error', $res['message']); redirect('cart.php'); }

        $order = $res['order'];
        remember_order((int) $order['id']);
        if ($me && $v['save_address'] === '1') {
            $hasDefault = (int) fetch_value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$me['id']]);
            q('INSERT INTO addresses (user_id,label,full_name,phone,address,town,county,is_default) VALUES (?,?,?,?,?,?,?,?)',
              [$me['id'], 'Home', $v['name'], $phone, $v['address'], $v['town'], $v['county'], $hasDefault ? 0 : 1]);
        }
        cart_clear();

        if ($order['payment_method'] === 'mpesa') {
            $push = mpesa_stk_push($order, $mpesaPhone);
            if (!$push['ok']) flash('error', $push['message']);
            redirect('pay.php?o=' . urlencode($order['order_number']));
        }
        redirect('order.php?o=' . urlencode($order['order_number']) . '&placed=1');
    }
}

$d = config('delivery');
$fee = delivery_fee($v['county'], $cart['subtotal']);
$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
function fe(array $errors, string $k): string { return isset($errors[$k]) ? '<div class="error" role="alert">' . e($errors[$k]) . '</div>' : ''; }
?>
<div class="wrap">
  <div class="page-head"><h1>Checkout</h1>
    <?php if (!$me): ?><p class="muted">Checking out as a guest. <a href="<?= e(url('login.php?next=' . urlencode(url('checkout.php')))) ?>">Sign in</a> to use saved details and see your order history.</p><?php endif; ?>
  </div>
  <?php foreach ($cart['notices'] as $n): ?><div class="alert alert-warn"><?= e($n) ?></div><?php endforeach; ?>
  <?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the highlighted fields and try again.</div><?php endif; ?>

  <form method="post" action="<?= e(url('checkout.php')) ?>" class="cart-layout" novalidate data-checkout
        data-local="<?= e($d['local_county']) ?>" data-local-fee="<?= (int) $d['local_fee'] ?>" data-other-fee="<?= (int) $d['other_fee'] ?>" data-free="<?= (int) $d['free_over'] ?>" data-subtotal="<?= (int) $cart['subtotal'] ?>">
    <?= csrf_field() ?>
    <div>
      <section class="panel">
        <h2>Contact and delivery</h2>
        <?php if ($saved): ?>
          <div class="field"><label for="saved">Use a saved address</label>
            <select id="saved" data-saved>
              <option value="">Enter a new address</option>
              <?php foreach ($saved as $a): ?><option value='<?= e(json_encode(['name' => $a['full_name'], 'phone' => $a['phone'], 'address' => $a['address'], 'town' => $a['town'], 'county' => $a['county']])) ?>'><?= e($a['label'] . ': ' . $a['address'] . ', ' . $a['town']) ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="row">
          <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>"><label for="name">Full name</label><input id="name" name="name" type="text" value="<?= e($v['name']) ?>" autocomplete="name" required><?= fe($errors, 'name') ?></div>
          <div class="field <?= isset($errors['phone']) ? 'has-error' : '' ?>"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="<?= e($v['phone']) ?>" autocomplete="tel" placeholder="0712 345 678" required><?= fe($errors, 'phone') ?></div>
        </div>
        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>"><label for="email">Email <span class="muted">(optional, for your receipt)</span></label><input id="email" name="email" type="email" value="<?= e($v['email']) ?>" autocomplete="email"><?= fe($errors, 'email') ?></div>
        <div class="field <?= isset($errors['address']) ? 'has-error' : '' ?>"><label for="address">Delivery address</label><input id="address" name="address" type="text" value="<?= e($v['address']) ?>" autocomplete="street-address" placeholder="Street, building or landmark" required><?= fe($errors, 'address') ?></div>
        <div class="row">
          <div class="field <?= isset($errors['town']) ? 'has-error' : '' ?>"><label for="town">Town or estate</label><input id="town" name="town" type="text" value="<?= e($v['town']) ?>" autocomplete="address-level2" required><?= fe($errors, 'town') ?></div>
          <div class="field <?= isset($errors['county']) ? 'has-error' : '' ?>"><label for="county">County</label>
            <select id="county" name="county" required><?php foreach ($counties as $c): ?><option <?= $v['county'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><?= fe($errors, 'county') ?></div>
        </div>
        <div class="field <?= isset($errors['notes']) ? 'has-error' : '' ?>"><label for="notes">Delivery notes <span class="muted">(optional)</span></label><textarea id="notes" name="notes" maxlength="500" style="min-height:80px"><?= e($v['notes']) ?></textarea><?= fe($errors, 'notes') ?></div>
        <?php if ($me): ?><label class="check"><input type="checkbox" name="save_address" value="1" <?= $v['save_address'] === '1' ? 'checked' : '' ?>> Save this address to my account</label><?php endif; ?>
      </section>

      <section class="panel">
        <h2>Payment</h2>
        <?= fe($errors, 'payment_method') ?>
        <label class="pay-opt"><input type="radio" name="payment_method" value="mpesa" <?= $v['payment_method'] === 'mpesa' ? 'checked' : '' ?>>
          <span><strong>M-Pesa</strong><span class="mpesa-mark">M-PESA</span><small>We send a payment request to your phone. Enter your PIN on the phone to approve it.</small></span></label>
        <div class="field <?= isset($errors['mpesa_phone']) ? 'has-error' : '' ?>" data-mpesa-field>
          <label for="mpesa_phone">M-Pesa number <span class="muted">(leave blank to use your phone number)</span></label>
          <input id="mpesa_phone" name="mpesa_phone" type="tel" value="<?= e($v['mpesa_phone']) ?>" placeholder="0712 345 678"><?= fe($errors, 'mpesa_phone') ?>
        </div>
        <label class="pay-opt"><input type="radio" name="payment_method" value="cod" <?= $v['payment_method'] === 'cod' ? 'checked' : '' ?>>
          <span><strong>Cash on delivery</strong><small>Pay the rider when your order arrives.</small></span></label>
      </section>
    </div>

    <aside class="summary" aria-label="Order summary">
      <h2>Your order</h2>
      <ul class="sum-items">
        <?php foreach ($cart['items'] as $it): ?>
          <li><span><?= e($it['name']) ?> &times; <?= (int) $it['quantity'] ?><small><?= e($it['color']) ?>, <?= e($it['size']) ?></small></span><span><?= e(money($it['line_total'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <div class="sum-row"><span>Subtotal</span><span><?= e(money($cart['subtotal'])) ?></span></div>
      <div class="sum-row"><span>Delivery</span><span data-fee><?= $fee > 0 ? e(money($fee)) : 'Free' ?></span></div>
      <div class="sum-row total"><span>Total</span><span data-total><?= e(money($cart['subtotal'] + $fee)) ?></span></div>
      <button class="btn btn-block" type="submit" style="margin-top:1.25rem">Place order</button>
      <p class="muted" style="font-size:.82rem;margin:.9rem 0 0">By placing your order you agree to us using your details to deliver it. We never see or store your M-Pesa PIN.</p>
    </aside>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
