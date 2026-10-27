<?php
/** JSON details for the "Quick add" dialog. */
require __DIR__ . '/../includes/bootstrap.php';

$p = fetch_one(catalog_base_sql() . " WHERE p.id = ? AND p.status = 'active'", [(int) get('id')]);
if (!$p) json_out(['ok' => false, 'message' => 'Product not found.'], 404);
$variants = fetch_all('SELECT id, size, color, color_hex, stock FROM product_variants WHERE product_id = ? ORDER BY id', [$p['id']]);
[$sizes, $colors] = variant_options($variants);
json_out([
    'ok' => true, 'id' => (int) $p['id'], 'name' => $p['name'], 'slug' => $p['slug'], 'price' => money($p['price']),
    'image' => image_url($p['image']), 'url' => url('product.php?slug=' . $p['slug']),
    'sizes' => $sizes, 'colors' => $colors,
    'variants' => array_map(fn($v) => ['id' => (int) $v['id'], 'size' => $v['size'], 'color' => $v['color'], 'stock' => (int) $v['stock']], $variants),
    'csrf' => csrf_token(), 'action' => url('cart.php'),
]);
