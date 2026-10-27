<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);
$low = (int) config('low_stock_threshold');

if (is_post()) {
    csrf_check();
    $id = (int) post('id'); $stock = (int) post('stock');
    $v = fetch_one('SELECT v.*, p.name FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?', [$id]);
    if ($v && $stock >= 0 && $stock <= 100000) {
        q('UPDATE product_variants SET stock = ? WHERE id = ?', [$stock, $id]);
        log_action('stock_updated', "{$v['name']} {$v['color']}/{$v['size']}: {$v['stock']} to $stock");
        flash('success', "Stock updated for {$v['name']} ({$v['color']}, {$v['size']}).");
    } else flash('error', 'Enter a stock quantity of zero or more.');
    redirect('admin/inventory.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'low', 'page']))));
}
$qs = get('q'); $onlyLow = get('low') === '1';
$where = ['1=1']; $params = [];
if ($qs !== '') { $where[] = '(p.name LIKE ? OR v.sku LIKE ?)'; $l = '%' . addcslashes($qs, '%_\\') . '%'; array_push($params, $l, $l); }
if ($onlyLow) { $where[] = 'v.stock <= ?'; $params[] = $low; }
$W = ' WHERE ' . implode(' AND ', $where);
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id' . $W, $params), 25, (int) get('page', '1'));
$rows = fetch_all("SELECT v.*, p.name, p.status FROM product_variants v JOIN products p ON p.id = v.product_id $W ORDER BY v.stock ASC, p.name, v.id LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
admin_header('Inventory', 'inventory');
?>
<div class="top"><h1>Inventory</h1></div>
<form class="filters" method="get"><div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($qs) ?>" placeholder="Product or SKU"></div>
<div><label style="font-weight:500;margin-top:1.5rem"><input type="checkbox" name="low" value="1" <?= $onlyLow ? 'checked' : '' ?>> Low stock only (<?= $low ?> or fewer)</label></div><button class="btn" type="submit">Filter</button></form>
<div class="card wrap-x"><table><thead><tr><th>Product</th><th>Variant</th><th>SKU</th><th>Stock</th><th>Update</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
 <td><?= e($r['name']) ?><?= $r['status'] !== 'active' ? ' ' . badge($r['status'], 'status') : '' ?></td>
 <td><span class="swatch" style="background:<?= e($r['color_hex']) ?>"></span><?= e($r['color']) ?>, <?= e($r['size']) ?></td><td class="hint"><?= e($r['sku']) ?></td>
 <td class="<?= $r['stock'] == 0 ? 'low' : ($r['stock'] <= $low ? 'low' : '') ?>"><?= $r['stock'] == 0 ? 'Out of stock' : (int) $r['stock'] ?></td>
 <td><form method="post" style="display:flex;gap:.4rem"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="number" name="stock" min="0" value="<?= (int) $r['stock'] ?>" aria-label="New stock for <?= e($r['name']) ?>" style="width:90px"><button class="btn btn-sm" type="submit">Save</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="hint">Nothing to show.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
