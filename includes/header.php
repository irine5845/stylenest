<?php
/** Storefront header. Set $pageTitle, $metaDesc and $activeNav before including. */
$store = config('store');
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . config('app_name') : config('app_name') . ' | Modern fashion, delivered across Kenya';
$metaDesc  = $metaDesc ?? 'Shop dresses, shirts, trousers and accessories from StyleNest. Pay with M-Pesa and get delivery across Kenya.';
$activeNav = $activeNav ?? '';
$bagCount  = cart_count();
$me        = current_user();
$navCats   = fetch_all('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#F8F6F1">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script>window.SN = {base: <?= json_encode(base_url()) ?>, csrf: <?= json_encode(csrf_token()) ?>};</script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="announce">New arrivals are here. Free delivery on orders over <?= e(money(config('delivery')['free_over'])) ?>.</div>
<header class="site-header">
  <div class="wrap bar">
    <a class="logo" href="<?= e(url()) ?>" aria-label="<?= e(config('app_name')) ?> home">
      <svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M4 28V15a12 12 0 0 1 24 0v13" stroke="#24231F" stroke-width="2.2" stroke-linecap="round"/><path d="M10 28V16a6 6 0 0 1 12 0v12" stroke="#B78B68" stroke-width="2.2" stroke-linecap="round"/></svg>
      <?= e(config('app_name')) ?>
    </a>
    <nav class="nav" id="site-nav" aria-label="Main">
      <a href="<?= e(url('shop.php')) ?>" <?= $activeNav === 'shop' ? 'aria-current="page"' : '' ?>>Shop all</a>
      <?php foreach ($navCats as $c): ?>
        <a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>" <?= $activeNav === $c['slug'] ? 'aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('about.php')) ?>" <?= $activeNav === 'about' ? 'aria-current="page"' : '' ?>>About</a>
      <a href="<?= e(url('contact.php')) ?>" <?= $activeNav === 'contact' ? 'aria-current="page"' : '' ?>>Contact</a>
    </nav>
    <div class="tools">
      <form class="search" action="<?= e(url('shop.php')) ?>" method="get" role="search">
        <label class="sr" for="hdr-q">Search products</label>
        <input id="hdr-q" type="search" name="q" placeholder="Search" value="<?= e(get('q')) ?>" autocomplete="off">
        <button class="icon-btn" type="submit" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></button>
      </form>
      <a class="icon-btn search-link" href="<?= e(url('shop.php#search')) ?>" aria-label="Search products"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></a>
      <?php if ($me): ?>
        <a class="icon-btn" href="<?= e(url($me['role'] === 'customer' ? 'account.php' : 'admin/')) ?>" aria-label="<?= $me['role'] === 'customer' ? 'My account' : 'Admin dashboard' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.500 2 7.500 6"/></svg></a>
      <?php else: ?>
        <a class="icon-btn" href="<?= e(url('login.php')) ?>" aria-label="Sign in"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.500 2 7.500 6"/></svg></a>
      <?php endif; ?>
      <a class="icon-btn" href="<?= e(url('cart.php')) ?>" aria-label="Shopping bag, <?= (int) $bagCount ?> items">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6.500a3 3 0 0 1 6 0V8"/></svg>
        <span class="bag-count" data-bag-count <?= $bagCount ? '' : 'hidden' ?>><?= (int) $bagCount ?></span>
      </a>
      <button class="icon-btn menu-btn" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu" data-menu>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
</header>
<main id="main">
<?php foreach (flash_take() as $f): ?>
  <div class="wrap" style="padding-top:1.25rem"><div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
