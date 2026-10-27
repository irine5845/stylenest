<?php
require __DIR__ . '/includes/bootstrap.php';

$order = get_order(get('o'));
if (!$order || !can_view_order($order)) {
    flash('info', 'Enter your order number and phone number or email to view an order.');
    redirect('track.php' . (get('o') !== '' ? '?o=' . urlencode(get('o')) : ''));
}
$items   = get_order_items((int) $order['id']);
$history = get_order_history((int) $order['id']);
$payment = fetch_one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
$placed  = get('placed') === '1';

$steps = ['pending' => 'Order received', 'processing' => 'Being prepared', 'shipped' => 'On its way', 'delivered' => 'Delivered'];
$keys  = array_keys($steps);
$pos   = array_search($order['status'], $keys, true);

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="padding-bottom:4rem">
  <?php if ($placed): ?>
    <div class="panel pay-wait" style="margin-top:2rem">
      <div class="checkmark" aria-hidden="true">&#10003;</div>
      <h1 style="font-size:2.6rem">Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?></h1>
      <p>Your order <strong><?= e($order['order_number']) ?></strong> is confirmed.
        <?= $order['payment_method'] === 'mpesa' && $order['payment_status'] === 'paid' ? 'We received your M-Pesa payment.' : ($order['payment_method'] === 'cod' ? 'Please have ' . e(money($order['total'])) . ' ready when it arrives.' : '') ?></p>
      <?php if ($order['email']): ?><p class="muted">Keep this order number to track your delivery.</p><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="page-head"><div class="crumbs"><a href="<?= e(url('account.php')) ?>">Account</a> / Order</div><h1>Order <?= e($order['order_number']) ?></h1></div>
  <?php endif; ?>

  <div class="cart-layout">
    <div>
      <section class="panel">
        <h2>Delivery progress</h2>
        <?php if ($order['status'] === 'cancelled'): ?>
          <p><?= badge('cancelled') ?> This order was cancelled.</p>
        <?php else: ?>
          <div class="progress" aria-label="Order progress">
            <?php foreach ($keys as $i => $k): ?><div class="<?= $i <= $pos ? 'done' : '' ?>"><?= e($steps[$k]) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <ul class="timeline">
          <?php foreach (array_reverse($history) as $h): ?>
            <li><strong><?= e(str_starts_with($h['status'], 'payment_') ? 'Payment ' . substr($h['status'], 8) : order_status_label($h['status'])) ?></strong>
              <?php if ($h['note']): ?><small><?= e($h['note']) ?></small><?php endif; ?><small><?= e(fmt_date($h['created_at'])) ?></small></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="panel">
        <h2>Items</h2>
        <div class="table-wrap"><table class="t">
          <thead><tr><th>Product</th><th>Details</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['product_name']) ?></td><td><?= e($it['color']) ?>, <?= e($it['size']) ?></td><td class="num"><?= (int) $it['quantity'] ?></td><td class="num"><?= e(money($it['line_total'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
    </div>

    <aside>
      <section class="panel">
        <h2>Summary</h2>
        <div class="sum-row"><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
        <div class="sum-row"><span>Delivery</span><span><?= $order['delivery_fee'] > 0 ? e(money($order['delivery_fee'])) : 'Free' ?></span></div>
        <div class="sum-row total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        <p style="margin-top:1rem"><strong>Payment:</strong> <?= $order['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash on delivery' ?> <?= badge($order['payment_status'], 'payment') ?></p>
        <?php if ($payment && $payment['mpesa_receipt']): ?><p class="muted">M-Pesa receipt: <?= e($payment['mpesa_receipt']) ?></p><?php endif; ?>
        <?php if ($order['payment_method'] === 'mpesa' && in_array($order['payment_status'], ['pending', 'failed'], true) && $order['status'] !== 'cancelled'): ?>
          <a class="btn btn-block" href="<?= e(url('pay.php?o=' . urlencode($order['order_number']))) ?>">Complete payment</a>
        <?php endif; ?>
      </section>
      <section class="panel">
        <h2>Delivery details</h2>
        <p style="margin:0"><?= e($order['customer_name']) ?><br><?= e($order['delivery_address']) ?><br><?= e($order['town']) ?>, <?= e($order['county']) ?><br><?= e(display_phone($order['phone'])) ?></p>
        <?php if ($order['notes']): ?><p class="muted" style="margin:.75rem 0 0">Note: <?= e($order['notes']) ?></p><?php endif; ?>
        <p class="muted" style="margin:.75rem 0 0">Placed <?= e(fmt_date($order['created_at'])) ?></p>
      </section>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
