<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$me = require_role(['admin', 'manager']);
$id = (int) get('id');
$o = fetch_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }

if (is_post()) {
    csrf_check();
    $a = post('action');
    if ($a === 'status') {
        $r = set_order_status($id, post('status'), post('note'), (int) $me['id']);
        if ($r['ok']) log_action('order_status', $o['order_number'] . ' to ' . post('status'));
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    } elseif ($a === 'payment') {
        $r = set_payment_status($id, post('payment_status'), (int) $me['id']);
        log_action('order_payment', $o['order_number'] . ' marked ' . post('payment_status'));
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
    redirect('admin/order_view.php?id=' . $id);
}
$items = get_order_items($id); $history = get_order_history($id);
$payments = fetch_all('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$id]);
admin_header('Order ' . $o['order_number'], 'orders');
?>
<div class="top"><h1>Order <?= e($o['order_number']) ?></h1><div><button class="btn btn-ghost no-print" onclick="window.print()">Print</button> <a class="btn btn-ghost no-print" href="<?= e(url('admin/orders.php')) ?>">All orders</a></div></div>
<div class="grid3"><div>
 <div class="card"><h2>Items</h2><div class="wrap-x"><table><thead><tr><th>Product</th><th>SKU</th><th>Variant</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Total</th></tr></thead><tbody>
 <?php foreach ($items as $it): ?><tr><td><?= e($it['product_name']) ?></td><td class="hint"><?= e($it['sku']) ?></td><td><?= e($it['color']) ?>, <?= e($it['size']) ?></td><td class="num"><?= e(money($it['unit_price'])) ?></td><td class="num"><?= (int) $it['quantity'] ?></td><td class="num"><?= e(money($it['line_total'])) ?></td></tr><?php endforeach; ?>
 <tr><td colspan="5" class="num">Subtotal</td><td class="num"><?= e(money($o['subtotal'])) ?></td></tr><tr><td colspan="5" class="num">Delivery</td><td class="num"><?= e(money($o['delivery_fee'])) ?></td></tr><tr><td colspan="5" class="num"><strong>Total</strong></td><td class="num"><strong><?= e(money($o['total'])) ?></strong></td></tr>
 </tbody></table></div></div>
 <div class="card"><h2>History</h2><table><tbody><?php foreach (array_reverse($history) as $h): ?><tr><td style="white-space:nowrap"><?= e(fmt_date($h['created_at'])) ?></td><td><strong><?= e(str_starts_with($h['status'], 'payment_') ? 'Payment ' . substr($h['status'], 8) : order_status_label($h['status'])) ?></strong><?= $h['note'] ? '<br><span class="hint">' . e($h['note']) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div>
 <div class="card"><h2>Payments</h2><div class="wrap-x"><table><thead><tr><th>Date</th><th>Method</th><th>Phone</th><th>Status</th><th>Receipt</th><th class="num">Amount</th></tr></thead><tbody>
 <?php foreach ($payments as $p): ?><tr><td><?= e(fmt_date($p['created_at'])) ?></td><td><?= $p['method'] === 'mpesa' ? 'M-Pesa' : 'Cash on delivery' ?></td><td><?= e(display_phone($p['phone'])) ?></td><td><?= badge($p['status'], 'payment') ?><?= $p['result_desc'] ? '<br><span class="hint">' . e($p['result_desc']) . '</span>' : '' ?></td><td><?= e($p['mpesa_receipt'] ?: '') ?></td><td class="num"><?= e(money($p['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div><div>
 <div class="card"><h2>Status</h2><p><?= badge($o['status']) ?> <?= badge($o['payment_status'], 'payment') ?></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
   <div class="field"><label for="st">Order status</label><select id="st" name="status"><?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option><?php endforeach; ?></select><div class="hint">Cancelling returns the items to stock.</div></div>
   <div class="field"><label for="nt">Note for the history (optional)</label><input id="nt" name="note" type="text" maxlength="200" placeholder="e.g. Handed to courier"></div>
   <button class="btn" type="submit">Update status</button></form>
  <hr style="border:0;border-top:1px solid var(--line);margin:1.25rem 0">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
   <div class="field"><label for="ps">Payment status</label><select id="ps" name="payment_status"><?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $o['payment_status'] === $s ? 'selected' : '' ?>><?= e(payment_status_label($s)) ?></option><?php endforeach; ?></select><div class="hint">M-Pesa payments update automatically. Use this to confirm cash or reconcile manually.</div></div>
   <button class="btn btn-ghost" type="submit">Update payment</button></form></div>
 <div class="card"><h2>Customer</h2><p style="margin:0"><?= e($o['customer_name']) ?><br><a href="tel:<?= e($o['phone']) ?>"><?= e(display_phone($o['phone'])) ?></a><?= $o['email'] ? '<br><a href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '' ?><br><span class="hint"><?= $o['user_id'] ? 'Registered customer' : 'Guest checkout' ?></span></p></div>
 <div class="card"><h2>Deliver to</h2><p style="margin:0"><?= e($o['delivery_address']) ?><br><?= e($o['town']) ?>, <?= e($o['county']) ?></p><?= $o['notes'] ? '<p class="hint" style="margin:.75rem 0 0">Note: ' . e($o['notes']) . '</p>' : '' ?></div>
</div></div>
<?php admin_footer();
