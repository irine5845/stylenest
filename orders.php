<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$total = (int) fetch_value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$me['id']]);
$pg = paginate($total, 15, (int) get('page', '1'));
$orders = fetch_all("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$me['id']]);
$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="padding-bottom:4rem">
  <div class="page-head"><div class="crumbs"><a href="<?= e(url('account.php')) ?>">Account</a> / Orders</div><h1>My orders</h1></div>
  <?php if (!$orders): ?><div class="empty"><p class="muted">No orders yet.</p><a class="btn" href="<?= e(url('shop.php')) ?>">Start shopping</a></div>
  <?php else: ?>
  <div class="panel"><div class="table-wrap"><table class="t"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('order.php?o=' . urlencode($o['order_number']))) ?>"><?= e($o['order_number']) ?></a></td><td><?= e(fmt_date($o['created_at'], false)) ?></td><td><?= badge($o['status']) ?></td><td><?= badge($o['payment_status'], 'payment') ?></td><td class="num"><?= e(money($o['total'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
  <?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
