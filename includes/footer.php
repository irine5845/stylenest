<?php $store = config('store'); $footCats = fetch_all('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name'); ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <a class="logo" href="<?= e(url()) ?>"><?= e(config('app_name')) ?></a>
        <p style="margin-top:.75rem;max-width:34ch">Clothes and accessories for everyday life, designed in Kenya and delivered to your door.</p>
      </div>
      <div>
        <h4>Shop</h4>
        <ul>
          <li><a href="<?= e(url('shop.php')) ?>">All products</a></li>
          <?php foreach ($footCats as $c): ?><li><a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>Customer service</h4>
        <ul>
          <li><a href="<?= e(url('track.php')) ?>">Track an order</a></li>
          <li><a href="<?= e(url('account.php')) ?>">My account</a></li>
          <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
          <li><a href="<?= e(url('about.php')) ?>">About us</a></li>
        </ul>
      </div>
      <div>
        <h4>Visit or call</h4>
        <p><?= e($store['address']) ?><br><?= e($store['hours']) ?></p>
        <p><a href="tel:<?= e(preg_replace('/\s+/', '', $store['phone'])) ?>"><?= e($store['phone']) ?></a><br><a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a><br><a href="https://wa.me/<?= e($store['whatsapp']) ?>">Chat on WhatsApp</a></p>
      </div>
    </div>
    <div class="legal">
      <span>&copy; <?= date('Y') ?> <?= e(config('app_name')) ?>. All rights reserved.</span>
      <span>We accept M-Pesa and cash on delivery. We never store your M-Pesa PIN.</span>
    </div>
  </div>
</footer>
<div class="toast-area" id="toasts" role="status" aria-live="polite"></div>
<dialog class="quick" id="quick" aria-label="Choose size and colour"></dialog>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
