<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
$st = get('status'); $m = get('method'); $where = ['1=1']; $params = [];
if (in_array($st, ['initiated', 'pending', 'success', 'failed', 'cancelled'], true)) { $where[] = 'p.status = ?'; $params[] = $st; }
if (in_array($m, ['mpesa', 'cod'], true)) { $where[] = 'p.method = ?'; $params[] = $m; }
$W = ' WHERE ' . implode(' AND ', $where);
$sum = fetch_one("SELECT COALESCE(SUM(CASE WHEN status='success' THEN amount END),0) ok, COALESCE(SUM(CASE WHEN status IN ('pending','initiated') THEN amount END),0) waiting, COALESCE(SUM(status='failed' OR status='cancelled'),0) bad FROM payments");
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM payments p' . $W, $params), 25, (int) get('page', '1'));
$rows = fetch_all("SELECT p.*, o.order_number, o.customer_name FROM payments p JOIN orders o ON o.id = p.order_id $W ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
admin_header('Payments', 'payments');
?>
<div class="top"><h1>Payments</h1></div>
<div class="stats"><div class="stat"><span>Received</span><strong><?= e(money($sum['ok'])) ?></strong></div><div class="stat"><span>Awaiting payment</span><strong><?= e(money($sum['waiting'])) ?></strong></div><div class="stat"><span>Failed or cancelled attempts</span><strong><?= (int) $sum['bad'] ?></strong></div></div>
<form class="filters" method="get"><div><label for="s">Status</label><select id="s" name="status"><option value="">All</option><?php foreach (['initiated', 'pending', 'success', 'failed', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= e(payment_status_label($s)) ?></option><?php endforeach; ?></select></div>
<div><label for="m">Method</label><select id="m" name="method"><option value="">All</option><option value="mpesa" <?= $m === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option><option value="cod" <?= $m === 'cod' ? 'selected' : '' ?>>Cash on delivery</option></select></div><button class="btn" type="submit">Filter</button></form>
<div class="card wrap-x"><table><thead><tr><th>Date</th><th>Order</th><th>Method</th><th>Phone</th><th>Reference</th><th>Status</th><th class="num">Amount</th></tr></thead><tbody>
<?php foreach ($rows as $p): ?><tr><td><?= e(fmt_date($p['created_at'])) ?></td><td><a href="<?= e(url('admin/order_view.php?id=' . $p['order_id'])) ?>"><?= e($p['order_number']) ?></a><br><span class="hint"><?= e($p['customer_name']) ?></span></td><td><?= $p['method'] === 'mpesa' ? 'M-Pesa' : 'Cash on delivery' ?></td><td><?= e(display_phone($p['phone'])) ?></td><td><?= e($p['mpesa_receipt'] ?: ($p['checkout_request_id'] ?: '')) ?></td><td><?= badge($p['status'], 'payment') ?><?= $p['result_desc'] ? '<br><span class="hint">' . e($p['result_desc']) . '</span>' : '' ?></td><td class="num"><?= e(money($p['amount'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="hint">No payments recorded.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
