<?php
/**
 * Shopping cart. The session holds [variant_id => quantity]; for signed-in customers
 * the cart is mirrored into the carts / cart_items tables so it follows them across devices.
 * Prices and stock are always re-read from the database, never trusted from the browser.
 */
const MAX_PER_LINE = 10;

function cart_get(): array { return $_SESSION['cart'] ?? []; }
function cart_count(): int { return array_sum(cart_get()); }

function cart_add(int $variantId, int $qty): array
{
    $v = fetch_one('SELECT v.id, v.stock, p.status FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?', [$variantId]);
    if (!$v || $v['status'] !== 'active') return ['ok' => false, 'message' => 'That item is no longer available.'];
    if ($v['stock'] < 1)                  return ['ok' => false, 'message' => 'Sorry, that size and colour is sold out.'];

    $cart = cart_get();
    $new  = ($cart[$variantId] ?? 0) + max(1, $qty);
    $cap  = min((int) $v['stock'], MAX_PER_LINE);
    $msg  = 'Added to your bag.';
    if ($new > $cap) { $new = $cap; $msg = 'Added. Only ' . $cap . ' available, so we set your quantity to ' . $cap . '.'; }
    $cart[$variantId] = $new;
    $_SESSION['cart'] = $cart;
    cart_save();
    return ['ok' => true, 'message' => $msg];
}
function cart_set(int $variantId, int $qty): void
{
    $cart = cart_get();
    if ($qty <= 0) unset($cart[$variantId]);
    else $cart[$variantId] = min($qty, MAX_PER_LINE);
    $_SESSION['cart'] = $cart;
    cart_save();
}
function cart_remove(int $variantId): void { cart_set($variantId, 0); }
function cart_clear(): void { $_SESSION['cart'] = []; cart_save(); }

/** Full cart detail with live prices and stock. Items that vanished are dropped; quantities are clamped. */
function cart_detail(): array
{
    $cart = cart_get();
    $out = ['items' => [], 'subtotal' => 0.0, 'count' => 0, 'notices' => []];
    if (!$cart) return $out;

    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $rows = fetch_all(
        "SELECT v.id AS variant_id, v.sku, v.size, v.color, v.color_hex, v.stock,
                p.id AS product_id, p.name, p.slug, p.price, p.status,
                (SELECT image_path FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id IN ($in)", $ids);

    $changed = false;
    $byId = [];
    foreach ($rows as $r) $byId[(int) $r['variant_id']] = $r;

    foreach ($cart as $vid => $qty) {
        $r = $byId[(int) $vid] ?? null;
        if (!$r || $r['status'] !== 'active' || $r['stock'] < 1) {
            $out['notices'][] = ($r['name'] ?? 'An item') . ' is no longer available and was removed from your bag.';
            unset($cart[$vid]); $changed = true; continue;
        }
        if ($qty > $r['stock']) {
            $out['notices'][] = 'Only ' . $r['stock'] . ' of ' . $r['name'] . ' (' . $r['size'] . ', ' . $r['color'] . ') left, so we updated your quantity.';
            $qty = (int) $r['stock']; $cart[$vid] = $qty; $changed = true;
        }
        $r['quantity']   = (int) $qty;
        $r['line_total'] = $r['price'] * $qty;
        $out['items'][]  = $r;
        $out['subtotal'] += $r['line_total'];
        $out['count']    += $qty;
    }
    if ($changed) { $_SESSION['cart'] = $cart; cart_save(); }
    return $out;
}

/** Mirror the session cart into the database for signed-in customers. */
function cart_save(): void
{
    if (empty($_SESSION['user_id'])) return;
    $uid = (int) $_SESSION['user_id'];
    q('INSERT INTO carts (user_id) VALUES (?) ON DUPLICATE KEY UPDATE updated_at = NOW()', [$uid]);
    $cartId = (int) fetch_value('SELECT id FROM carts WHERE user_id = ?', [$uid]);
    q('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
    foreach (cart_get() as $vid => $qty) {
        q('INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?,?,?)', [$cartId, (int) $vid, (int) $qty]);
    }
}
/** On sign-in, combine anything saved from earlier visits with the current session cart. */
function cart_merge_saved(int $userId): void
{
    $saved = fetch_all('SELECT ci.variant_id, ci.quantity FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?', [$userId]);
    $cart = cart_get();
    foreach ($saved as $s) {
        $vid = (int) $s['variant_id'];
        $cart[$vid] = min(MAX_PER_LINE, max($cart[$vid] ?? 0, (int) $s['quantity']));
    }
    $_SESSION['cart'] = $cart;
    $_SESSION['user_id'] = $userId;
    cart_save();
}
