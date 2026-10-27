<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);

if (is_post()) {
    csrf_check();
    $a = post('action'); $id = (int) post('id'); $name = post('name');
    if ($a === 'save') {
        if (mb_strlen($name) < 2) flash('error', 'Enter a category name.');
        else {
            $desc = mb_substr(post('description'), 0, 255) ?: null; $sort = (int) post('sort_order'); $active = isset($_POST['is_active']) ? 1 : 0;
            $err = null; $img = save_product_image($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $err);
            if ($err) flash('error', $err);
            elseif ($id) {
                q('UPDATE categories SET name=?, description=?, sort_order=?, is_active=? WHERE id=?', [$name, $desc, $sort, $active, $id]);
                if ($img) q('UPDATE categories SET image=? WHERE id=?', [$img, $id]);
                log_action('category_updated', $name); flash('success', 'Category saved.');
            } else {
                q('INSERT INTO categories (name,slug,description,image,sort_order,is_active) VALUES (?,?,?,?,?,?)', [$name, unique_slug('categories', $name), $desc, $img, $sort, $active]);
                log_action('category_created', $name); flash('success', 'Category added.');
            }
        }
    } elseif ($a === 'delete') {
        if (fetch_value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])) flash('error', 'This category still has products. Move or deactivate them first, or hide the category instead.');
        else { q('DELETE FROM categories WHERE id = ?', [$id]); log_action('category_deleted', '#' . $id); flash('success', 'Category deleted.'); }
    }
    redirect('admin/categories.php');
}
$cats = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) n FROM categories c ORDER BY sort_order, name');
$edit = null; if (get('edit')) foreach ($cats as $c) if ($c['id'] == get('edit')) $edit = $c;
admin_header('Categories', 'categories');
?>
<div class="top"><h1>Categories</h1></div>
<div class="grid3">
<div class="card wrap-x"><table><thead><tr><th>Name</th><th>Products</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($cats as $c): ?><tr><td><strong><?= e($c['name']) ?></strong><br><span class="hint">/<?= e($c['slug']) ?></span></td><td><?= (int) $c['n'] ?></td><td><?= (int) $c['sort_order'] ?></td><td><?= $c['is_active'] ? '<span class="badge badge-ok">Visible</span>' : '<span class="badge badge-muted">Hidden</span>' ?></td>
<td style="white-space:nowrap"><a class="btn btn-sm btn-ghost" href="?edit=<?= $c['id'] ?>">Edit</a>
<form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="card"><h2><?= $edit ? 'Edit category' : 'Add category' ?></h2>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
<div class="field"><label for="n">Name</label><input id="n" name="name" type="text" value="<?= e($edit['name'] ?? '') ?>" required></div>
<div class="field"><label for="d">Short description</label><input id="d" name="description" type="text" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></div>
<div class="field"><label for="s">Display order</label><input id="s" name="sort_order" type="number" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
<div class="field"><label for="i">Collection image</label><input id="i" type="file" name="image" accept="image/jpeg,image/png,image/webp"><div class="hint">Portrait 4:5 works best. Leave empty to keep the current image.</div></div>
<div class="field"><label style="font-weight:500"><input type="checkbox" name="is_active" value="1" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>> Visible in the store</label></div>
<button class="btn" type="submit">Save category</button> <?php if ($edit): ?><a class="btn btn-ghost" href="categories.php">Cancel</a><?php endif; ?>
</form></div></div>
<?php admin_footer();
