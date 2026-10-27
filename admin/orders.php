<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
$st = get('status'); $ps = get('payment'); $qs = get('q');
$where = ['1=1']; $params = [];
if (in_array($st, ORDER_STATUSES, true)) { $where[] = 'status = ?'; $params[] = $st; }
if (in_array($ps, PAYMENT_STATUSES, true)) { $where[] = 'payment_status = ?'; $params[] = $ps; }
if ($qs !== '') { $where[] = '(order_number LIKE ? OR customer_name LIKE ? OR phone LIKE ? OR email LIKE ?)'; $l = '%' . addcslashes($qs, '%_\\') . '%'; array_push($params, $l, $l, $l, $l); }
$W = ' WHERE ' . implode(' AND ', $where);
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM orders' . $W, $params), 20, (int) get('page', '1'));
$rows = fetch_all("SELECT * FROM orders $W ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
admin_header('Orders', 'orders');
?>
<div class="top"><h1>Orders</h1></div>
<form class="filters" method="get">
 <div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($qs) ?>" placeholder="Number, name, phone, email"></div>
 <div><label for="s">Order status</label><select id="s" name="status"><option value="">All</option><?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option><?php endforeach; ?></select></div>
 <div><label for="p">Payment</label><select id="p" name="payment"><option value="">All</option><?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $ps === $s ? 'selected' : '' ?>><?= e(payment_status_label($s)) ?></option><?php endforeach; ?></select></div>
 <button class="btn" type="submit">Filter</button></form>
<div class="card wrap-x"><table><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Method</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead><tbody>
<?php foreach ($rows as $o): ?><tr><td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a></td><td><?= e(fmt_date($o['created_at'])) ?></td><td><?= e($o['customer_name']) ?><br><span class="hint"><?= e(display_phone($o['phone'])) ?></span></td><td><?= $o['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash on delivery' ?></td><td><?= badge($o['status']) ?></td><td><?= badge($o['payment_status'], 'payment') ?></td><td class="num"><?= e(money($o['total'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="hint">No orders match.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
