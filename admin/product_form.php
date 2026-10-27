<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_role(['admin', 'manager']);

$id = (int) get('id');
$product = $id ? fetch_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) { flash('error', 'Product not found.'); redirect('admin/products.php'); }
$cats = fetch_all('SELECT id, name FROM categories ORDER BY sort_order, name');
$errors = [];
$f = $product ?: ['name' => '', 'category_id' => $cats[0]['id'] ?? 0, 'price' => '', 'compare_price' => '', 'status' => 'active', 'is_featured' => 0, 'label' => '', 'description' => '', 'details' => ''];
$images   = $id ? fetch_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$id]) : [];
$variants = $id ? fetch_all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [$id]) : [];
if (!$variants) $variants = [['id' => '', 'size' => '', 'color' => '', 'color_hex' => '#24231F', 'stock' => 0, 'sku' => '']];

if (is_post()) {
    csrf_check();
    $f = [
        'name' => post('name'), 'category_id' => (int) post('category_id'), 'price' => post('price'), 'compare_price' => post('compare_price'),
        'status' => post('status'), 'is_featured' => isset($_POST['is_featured']) ? 1 : 0, 'label' => post('label'),
        'description' => post('description'), 'details' => post('details'),
    ];
    if (mb_strlen($f['name']) < 2) $errors['name'] = 'Enter a product name.';
    if (!fetch_value('SELECT id FROM categories WHERE id = ?', [$f['category_id']])) $errors['category_id'] = 'Choose a category.';
    if (!is_numeric($f['price']) || $f['price'] <= 0) $errors['price'] = 'Enter a price greater than zero.';
    if ($f['compare_price'] !== '' && (!is_numeric($f['compare_price']) || $f['compare_price'] <= $f['price'])) $errors['compare_price'] = 'The original price must be higher than the selling price, or leave it blank.';
    if (!in_array($f['status'], ['active', 'unavailable', 'draft'], true)) $errors['status'] = 'Choose a status.';
    if (mb_strlen($f['label']) > 40) $errors['label'] = 'Keep the label under 40 characters.';

    // variants
    $vin = []; $seen = [];
    foreach (($_POST['v'] ?? []) as $row) {
        $size = trim((string) ($row['size'] ?? '')); $color = trim((string) ($row['color'] ?? ''));
        $vid = (int) ($row['id'] ?? 0); $del = !empty($row['delete']);
        if ($size === '' && $color === '' && !$vid) continue;
        if ($del) { if ($vid) $vin[] = ['id' => $vid, 'delete' => true]; continue; }
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($row['color_hex'] ?? '')) ? strtoupper($row['color_hex']) : '#24231F';
        $stock = (int) ($row['stock'] ?? 0);
        if ($size === '' || $color === '') { $errors['variants'] = 'Every variant needs a size and a colour.'; break; }
        if ($stock < 0) { $errors['variants'] = 'Stock cannot be negative.'; break; }
        $key = strtolower("$size|$color");
        if (isset($seen[$key])) { $errors['variants'] = "Duplicate variant: $color, $size."; break; }
        $seen[$key] = 1;
        $vin[] = ['id' => $vid, 'size' => mb_substr($size, 0, 20), 'color' => mb_substr($color, 0, 40), 'hex' => $hex, 'stock' => $stock, 'sku' => trim((string) ($row['sku'] ?? ''))];
    }
    $liveVariants = array_filter($vin, fn($v) => empty($v['delete']));
    if (!$errors && $f['status'] === 'active' && !$liveVariants) $errors['variants'] = 'Add at least one size and colour before making a product active.';

    $newImages = [];
    if (!$errors && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $i => $_) {
            $file = ['name' => $_FILES['images']['name'][$i], 'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i], 'size' => $_FILES['images']['size'][$i]];
            $err = null; $path = save_product_image($file, $err);
            if ($err) { $errors['images'] = $err; break; }
            if ($path) $newImages[] = $path;
        }
        if ($errors) foreach ($newImages as $n) delete_product_image_file($n);
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $cmp = $f['compare_price'] === '' ? null : $f['compare_price'];
            if ($product) {
                $slug = $product['slug'];
                q('UPDATE products SET category_id=?, name=?, description=?, details=?, price=?, compare_price=?, status=?, is_featured=?, label=? WHERE id=?',
                  [$f['category_id'], $f['name'], $f['description'], $f['details'], $f['price'], $cmp, $f['status'], $f['is_featured'], $f['label'] ?: null, $id]);
            } else {
                $slug = unique_slug('products', $f['name']);
                q('INSERT INTO products (category_id,name,slug,description,details,price,compare_price,status,is_featured,label) VALUES (?,?,?,?,?,?,?,?,?,?)',
                  [$f['category_id'], $f['name'], $slug, $f['description'], $f['details'], $f['price'], $cmp, $f['status'], $f['is_featured'], $f['label'] ?: null]);
                $id = insert_id();
            }
            foreach ($vin as $v) {
                if (!empty($v['delete'])) { q('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$v['id'], $id]); continue; }
                if ($v['id']) {
                    q('UPDATE product_variants SET size=?, color=?, color_hex=?, stock=? WHERE id=? AND product_id=?', [$v['size'], $v['color'], $v['hex'], $v['stock'], $v['id'], $id]);
                } else {
                    $sku = $v['sku'] ?: sprintf('SN-%03d-%s-%s', $id, strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v['size'])), strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $v['color']), 0, 3)));
                    $base = $sku; $n = 2; while (fetch_value('SELECT id FROM product_variants WHERE sku = ?', [$sku])) $sku = $base . '-' . $n++;
                    q('INSERT INTO product_variants (product_id,sku,size,color,color_hex,stock) VALUES (?,?,?,?,?,?)', [$id, $sku, $v['size'], $v['color'], $v['hex'], $v['stock']]);
                }
            }
            // existing images: delete / alt text
            foreach (($_POST['img'] ?? []) as $iid => $row) {
                $iid = (int) $iid; $img = fetch_one('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$iid, $id]); if (!$img) continue;
                if (!empty($row['delete'])) { q('DELETE FROM product_images WHERE id = ?', [$iid]); delete_product_image_file($img['image_path']); }
                else q('UPDATE product_images SET alt_text = ? WHERE id = ?', [mb_substr(trim((string) ($row['alt'] ?? '')), 0, 190) ?: null, $iid]);
            }
            $next = (int) fetch_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_images WHERE product_id = ?', [$id]);
            foreach ($newImages as $path) q('INSERT INTO product_images (product_id,image_path,alt_text,sort_order) VALUES (?,?,?,?)', [$id, $path, $f['name'], $next++]);
            $pdo->commit();
            log_action($product ? 'product_updated' : 'product_created', $f['name']);
            flash('success', $product ? 'Product saved.' : 'Product created. You can add more photos below.');
            redirect('admin/product_form.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($newImages as $n) delete_product_image_file($n);
            error_log('product save: ' . $e->getMessage());
            $errors['name'] = 'Could not save the product. Check for duplicate SKUs or sizes.';
        }
    }
    // re-show submitted variants on error
    $variants = array_map(fn($r) => ['id' => $r['id'] ?? '', 'size' => $r['size'] ?? '', 'color' => $r['color'] ?? '', 'color_hex' => $r['color_hex'] ?? '#24231F', 'stock' => $r['stock'] ?? 0, 'sku' => $r['sku'] ?? ''], $_POST['v'] ?? []) ?: $variants;
}
function pe(array $e, string $k): string { return isset($e[$k]) ? '<div class="err" role="alert">' . e($e[$k]) . '</div>' : ''; }
admin_header($product ? 'Edit product' : 'Add product', 'products');
?>
<div class="top"><h1><?= $product ? 'Edit ' . e($product['name']) : 'Add product' ?></h1>
  <div><?php if ($product): ?><a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= e(url('product.php?slug=' . $product['slug'])) ?>">View in store</a> <?php endif; ?><a class="btn btn-ghost" href="<?= e(url('admin/products.php')) ?>">Back to products</a></div></div>
<?php if ($errors): ?><div class="alert alert-error" role="alert">Please fix the problems below.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
<div class="grid3">
 <div>
  <div class="card"><h2>Details</h2>
    <div class="field"><label for="name">Product name</label><input id="name" name="name" type="text" value="<?= e($f['name']) ?>" required><?= pe($errors, 'name') ?></div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description"><?= e($f['description']) ?></textarea></div>
    <div class="field"><label for="details">Fabric and care</label><textarea id="details" name="details" style="min-height:70px"><?= e($f['details']) ?></textarea></div>
  </div>
  <div class="card"><h2>Sizes, colours and stock</h2>
    <p class="hint">Each row is one size and colour with its own stock. Tick Remove to delete a row. Leave SKU blank to generate one.</p>
    <?= pe($errors, 'variants') ?>
    <div class="wrap-x"><table id="vt"><thead><tr><th>Size</th><th>Colour</th><th>Swatch</th><th>Stock</th><th>SKU</th><th>Remove</th></tr></thead><tbody>
    <?php foreach ($variants as $i => $v): ?><tr>
      <td><input type="hidden" name="v[<?= $i ?>][id]" value="<?= e($v['id']) ?>"><input type="text" name="v[<?= $i ?>][size]" value="<?= e($v['size']) ?>" aria-label="Size" style="min-width:80px"></td>
      <td><input type="text" name="v[<?= $i ?>][color]" value="<?= e($v['color']) ?>" aria-label="Colour" style="min-width:110px"></td>
      <td><input type="color" name="v[<?= $i ?>][color_hex]" value="<?= e($v['color_hex']) ?>" aria-label="Swatch colour" style="width:48px;height:36px;padding:2px"></td>
      <td><input type="number" min="0" name="v[<?= $i ?>][stock]" value="<?= (int) $v['stock'] ?>" aria-label="Stock" style="width:85px"></td>
      <td><?php if ($v['id']): ?><span class="hint"><?= e($v['sku']) ?></span><input type="hidden" name="v[<?= $i ?>][sku]" value="<?= e($v['sku']) ?>"><?php else: ?><input type="text" name="v[<?= $i ?>][sku]" value="<?= e($v['sku']) ?>" aria-label="SKU" placeholder="auto" style="width:130px"><?php endif; ?></td>
      <td><input type="checkbox" name="v[<?= $i ?>][delete]" value="1" aria-label="Remove this variant"></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <p style="margin:.75rem 0 0"><button class="btn btn-sm btn-ghost" type="button" id="add-row">Add another row</button></p>
  </div>
  <div class="card"><h2>Photos</h2>
    <?php if ($images): ?><div class="imgs"><?php foreach ($images as $img): ?><figure><img src="<?= e(image_url($img['image_path'])) ?>" alt=""><input type="text" name="img[<?= $img['id'] ?>][alt]" value="<?= e($img['alt_text']) ?>" placeholder="Describe the photo" aria-label="Photo description"><label style="font-weight:400"><input type="checkbox" name="img[<?= $img['id'] ?>][delete]" value="1"> Remove</label></figure><?php endforeach; ?></div><?php endif; ?>
    <div class="field" style="margin-top:1rem"><label for="images">Add photos (JPG, PNG or WebP, up to 4 MB each)</label><input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><?= pe($errors, 'images') ?><div class="hint">The first photo is used as the main image. Upload portrait photos (4:5) for the best fit.</div></div>
  </div>
 </div>
 <div>
  <div class="card"><h2>Pricing and status</h2>
    <div class="field"><label for="price">Selling price (KSh)</label><input id="price" name="price" type="number" min="1" step="1" value="<?= e($f['price']) ?>" required><?= pe($errors, 'price') ?></div>
    <div class="field"><label for="compare">Original price (KSh, optional)</label><input id="compare" name="compare_price" type="number" min="1" step="1" value="<?= e($f['compare_price']) ?>"><div class="hint">Shown crossed out to mark a sale.</div><?= pe($errors, 'compare_price') ?></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category_id"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $f['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?= pe($errors, 'category_id') ?></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach (['active' => 'Active (visible in store)', 'unavailable' => 'Unavailable (hidden)', 'draft' => 'Draft (hidden)'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="label">Promotional label</label><input id="label" name="label" type="text" maxlength="40" value="<?= e($f['label']) ?>" placeholder="New, Sale, Bestseller"><?= pe($errors, 'label') ?></div>
    <div class="field"><label style="font-weight:500"><input type="checkbox" name="is_featured" value="1" <?= $f['is_featured'] ? 'checked' : '' ?>> Feature on the homepage</label></div>
    <button class="btn" type="submit">Save product</button>
  </div>
 </div>
</div>
</form>
<script>
document.getElementById('add-row').addEventListener('click', function () {
  var tb = document.querySelector('#vt tbody'), i = tb.rows.length + 100, tr = document.createElement('tr');
  tr.innerHTML = '<td><input type="hidden" name="v['+i+'][id]" value=""><input type="text" name="v['+i+'][size]" aria-label="Size" style="min-width:80px"></td>' +
   '<td><input type="text" name="v['+i+'][color]" aria-label="Colour" style="min-width:110px"></td>' +
   '<td><input type="color" name="v['+i+'][color_hex]" value="#24231F" aria-label="Swatch colour" style="width:48px;height:36px;padding:2px"></td>' +
   '<td><input type="number" min="0" name="v['+i+'][stock]" value="0" aria-label="Stock" style="width:85px"></td>' +
   '<td><input type="text" name="v['+i+'][sku]" placeholder="auto" aria-label="SKU" style="width:130px"></td><td></td>';
  tb.appendChild(tr);
});
</script>
<?php admin_footer();
