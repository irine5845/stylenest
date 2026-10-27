<?php
/** Product listing helpers shared by the storefront pages. */

function catalog_base_sql(): string
{
    return "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
              (SELECT image_path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image,
              (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) AS total_stock
            FROM products p JOIN categories c ON c.id = p.category_id";
}

function render_product_card(array $p): void
{
    $out  = ((int) $p['total_stock'] <= 0);
    $sale = $p['compare_price'] && $p['compare_price'] > $p['price'];
    $href = url('product.php?slug=' . urlencode($p['slug']));
    ?>
    <article class="card">
      <?php if ($out): ?><span class="tag out">Sold out</span>
      <?php elseif ($p['label']): ?><span class="tag <?= $sale ? 'sale' : '' ?>"><?= e($p['label']) ?></span><?php endif; ?>
      <a class="card-img" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(image_url($p['image'])) ?>" alt="" loading="lazy" width="400" height="500">
      </a>
      <div class="card-body">
        <a class="card-title" href="<?= e($href) ?>"><?= e($p['name']) ?></a>
        <span class="card-cat"><?= e($p['category_name']) ?></span>
        <span class="price"><?= e(money($p['price'])) ?><?php if ($sale): ?><s><?= e(money($p['compare_price'])) ?></s><?php endif; ?></span>
        <div class="card-actions">
          <?php if ($out): ?>
            <a class="btn btn-ghost btn-sm btn-block" href="<?= e($href) ?>">View details</a>
          <?php else: ?>
            <button class="btn btn-ghost btn-sm btn-block" type="button" data-quick="<?= (int) $p['id'] ?>">Quick add</button>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php
}

/** Renders the newsletter sign-up band. */
function render_newsletter(): void
{ ?>
<section class="newsletter" aria-labelledby="nl-h">
  <div class="wrap inner">
    <div>
      <h2 id="nl-h">Be first to see new arrivals</h2>
      <p>One short email when new pieces land. Unsubscribe whenever you like.</p>
    </div>
    <form action="<?= e(url('newsletter.php')) ?>" method="post">
      <?= csrf_field() ?>
      <div class="nl-row">
        <label class="sr" for="nl-email">Email address</label>
        <input id="nl-email" type="email" name="email" placeholder="you@example.com" required autocomplete="email">
        <button class="btn" type="submit">Subscribe</button>
      </div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> I agree to receive emails from <?= e(config('app_name')) ?>.</label>
    </form>
  </div>
</section>
<?php }

/** Distinct sizes (in sensible order) and colours for a list of variants. */
function variant_options(array $variants): array
{
    $order = ['XS','S','M','L','XL','XXL','28','30','32','34','36','38','40','One size'];
    $sizes = []; $colors = [];
    foreach ($variants as $v) { $sizes[$v['size']] = true; $colors[$v['color']] = $v['color_hex']; }
    $sizes = array_keys($sizes);
    usort($sizes, function ($a, $b) use ($order) {
        $ia = array_search($a, $order); $ib = array_search($b, $order);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    return [$sizes, $colors];
}

/** Server-rendered size/colour picker + add-to-bag form. Works without JavaScript; app.js adds live availability. */
function render_picker(array $product, array $variants, string $idp = 'pp'): void
{
    [$sizes, $colors] = variant_options($variants);
    $json = array_map(fn($v) => ['id' => (int) $v['id'], 'size' => $v['size'], 'color' => $v['color'], 'stock' => (int) $v['stock']], $variants);
    $inStock = array_sum(array_column($variants, 'stock')) > 0;
    ?>
    <form class="buy-form" method="post" action="<?= e(url('cart.php')) ?>" data-picker data-variants="<?= e(json_encode($json)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
      <?php if (count($colors) > 1 || true): ?>
      <fieldset class="opt" style="border:0;padding:0;margin:1.4rem 0">
        <legend class="label">Colour <span data-color-name></span></legend>
        <div class="colors">
          <?php foreach ($colors as $name => $hex): ?>
            <label class="color-opt" title="<?= e($name) ?>"><input type="radio" name="color" value="<?= e($name) ?>" required <?= count($colors) === 1 ? 'checked' : '' ?>><span style="background:<?= e($hex) ?>"></span><span class="sr"><?= e($name) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?php endif; ?>
      <fieldset class="opt" style="border:0;padding:0;margin:1.4rem 0">
        <legend class="label">Size</legend>
        <div class="sizes">
          <?php foreach ($sizes as $s): ?>
            <label class="size-opt"><input type="radio" name="size" value="<?= e($s) ?>" required <?= count($sizes) === 1 ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="stock" data-stock aria-live="polite"></div>
      <div class="buy-row">
        <div class="qty">
          <button type="button" data-qty="-1" aria-label="Decrease quantity">&minus;</button>
          <input type="number" name="qty" value="1" min="1" max="10" aria-label="Quantity">
          <button type="button" data-qty="1" aria-label="Increase quantity">+</button>
        </div>
        <button class="btn" type="submit" <?= $inStock ? '' : 'disabled' ?>><?= $inStock ? 'Add to bag' : 'Sold out' ?></button>
      </div>
    </form>
    <?php
}
