<?php
require __DIR__ . '/includes/bootstrap.php';

$catSlug = get('category'); $q = get('q'); $size = get('size'); $color = get('color');
$min = get('min') !== '' ? max(0, (float) get('min')) : null;
$max = get('max') !== '' ? max(0, (float) get('max')) : null;
$sort = get('sort', 'newest');
$sorts = ['newest' => 'Newest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'name' => 'Name A to Z'];
if (!isset($sorts[$sort])) $sort = 'newest';
$order = ['newest' => 'p.created_at DESC, p.id DESC', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'name' => 'p.name ASC'][$sort];

/** Builds a shop link that keeps the current filters but changes some of them. */
function shop_url(array $change = []): string
{
    $p = array_merge($_GET, $change); unset($p['page']);
    $p = array_filter($p, fn($v) => $v !== null && $v !== '');
    return url('shop.php') . ($p ? '?' . http_build_query($p) : '');
}

$where = ["p.status = 'active'"]; $params = [];
$currentCat = null;
if ($catSlug !== '') {
    $currentCat = fetch_one('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$catSlug]);
    $where[] = 'c.slug = ?'; $params[] = $catSlug;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($size !== '') { $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.size = ? AND v.stock > 0)'; $params[] = $size; }
if ($color !== '') { $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.color = ? AND v.stock > 0)'; $params[] = $color; }
if ($min !== null) { $where[] = 'p.price >= ?'; $params[] = $min; }
if ($max !== null) { $where[] = 'p.price <= ?'; $params[] = $max; }
$W = ' WHERE ' . implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id' . $W, $params);
$pg = paginate($total, 12, (int) get('page', '1'));
$products = fetch_all(catalog_base_sql() . $W . " ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

// filter options
$cats = fetch_all("SELECT c.name, c.slug, COUNT(p.id) AS n FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active' WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order, c.name");
$sizeOrder = ['XS','S','M','L','XL','XXL','28','30','32','34','36','38','40','One size'];
$sizes = array_column(fetch_all("SELECT DISTINCT v.size FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status = 'active'"), 'size');
usort($sizes, fn($a, $b) => (array_search($a, $sizeOrder) !== false ? array_search($a, $sizeOrder) : 99) <=> (array_search($b, $sizeOrder) !== false ? array_search($b, $sizeOrder) : 99));
$colors = fetch_all("SELECT v.color, MIN(v.color_hex) AS hex FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.status = 'active' GROUP BY v.color ORDER BY v.color");

$title = $q !== '' ? 'Search results' : ($currentCat['name'] ?? 'Shop all');
$pageTitle = $title; $activeNav = $currentCat['slug'] ?? 'shop';
$metaDesc = $currentCat['description'] ?? 'Browse the full StyleNest range of clothing and accessories.';
$hasFilters = ($q !== '' || $size !== '' || $color !== '' || $min !== null || $max !== null);
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?= e($title) ?></div>
    <h1><?= $q !== '' ? 'Results for &ldquo;' . e($q) . '&rdquo;' : e($title) ?></h1>
    <p class="muted"><?= $currentCat && $q === '' ? e($currentCat['description']) . '. ' : '' ?><?= (int) $total ?> <?= $total === 1 ? 'product' : 'products' ?></p>
  </div>

  <form class="shop-search" id="search" action="<?= e(url('shop.php')) ?>" method="get" role="search">
    <?php if ($catSlug !== ''): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
    <label class="sr" for="shop-q">Search products</label>
    <input id="shop-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, category or description">
    <button class="btn" type="submit">Search</button>
  </form>

  <div class="shop-layout">
    <aside class="filters" id="filters" aria-label="Filters">
      <h3>Collections</h3>
      <ul class="filter-list">
        <li><a href="<?= e(shop_url(['category' => null])) ?>" class="<?= $catSlug === '' ? 'on' : '' ?>">All <span class="muted"><?= array_sum(array_column($cats, 'n')) ?></span></a></li>
        <?php foreach ($cats as $c): ?>
          <li><a href="<?= e(shop_url(['category' => $c['slug']])) ?>" class="<?= $catSlug === $c['slug'] ? 'on' : '' ?>"><?= e($c['name']) ?> <span class="muted"><?= (int) $c['n'] ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <h3>Size</h3>
      <div class="chips">
        <?php foreach ($sizes as $s): ?>
          <a class="chip <?= $size === $s ? 'on' : '' ?>" href="<?= e(shop_url(['size' => $size === $s ? null : $s])) ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
      </div>
      <h3>Colour</h3>
      <div class="swatch-row">
        <?php foreach ($colors as $c): ?>
          <a class="swatch <?= $color === $c['color'] ? 'on' : '' ?>" style="background:<?= e($c['hex']) ?>" title="<?= e($c['color']) ?>" aria-label="<?= e($c['color']) ?>" href="<?= e(shop_url(['color' => $color === $c['color'] ? null : $c['color']])) ?>"></a>
        <?php endforeach; ?>
      </div>
      <h3>Price (KSh)</h3>
      <form class="price-form" method="get" action="<?= e(url('shop.php')) ?>">
        <?php foreach (['category', 'q', 'size', 'color', 'sort'] as $k) if (get($k) !== '') echo '<input type="hidden" name="' . e($k) . '" value="' . e(get($k)) . '">'; ?>
        <label class="sr" for="pmin">Minimum price</label><input id="pmin" type="number" name="min" min="0" step="100" placeholder="Min" value="<?= $min !== null ? e($min) : '' ?>">
        <span aria-hidden="true">to</span>
        <label class="sr" for="pmax">Maximum price</label><input id="pmax" type="number" name="max" min="0" step="100" placeholder="Max" value="<?= $max !== null ? e($max) : '' ?>">
        <button class="btn btn-sm" type="submit">Apply</button>
      </form>
      <?php if ($hasFilters): ?><p style="margin-top:1.25rem"><a href="<?= e(url('shop.php' . ($catSlug !== '' ? '?category=' . urlencode($catSlug) : ''))) ?>">Clear all filters</a></p><?php endif; ?>
    </aside>

    <section aria-label="Products">
      <div class="toolbar">
        <button class="btn btn-ghost btn-sm filter-toggle" type="button" data-filters aria-expanded="false" aria-controls="filters">Filters</button>
        <form method="get" action="<?= e(url('shop.php')) ?>">
          <?php foreach (['category', 'q', 'size', 'color', 'min', 'max'] as $k) if (get($k) !== '') echo '<input type="hidden" name="' . e($k) . '" value="' . e(get($k)) . '">'; ?>
          <label for="sort" class="sr">Sort by</label>
          <select id="sort" name="sort" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => $v): ?><option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm" type="submit">Sort</button></noscript>
        </form>
      </div>

      <?php if (!$products): ?>
        <div class="empty">
          <h3>Nothing matches yet</h3>
          <p class="muted">Try removing a filter or searching for something broader.</p>
          <a class="btn" href="<?= e(url('shop.php')) ?>">Show all products</a>
        </div>
      <?php else: ?>
        <div class="grid-products"><?php foreach ($products as $p) render_product_card($p); ?></div>
        <?php if ($pg['pages'] > 1): ?>
          <nav class="pager" aria-label="Pages">
            <?php if ($pg['page'] > 1): ?><a href="<?= e(page_link($pg['page'] - 1)) ?>">Previous</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
              <?php if ($i === $pg['page']): ?><span class="cur" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(page_link($i)) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e(page_link($pg['page'] + 1)) ?>">Next</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
