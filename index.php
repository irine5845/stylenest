<?php
require __DIR__ . '/includes/bootstrap.php';
$activeNav = 'home';
$cats = fetch_all('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
$featured = fetch_all(catalog_base_sql() . " WHERE p.status = 'active' ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 8");
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <h1>Clothes made for the way you live.</h1>
      <p class="lead">Dresses, shirts, trousers and accessories in natural fabrics. Pay with M-Pesa and get delivery anywhere in Kenya.</p>
      <div class="hero-actions">
        <a class="btn" href="<?= e(url('shop.php?sort=newest')) ?>">Shop new arrivals</a>
        <a class="btn btn-ghost" href="#collections">Browse collections</a>
      </div>
      <p class="hero-note">Every piece is listed with real stock by size and colour, so what you see is what we can send.</p>
    </div>
    <div class="arch"><img src="<?= e(asset('img/hero.svg')) ?>" alt="A sand-coloured dress and a black tote bag" width="800" height="1000"></div>
  </div>
</section>

<section class="benefits" aria-label="Why shop with us">
  <div class="wrap">
    <ul>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7.500" cy="17.500" r="1.800"/><circle cx="17" cy="17.500" r="1.800"/></svg><div><strong>Delivery across Kenya</strong><span>Rates shown before you pay</span></div></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2.500" width="10" height="19" rx="2"/><path d="M11 18.500h2"/></svg><div><strong>Pay with M-Pesa</strong><span>Approve on your phone</span></div></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 0 1 14-5.300M20 12a8 8 0 0 1-14 5.300M18 3v4h-4M6 21v-4h4"/></svg><div><strong>Cash on delivery</strong><span>Pay when it arrives</span></div></li>
      <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.500 3 8 7 10 4-2 7-5.500 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg><div><strong>Secure checkout</strong><span>Your details stay private</span></div></li>
    </ul>
  </div>
</section>

<section class="section" id="collections">
  <div class="wrap">
    <div class="section-head"><h2>Shop by collection</h2><a href="<?= e(url('shop.php')) ?>">See everything</a></div>
    <div class="collections">
      <?php foreach ($cats as $c): ?>
        <a class="collection" href="<?= e(url('shop.php?category=' . $c['slug'])) ?>">
          <div class="arch"><img src="<?= e(image_url($c['image'])) ?>" alt="" loading="lazy" width="800" height="1000"></div>
          <h3><?= e($c['name']) ?></h3>
          <p><?= e($c['description']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="wrap">
    <div class="section-head"><h2>Featured pieces</h2><a href="<?= e(url('shop.php?sort=newest')) ?>">Shop new arrivals</a></div>
    <?php if ($featured): ?>
      <div class="grid-products"><?php foreach ($featured as $p) render_product_card($p); ?></div>
    <?php else: ?>
      <div class="empty"><p>No products yet. Add some from the admin dashboard.</p></div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap story">
    <div class="arch"><img src="<?= e(asset('img/about.svg')) ?>" alt="A cream blouse and stone trousers" loading="lazy" width="800" height="1000"></div>
    <div>
      <h2>Simple clothes, chosen with care</h2>
      <p>We keep our range small on purpose. Each piece is picked for fabric, fit and how well it works with the rest of your wardrobe.</p>
      <p>Shopping should be easy too. You can see what is in stock, pay the way you prefer, and follow your order from our shelf to your door.</p>
      <a class="btn btn-ghost" href="<?= e(url('about.php')) ?>">Read our story</a>
    </div>
  </div>
</section>
<?php render_newsletter(); require __DIR__ . '/includes/footer.php'; ?>
