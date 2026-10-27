<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$from = $valid(get('from')) ? get('from') : date('Y-m-d', strtotime('-29 days'));
$to   = $valid(get('to')) ? get('to') : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$p = [$from . ' 00:00:00', $to . ' 23:59:59'];

$sum = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(total),0) booked, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total END),0) paid, COALESCE(AVG(total),0) aov FROM orders WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ?", $p);
$cancelled = (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status = 'cancelled' AND created_at BETWEEN ? AND ?", $p);
$top = fetch_all("SELECT i.product_name, SUM(i.quantity) qty, SUM(i.line_total) revenue FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status <> 'cancelled' AND o.created_at BETWEEN ? AND ? GROUP BY i.product_name ORDER BY revenue DESC LIMIT 10", $p);
$cat = fetch_all("SELECT COALESCE(c.name,'Other') name, SUM(i.quantity) qty, SUM(i.line_total) revenue FROM order_items i JOIN orders o ON o.id = i.order_id LEFT JOIN products pr ON pr.id = i.product_id LEFT JOIN categories c ON c.id = pr.category_id WHERE o.status <> 'cancelled' AND o.created_at BETWEEN ? AND ? GROUP BY c.name ORDER BY revenue DESC", $p);
$daily = fetch_all("SELECT DATE(created_at) d, COUNT(*) n, SUM(total) t FROM orders WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d", $p);
$method = fetch_all("SELECT payment_method m, COUNT(*) n, SUM(total) t FROM orders WHERE status <> 'cancelled' AND created_at BETWEEN ? AND ? GROUP BY payment_method", $p);
$stockValue = fetch_one("SELECT COALESCE(SUM(v.stock),0) units, COALESCE(SUM(v.stock * p.price),0) val FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status = 'active'");

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stylenest-sales-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    $safe = fn($v) => (is_string($v) && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v;      // blocks spreadsheet formula injection
    fputcsv($out, ['Order', 'Date', 'Customer', 'Method', 'Order status', 'Payment status', 'Subtotal', 'Delivery', 'Total']);
    foreach (fetch_all('SELECT * FROM orders WHERE created_at BETWEEN ? AND ? ORDER BY id', $p) as $o)
        fputcsv($out, [$o['order_number'], $o['created_at'], $safe($o['customer_name']), $o['payment_method'], $o['status'], $o['payment_status'], $o['subtotal'], $o['delivery_fee'], $o['total']]);
    fclose($out); exit;
}
admin_header('Reports', 'reports');
?>
<div class="top"><h1>Sales reports</h1><a class="btn btn-ghost no-print" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv">Download CSV</a></div>
<form class="filters no-print" method="get"><div><label for="f">From</label><input id="f" type="date" name="from" value="<?= e($from) ?>"></div><div><label for="t">To</label><input id="t" type="date" name="to" value="<?= e($to) ?>"></div><button class="btn" type="submit">Update</button></form>
<div class="stats"><div class="stat"><span>Orders</span><strong><?= (int) $sum['n'] ?></strong><span><?= $cancelled ?> cancelled</span></div><div class="stat"><span>Orders value</span><strong><?= e(money($sum['booked'])) ?></strong></div><div class="stat"><span>Paid</span><strong><?= e(money($sum['paid'])) ?></strong></div><div class="stat"><span>Average order</span><strong><?= e(money($sum['aov'])) ?></strong></div><div class="stat"><span>Stock on hand</span><strong><?= number_format($stockValue['units']) ?></strong><span>worth <?= e(money($stockValue['val'])) ?> at retail</span></div></div>
<div class="grid2">
<div class="card"><h2>Top products</h2><table><thead><tr><th>Product</th><th class="num">Units</th><th class="num">Revenue</th></tr></thead><tbody><?php foreach ($top as $t): ?><tr><td><?= e($t['product_name']) ?></td><td class="num"><?= (int) $t['qty'] ?></td><td class="num"><?= e(money($t['revenue'])) ?></td></tr><?php endforeach; if (!$top): ?><tr><td colspan="3" class="hint">No sales in this period.</td></tr><?php endif; ?></tbody></table></div>
<div class="card"><h2>By category</h2><table><thead><tr><th>Category</th><th class="num">Units</th><th class="num">Revenue</th></tr></thead><tbody><?php foreach ($cat as $c): ?><tr><td><?= e($c['name']) ?></td><td class="num"><?= (int) $c['qty'] ?></td><td class="num"><?= e(money($c['revenue'])) ?></td></tr><?php endforeach; if (!$cat): ?><tr><td colspan="3" class="hint">No sales in this period.</td></tr><?php endif; ?></tbody></table>
<h2 style="margin-top:1.25rem">By payment method</h2><table><tbody><?php foreach ($method as $x): ?><tr><td><?= $x['m'] === 'mpesa' ? 'M-Pesa' : 'Cash on delivery' ?></td><td class="num"><?= (int) $x['n'] ?> orders</td><td class="num"><?= e(money($x['t'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card"><h2>Daily sales</h2><div class="wrap-x"><table><thead><tr><th>Date</th><th class="num">Orders</th><th class="num">Sales</th></tr></thead><tbody><?php foreach ($daily as $d): ?><tr><td><?= e(fmt_date($d['d'], false)) ?></td><td class="num"><?= (int) $d['n'] ?></td><td class="num"><?= e(money($d['t'])) ?></td></tr><?php endforeach; if (!$daily): ?><tr><td colspan="3" class="hint">No orders in this period.</td></tr><?php endif; ?></tbody></table></div></div>
<?php admin_footer();
