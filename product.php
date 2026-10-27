<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');
$p = fetch_one(catalog_base_sql() . ' WHERE p.slug = ?', [$slug]);
if (!$p || ($p['status'] !== 'active' && !is_staff())) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap"><div class="empty" style="margin:3rem 0"><h1>We could not find that product</h1><p class="muted">It may have been removed or the link is out of date.</p><a class="btn" href="' . e(url('shop.php')) . '">Browse the shop</a></div></div>';
    require __DIR__ . '/includes/footer.php'; exit;
}
$images   = fetch_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$p['id']]);
$variants = fetch_all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY id', [$p['id']]);
$related  = fetch_all(catalog_base_sql() . " WHERE p.status = 'active' AND p.category_id = ? AND p.id <> ? ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 4", [$p['category_id'], $p['id']]);
$sale = $p['compare_price'] && $p['compare_price'] > $p['price'];

$pageTitle = $p['name']; $activeNav = $p['category_slug'];
$metaDesc = mb_substr((string) $p['description'], 0, 155);
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="page-head" style="padding-bottom:1rem">
    <div class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('shop.php?category=' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a> / <?= e($p['name']) ?></div>
  </div>
  <?php if ($p['status'] !== 'active'): ?><div class="alert alert-warn">This product is <?= e($p['status']) ?> and only visible to staff.</div><?php endif; ?>
  <div class="product">
    <div class="gallery">
      <?php if (count($images) > 1): ?>
        <div class="thumbs" role="group" aria-label="Product images">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" data-thumb="<?= e(image_url($img['image_path'])) ?>" data-alt="<?= e($img['alt_text'] ?: $p['name']) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Show image <?= $i + 1 ?>"><img src="<?= e(image_url($img['image_path'])) ?>" alt="" loading="lazy"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="main-img"><img id="main-img" src="<?= e(image_url($images[0]['image_path'] ?? null)) ?>" alt="<?= e($images[0]['alt_text'] ?? $p['name']) ?>" width="800" height="1000"></div>
    </div>

    <div class="pinfo">
      <h1><?= e($p['name']) ?></h1>
      <div class="price"><?= e(money($p['price'])) ?><?php if ($sale): ?><s><?= e(money($p['compare_price'])) ?></s><?php endif; ?></div>
      <p><?= e($p['description']) ?></p>
      <?php if ($p['status'] === 'active'): render_picker($p, $variants); else: ?>
        <p class="alert alert-info">Not available to order.</p>
      <?php endif; ?>
      <div class="accordion">
        <details open><summary>Fabric and care</summary><p><?= e($p['details'] ?: 'Details coming soon.') ?></p></details>
        <details><summary>Delivery</summary><p>We deliver across Kenya. You will see the exact delivery charge at checkout before you pay. Orders over <?= e(money(config('delivery')['free_over'])) ?> ship free.</p></details>
        <details><summary>Payment</summary><p>Pay with M-Pesa (you approve the payment on your phone) or choose cash on delivery.</p></details>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="section" style="padding-top:1rem">
      <div class="section-head"><h2>You may also like</h2></div>
      <div class="grid-products"><?php foreach ($related as $r) render_product_card($r); ?></div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
