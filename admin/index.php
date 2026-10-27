<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
$low = (int) config('low_stock_threshold');

$today   = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'");
$month   = fetch_one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN payment_status='paid' THEN total END),0) paid, COALESCE(SUM(total),0) booked FROM orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND status <> 'cancelled'");
$pending = (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')");
$lowCount = (int) fetch_value("SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.status='active' AND v.stock <= ?", [$low]);
$days = fetch_all("SELECT DATE(created_at) d, SUM(total) t FROM orders WHERE created_at >= (CURDATE() - INTERVAL 6 DAY) AND status <> 'cancelled' GROUP BY DATE(created_at)");
$byDay = array_column($days, 't', 'd'); $series = []; $maxv = 1;
for ($i = 6; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $series[$d] = (float) ($byDay[$d] ?? 0); $maxv = max($maxv, $series[$d]); }
$recent = fetch_all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
$lowItems = fetch_all("SELECT v.id, v.size, v.color, v.stock, p.name FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.status='active' AND v.stock <= ? ORDER BY v.stock, p.name LIMIT 8", [$low]);

admin_header('Dashboard', 'index');
?>
<div class="top"><h1>Dashboard</h1><a class="btn" href="<?= e(url('admin/product_form.php')) ?>">Add product</a></div>
<div class="stats">
  <div class="stat"><span>Orders today</span><strong><?= (int) $today['n'] ?></strong><span><?= e(money($today['t'])) ?></span></div>
  <div class="stat"><span>Paid this month</span><strong><?= e(money($month['paid'])) ?></strong><span><?= (int) $month['n'] ?> orders, <?= e(money($month['booked'])) ?> booked</span></div>
  <div class="stat"><span>Orders to fulfil</span><strong><?= $pending ?></strong><span><a href="<?= e(url('admin/orders.php?status=pending')) ?>">View pending</a></span></div>
  <div class="stat"><span>Low or out of stock</span><strong class="<?= $lowCount ? 'low' : '' ?>"><?= $lowCount ?></strong><span><a href="<?= e(url('admin/inventory.php?low=1')) ?>">View inventory</a></span></div>
</div>
<div class="grid3">
  <div>
    <div class="card"><h2>Sales, last 7 days</h2>
      <div class="bars" role="img" aria-label="Daily sales for the last seven days">
        <?php foreach ($series as $d => $t): ?><div title="<?= e(money($t)) ?>"><span><?= $t > 0 ? e(number_format($t / 1000, 1)) . 'k' : '' ?></span><i style="height:<?= max(1, round($t / $maxv * 100)) ?>%"></i><span><?= e(date('D', strtotime($d))) ?></span></div><?php endforeach; ?>
      </div></div>
    <div class="card"><h2>Recent orders</h2><div class="wrap-x"><table><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($recent as $o): ?><tr><td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['order_number']) ?></a></td><td><?= e($o['customer_name']) ?></td><td><?= badge($o['status']) ?></td><td><?= badge($o['payment_status'], 'payment') ?></td><td class="num"><?= e(money($o['total'])) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="5" class="hint">No orders yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <div class="card"><h2>Stock alerts</h2>
    <?php if (!$lowItems): ?><p class="hint">Everything is well stocked.</p><?php else: ?>
    <table><tbody><?php foreach ($lowItems as $i): ?><tr><td><?= e($i['name']) ?><br><span class="hint"><?= e($i['color']) ?>, <?= e($i['size']) ?></span></td><td class="num <?= $i['stock'] == 0 ? 'low' : '' ?>"><?= $i['stock'] == 0 ? 'Out' : (int) $i['stock'] . ' left' ?></td></tr><?php endforeach; ?></tbody></table>
    <p style="margin-bottom:0"><a href="<?= e(url('admin/inventory.php?low=1')) ?>">Manage stock</a></p><?php endif; ?></div>
</div>
<?php admin_footer();
