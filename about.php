<?php
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'About us'; $activeNav = 'about';
$metaDesc = 'The story and values behind StyleNest, a modern Kenyan fashion boutique.';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap story">
    <div class="arch"><img src="<?= e(asset('img/about.svg')) ?>" alt="A cream blouse and stone trousers" width="800" height="1000"></div>
    <div>
      <h1 style="font-size:clamp(2.4rem,5vw,4rem)">A calmer way to dress</h1>
      <p><?= e(config('app_name')) ?> began with a simple idea: good clothes should be easy to find, easy to buy and easy to live in.</p>
      <p>We choose natural, breathable fabrics and cuts that work across seasons. We keep our range focused, so every piece earns its place in your wardrobe.</p>
      <p>Whether you shop with us online or visit the boutique, you will always see honest prices in Kenyan shillings and real stock for every size and colour.</p>
      <a class="btn" href="<?= e(url('shop.php')) ?>">Shop the collection</a>
    </div>
  </div>
</section>
<section class="section alt">
  <div class="wrap">
    <h2>What we care about</h2>
    <div class="values">
      <div><h3>Fabric first</h3><p>Cotton, linen and blends that feel good in Kenya's weather and last through many washes.</p></div>
      <div><h3>Clear and honest</h3><p>Prices, delivery charges and stock levels are shown before you pay. No surprises at checkout.</p></div>
      <div><h3>Service you can reach</h3><p>Call, message or visit us. A real person will help with sizing, orders and exchanges.</p></div>
    </div>
  </div>
</section>
<?php render_newsletter(); require __DIR__ . '/includes/footer.php'; ?>
