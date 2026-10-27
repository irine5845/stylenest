<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
if (is_post()) {
    csrf_check();
    $id = (int) post('id'); $u = fetch_one("SELECT name, is_active FROM users WHERE id = ? AND role = 'customer'", [$id]);
    if ($u) { q('UPDATE users SET is_active = ? WHERE id = ?', [$u['is_active'] ? 0 : 1, $id]); log_action('customer_status', $u['name'] . ($u['is_active'] ? ' disabled' : ' enabled')); flash('success', 'Account ' . ($u['is_active'] ? 'disabled.' : 'enabled.')); }
    redirect('admin/customers.php');
}
$qs = get('q'); $params = []; $W = " WHERE u.role = 'customer'";
if ($qs !== '') { $W .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $l = '%' . addcslashes($qs, '%_\\') . '%'; $params = [$l, $l, $l]; }
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM users u' . $W, $params), 20, (int) get('page', '1'));
$rows = fetch_all("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) orders_n, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') spent FROM users u $W ORDER BY u.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
admin_header('Customers', 'customers');
?>
<div class="top"><h1>Customers</h1></div>
<form class="filters" method="get"><div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($qs) ?>" placeholder="Name, email or phone"></div><button class="btn" type="submit">Search</button></form>
<div class="card wrap-x"><table><thead><tr><th>Customer</th><th>Phone</th><th>Joined</th><th class="num">Orders</th><th class="num">Spent</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?><tr><td><strong><?= e($u['name']) ?></strong><br><span class="hint"><?= e($u['email']) ?></span></td><td><?= e(display_phone($u['phone'])) ?></td><td><?= e(fmt_date($u['created_at'], false)) ?></td><td class="num"><?= (int) $u['orders_n'] ?></td><td class="num"><?= e(money($u['spent'])) ?></td><td><?= $u['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-bad">Disabled</span>' ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="hint">No customers yet.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
