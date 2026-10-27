<?php
require __DIR__ . '/includes/bootstrap.php';

$order = get_order(get('o'));
if (!$order || !can_view_order($order) || $order['payment_method'] !== 'mpesa') { http_response_code(404); flash('error', 'We could not find that order.'); redirect('track.php'); }
$payment = fetch_one("SELECT * FROM payments WHERE order_id = ? AND method = 'mpesa' ORDER BY id DESC LIMIT 1", [$order['id']]);

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'retry' && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled') {
        $phone = normalize_phone(post('phone'));
        if (!$phone) flash('error', 'Enter a valid Safaricom number like 0712 345 678.');
        else {
            q("UPDATE payments SET status = 'initiated' WHERE id = ?", [$payment['id']]);
            $r = mpesa_stk_push($order, $phone);
            flash($r['ok'] ? 'success' : 'error', $r['message']);
        }
    } elseif (in_array($action, ['sim_success', 'sim_fail'], true) && mpesa_is_simulated() && $payment && $payment['checkout_request_id']) {
        if ($action === 'sim_success') mpesa_apply_result($payment['checkout_request_id'], 0, 'Simulated payment', 'SIM' . strtoupper(bin2hex(random_bytes(4))), (float) $payment['amount'], '{"simulated":true}');
        else mpesa_apply_result($payment['checkout_request_id'], 1032, 'Request cancelled by user (simulated)', null, null, '{"simulated":true}');
    }
    redirect('pay.php?o=' . urlencode($order['order_number']));
}

if ($order['payment_status'] === 'paid') redirect('order.php?o=' . urlencode($order['order_number']) . '&placed=1');
$state = $payment['status'] ?? 'initiated';
$pageTitle = 'Complete your payment';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap narrow" style="padding-bottom:4rem">
  <div class="page-head"><h1>Complete your payment</h1><p class="muted">Order <?= e($order['order_number']) ?> &middot; <?= e(money($order['total'])) ?></p></div>

  <section class="panel pay-wait" data-pay data-order="<?= e($order['order_number']) ?>" data-state="<?= e($state) ?>" data-status-url="<?= e(url('api/payment_status.php?o=' . urlencode($order['order_number']))) ?>" data-done-url="<?= e(url('order.php?o=' . urlencode($order['order_number']) . '&placed=1')) ?>">
    <?php if ($state === 'pending'): ?>
      <div class="spinner" aria-hidden="true"></div>
      <h2>Check your phone</h2>
      <p>We sent an M-Pesa request for <strong><?= e(money($order['total'])) ?></strong> to <strong><?= e(display_phone($payment['phone'])) ?></strong>. Enter your M-Pesa PIN to approve it. This page updates by itself.</p>
      <p class="muted" data-pay-note>Waiting for confirmation&hellip;</p>
      <?php if (mpesa_is_simulated()): ?>
        <div class="alert alert-info" style="text-align:left;margin-top:1.5rem">
          <strong>Test mode.</strong> M-Pesa is running in simulation, so no real prompt was sent. Use these buttons to try both outcomes.
          <form method="post" style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap"><?= csrf_field() ?>
            <button class="btn btn-sm" name="action" value="sim_success">Simulate successful payment</button>
            <button class="btn btn-sm btn-ghost" name="action" value="sim_fail">Simulate cancelled payment</button>
          </form>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <h2><?= $state === 'cancelled' ? 'Payment was cancelled' : 'Payment did not go through' ?></h2>
      <p><?= e($payment['result_desc'] ?? 'The payment request was not completed.') ?></p>
      <p class="muted">Your order is saved and your items are held for you. Try again below, or <a href="<?= e(url('contact.php')) ?>">contact us</a> for help.</p>
    <?php endif; ?>
  </section>

  <?php if ($state !== 'pending' && $order['status'] !== 'cancelled'): ?>
  <section class="panel">
    <h2>Send a new payment request</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retry">
      <div class="field"><label for="phone">M-Pesa number</label><input id="phone" name="phone" type="tel" value="<?= e(display_phone($payment['phone'] ?? $order['phone'])) ?>" required></div>
      <button class="btn" type="submit">Send payment request</button>
    </form>
  </section>
  <?php endif; ?>
  <p><a href="<?= e(url('order.php?o=' . urlencode($order['order_number']))) ?>">View order details</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
