<?php
require __DIR__ . '/includes/bootstrap.php';
$err = '';
if (is_post()) {
    csrf_check();
    $num = strtoupper(post('order_number')); $contact = post('contact');
    $o = get_order($num);
    // Same message for "no such order" and "wrong contact" so order numbers cannot be probed.
    $ok = false;
    if ($o && $contact !== '') {
        $phone = normalize_phone($contact);
        $ok = ($phone && hash_equals((string) $o['phone'], $phone)) || ($o['email'] && hash_equals(strtolower((string) $o['email']), strtolower($contact)));
    }
    if ($ok) { remember_order((int) $o['id']); redirect('order.php?o=' . urlencode($o['order_number'])); }
    $err = 'We could not find an order matching those details. Check the order number and the phone number or email used at checkout.';
}
$pageTitle = 'Track an order';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap auth">
  <div class="page-head" style="text-align:center"><h1>Track your order</h1><p class="muted">Enter your order number and the phone number or email you used at checkout.</p></div>
  <div class="panel">
    <?php if ($err): ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="field"><label for="on">Order number</label><input id="on" name="order_number" type="text" placeholder="SN-261014-AB12C" value="<?= e(get('o', post('order_number'))) ?>" required></div>
      <div class="field"><label for="ct">Phone or email</label><input id="ct" name="contact" type="text" value="<?= e(post('contact')) ?>" required></div>
      <button class="btn btn-block" type="submit">Find my order</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
