<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    $action = post('action');
    $result = ['ok' => true, 'message' => ''];

    if ($action === 'add') {
        $vid = (int) post('variant_id');
        if (!$vid) {
            $v = fetch_one('SELECT id FROM product_variants WHERE product_id = ? AND size = ? AND color = ?', [(int) post('product_id'), post('size'), post('color')]);
            $vid = $v ? (int) $v['id'] : 0;
        }
        $result = $vid ? cart_add($vid, max(1, min(10, (int) post('qty', '1')))) : ['ok' => false, 'message' => 'Please choose a size and colour.'];
    } elseif ($action === 'update') {
        cart_set((int) post('variant_id'), (int) post('qty'));
        $result['message'] = 'Bag updated.';
    } elseif ($action === 'remove') {
        cart_remove((int) post('variant_id'));
        $result['message'] = 'Item removed.';
    }

    if (is_ajax()) {
        json_out(['ok' => $result['ok'], 'message' => $result['message'], 'count' => cart_count(), 'cartUrl' => url('cart.php')], $result['ok'] ? 200 : 422);
    }
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($action === 'add' && $result['ok'] ? 'cart.php' : ($action === 'add' ? ($_SERVER['HTTP_REFERER'] ?? 'shop.php') : 'cart.php'));
}

$cart = cart_detail();
$free = config('delivery')['free_over'];
$pageTitle = 'Your bag';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="page-head"><h1>Your bag</h1></div>
  <?php foreach ($cart['notices'] as $n): ?><div class="alert alert-warn"><?= e($n) ?></div><?php endforeach; ?>

  <?php if (!$cart['items']): ?>
    <div class="empty" style="margin-bottom:4rem">
      <h2>Your bag is empty</h2>
      <p class="muted">Find something you love and it will show up here.</p>
      <a class="btn" href="<?= e(url('shop.php')) ?>">Start shopping</a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <section aria-label="Items in your bag">
      <?php foreach ($cart['items'] as $it): ?>
        <div class="line">
          <a href="<?= e(url('product.php?slug=' . $it['slug'])) ?>"><img src="<?= e(image_url($it['image'])) ?>" alt="" width="96" height="120"></a>
          <div>
            <h3><a href="<?= e(url('product.php?slug=' . $it['slug'])) ?>"><?= e($it['name']) ?></a></h3>
            <div class="meta"><span class="swatch" style="background:<?= e($it['color_hex']) ?>"></span><?= e($it['color']) ?> / Size <?= e($it['size']) ?></div>
            <div class="meta"><?= e(money($it['price'])) ?> each<?php if ($it['stock'] <= 3): ?> <span style="color:var(--warn)">Only <?= (int) $it['stock'] ?> left</span><?php endif; ?></div>
            <form method="post" action="<?= e(url('cart.php')) ?>" style="margin-top:.7rem" data-cart-line>
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>">
              <div class="qty">
                <button type="button" data-qty="-1" aria-label="Decrease quantity of <?= e($it['name']) ?>">&minus;</button>
                <input type="number" name="qty" value="<?= (int) $it['quantity'] ?>" min="0" max="<?= (int) min($it['stock'], MAX_PER_LINE) ?>" aria-label="Quantity of <?= e($it['name']) ?>">
                <button type="button" data-qty="1" aria-label="Increase quantity of <?= e($it['name']) ?>">+</button>
              </div>
              <noscript><button class="btn btn-sm" type="submit">Update</button></noscript>
            </form>
          </div>
          <div class="line-side">
            <strong><?= e(money($it['line_total'])) ?></strong>
            <form method="post" action="<?= e(url('cart.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>"><button class="link-btn" type="submit">Remove</button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <p style="margin-top:1.25rem"><a href="<?= e(url('shop.php')) ?>">Continue shopping</a></p>
    </section>

    <aside class="summary" aria-label="Order summary">
      <h2>Summary</h2>
      <div class="sum-row"><span>Subtotal (<?= (int) $cart['count'] ?> <?= $cart['count'] === 1 ? 'item' : 'items' ?>)</span><strong><?= e(money($cart['subtotal'])) ?></strong></div>
      <div class="sum-row"><span>Delivery</span><span class="muted">Calculated at checkout</span></div>
      <?php if ($free > 0): ?>
        <p class="muted" style="font-size:.88rem;margin:.6rem 0 0">
          <?= $cart['subtotal'] >= $free ? 'You have unlocked free delivery.' : 'Add ' . e(money($free - $cart['subtotal'])) . ' more for free delivery.' ?>
        </p>
      <?php endif; ?>
      <a class="btn btn-block" style="margin-top:1.25rem" href="<?= e(url('checkout.php')) ?>">Go to checkout</a>
    </aside>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
