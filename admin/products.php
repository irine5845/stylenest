<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
$me = require_role(['admin', 'manager']);

if (is_post()) {
    csrf_check();
    $id = (int) post('id'); $p = fetch_one('SELECT name, status FROM products WHERE id = ?', [$id]);
    if ($p && post('action') === 'toggle') {
        $new = $p['status'] === 'active' ? 'unavailable' : 'active';
        q('UPDATE products SET status = ? WHERE id = ?', [$new, $id]);
        log_action('product_status', $p['name'] . ' set to ' . $new);
        flash('success', $p['name'] . ($new === 'active' ? ' is now live.' : ' was deactivated and hidden from the store.'));
    }
    redirect('admin/products.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'category', 'status', 'page']))));
}
$qs = get('q'); $cat = (int) get('category'); $st = get('status');
$where = ['1=1']; $params = [];
if ($qs !== '') { $where[] = '(p.name LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.sku LIKE ?))'; $l = '%' . addcslashes($qs, '%_\\') . '%'; array_push($params, $l, $l); }
if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
if (in_array($st, ['active', 'unavailable', 'draft'], true)) { $where[] = 'p.status = ?'; $params[] = $st; }
$W = ' WHERE ' . implode(' AND ', $where);
$pg = paginate((int) fetch_value('SELECT COUNT(*) FROM products p' . $W, $params), 15, (int) get('page', '1'));
$rows = fetch_all(catalog_base_sql() . $W . " ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$cats = fetch_all('SELECT id, name FROM categories ORDER BY sort_order, name');
admin_header('Products', 'products');
?>
<div class="top"><h1>Products</h1><a class="btn" href="<?= e(url('admin/product_form.php')) ?>">Add product</a></div>
<form class="filters" method="get">
  <div><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($qs) ?>" placeholder="Name or SKU"></div>
  <div><label for="c">Category</label><select id="c" name="category"><option value="">All</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div><label for="s">Status</label><select id="s" name="status"><option value="">All</option><?php foreach (['active', 'unavailable', 'draft'] as $s): ?><option <?= $st === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card wrap-x"><table><thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): ?><tr>
  <td><img class="thumb" src="<?= e(image_url($p['image'])) ?>" alt=""></td>
  <td><a href="<?= e(url('admin/product_form.php?id=' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a><?= $p['is_featured'] ? ' <span class="badge badge-info">Featured</span>' : '' ?><?= $p['label'] ? ' <span class="badge badge-muted">' . e($p['label']) . '</span>' : '' ?></td>
  <td><?= e($p['category_name']) ?></td><td class="num"><?= e(money($p['price'])) ?></td>
  <td class="num <?= $p['total_stock'] == 0 ? 'low' : '' ?>"><?= (int) $p['total_stock'] ?></td><td><?= badge($p['status'], 'status') ?></td>
  <td style="white-space:nowrap"><a class="btn btn-sm btn-ghost" href="<?= e(url('admin/product_form.php?id=' . $p['id'])) ?>">Edit</a>
    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></td>
</tr><?php endforeach; if (!$rows): ?><tr><td colspan="7" class="hint">No products match.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pg['pages'] > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?= $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?><?php endfor; ?></nav><?php endif; ?>
<?php admin_footer();
