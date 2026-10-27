<?php
/** Order creation, status changes and access rules. */

const ORDER_STATUSES   = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded'];

function order_history_add(int $orderId, string $status, ?string $note = null, ?int $by = null): void
{
    q('INSERT INTO order_status_history (order_id, status, note, created_by) VALUES (?,?,?,?)', [$orderId, $status, $note ?: null, $by]);
}

/**
 * Creates an order from the current cart inside one transaction.
 * Stock rows are locked, checked and reduced together so two shoppers cannot buy the last item.
 * Returns ['ok'=>true,'order'=>row] or ['ok'=>false,'message'=>...].
 */
function create_order(array $f, array $cartItems, ?int $userId): array
{
    if (!$cartItems) return ['ok' => false, 'message' => 'Your bag is empty.'];
    $pdo = db();
    try {
        $pdo->beginTransaction();

        $ids = array_map(fn($i) => (int) $i['variant_id'], $cartItems);
        sort($ids);                                       // consistent lock order avoids deadlocks
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = fetch_all("SELECT v.id, v.sku, v.size, v.color, v.stock, p.id AS product_id, p.name, p.price, p.status
                           FROM product_variants v JOIN products p ON p.id = v.product_id
                           WHERE v.id IN ($in) ORDER BY v.id FOR UPDATE", $ids);
        $locked = [];
        foreach ($rows as $r) $locked[(int) $r['id']] = $r;

        $subtotal = 0.0; $lines = [];
        foreach ($cartItems as $ci) {
            $r = $locked[(int) $ci['variant_id']] ?? null;
            $qty = (int) $ci['quantity'];
            if (!$r || $r['status'] !== 'active') { $pdo->rollBack(); return ['ok' => false, 'message' => ($ci['name'] ?? 'An item') . ' is no longer available.']; }
            if ($r['stock'] < $qty) { $pdo->rollBack(); return ['ok' => false, 'message' => 'Only ' . $r['stock'] . ' left of ' . $r['name'] . ' (' . $r['size'] . ', ' . $r['color'] . '). Please update your bag.']; }
            $lineTotal = $r['price'] * $qty;
            $subtotal += $lineTotal;
            $lines[] = [$r, $qty, $lineTotal];
        }

        $fee   = delivery_fee($f['county'], $subtotal);
        $total = $subtotal + $fee;
        $number = generate_order_number();

        q('INSERT INTO orders (order_number, user_id, customer_name, email, phone, delivery_address, town, county, notes,
                               subtotal, delivery_fee, total, payment_method, payment_status, status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [$number, $userId, $f['name'], $f['email'] ?: null, $f['phone'], $f['address'], $f['town'], $f['county'],
           $f['notes'] ?: null, $subtotal, $fee, $total, $f['payment_method'],
           $f['payment_method'] === 'mpesa' ? 'pending' : 'unpaid', 'pending']);
        $orderId = insert_id();

        foreach ($lines as [$r, $qty, $lineTotal]) {
            q('INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, size, color, unit_price, quantity, line_total)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
              [$orderId, $r['product_id'], $r['id'], $r['name'], $r['sku'], $r['size'], $r['color'], $r['price'], $qty, $lineTotal]);
            q('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$qty, $r['id'], $qty]);
        }

        q('INSERT INTO payments (order_id, method, amount, phone, status) VALUES (?,?,?,?,?)',
          [$orderId, $f['payment_method'], $total, $f['payment_method'] === 'mpesa' ? $f['mpesa_phone'] : $f['phone'],
           $f['payment_method'] === 'mpesa' ? 'initiated' : 'pending']);
        order_history_add($orderId, 'pending', 'Order placed');

        $pdo->commit();
        return ['ok' => true, 'order' => fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId])];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('create_order failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'We could not place your order. Please try again.'];
    }
}

function get_order(string $number): ?array { return fetch_one('SELECT * FROM orders WHERE order_number = ?', [$number]); }
function get_order_items(int $orderId): array { return fetch_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]); }
function get_order_history(int $orderId): array { return fetch_all('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id', [$orderId]); }

/** Guests can open their own orders for the session; signed-in customers can open theirs; staff can open any. */
function can_view_order(array $order): bool
{
    if (in_array($order['id'], $_SESSION['my_orders'] ?? [], false)) return true;
    $u = current_user();
    if (!$u) return false;
    return is_staff() || (int) $order['user_id'] === (int) $u['id'];
}
function remember_order(int $orderId): void
{
    $_SESSION['my_orders'] = array_values(array_unique(array_merge($_SESSION['my_orders'] ?? [], [$orderId])));
}

/** Puts stock back for an order (used when it is cancelled). Safe to call once; guarded by status. */
function restock_order(int $orderId): void
{
    foreach (get_order_items($orderId) as $it) {
        if ($it['variant_id']) q('UPDATE product_variants SET stock = stock + ? WHERE id = ?', [$it['quantity'], $it['variant_id']]);
    }
}

/** Change fulfilment status. Cancelling restocks; un-cancelling re-reserves (if stock allows). */
function set_order_status(int $orderId, string $new, string $note = '', ?int $by = null): array
{
    if (!in_array($new, ORDER_STATUSES, true)) return ['ok' => false, 'message' => 'Unknown status.'];
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $o = fetch_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) { $pdo->rollBack(); return ['ok' => false, 'message' => 'Order not found.']; }
        if ($o['status'] === $new) { $pdo->rollBack(); return ['ok' => true, 'message' => 'No change.']; }

        if ($new === 'cancelled') {
            restock_order($orderId);
        } elseif ($o['status'] === 'cancelled') {
            foreach (get_order_items($orderId) as $it) {
                $affected = q('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$it['quantity'], $it['variant_id'], $it['quantity']])->rowCount();
                if (!$affected) { $pdo->rollBack(); return ['ok' => false, 'message' => 'Cannot reopen: not enough stock for ' . $it['product_name'] . '.']; }
            }
        }
        $pay = $o['payment_status'];
        if ($new === 'delivered' && $o['payment_method'] === 'cod' && $pay === 'unpaid') {
            $pay = 'paid';
            q("UPDATE payments SET status = 'success' WHERE order_id = ? AND method = 'cod'", [$orderId]);
        }
        q('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$new, $pay, $orderId]);
        order_history_add($orderId, $new, $note ?: null, $by);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Order updated.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('set_order_status: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not update the order.'];
    }
}

/** Manual payment status change from the admin (e.g. confirming a till payment, refunds). */
function set_payment_status(int $orderId, string $status, ?int $by = null): array
{
    if (!in_array($status, PAYMENT_STATUSES, true)) return ['ok' => false, 'message' => 'Unknown payment status.'];
    q('UPDATE orders SET payment_status = ? WHERE id = ?', [$status, $orderId]);
    $pm = ['paid' => 'success', 'failed' => 'failed', 'pending' => 'pending', 'unpaid' => 'pending', 'refunded' => 'cancelled'][$status];
    q('UPDATE payments SET status = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$pm, $orderId]);
    order_history_add($orderId, 'payment_' . $status, 'Payment marked ' . $status . ' by staff', $by);
    return ['ok' => true, 'message' => 'Payment status updated.'];
}

/** Cancels M-Pesa orders that were never paid within 2 hours, returning their stock. Runs occasionally from bootstrap. */
function expire_stale_orders(): void
{
    $rows = fetch_all("SELECT id FROM orders WHERE payment_method = 'mpesa' AND status = 'pending'
                       AND payment_status IN ('pending','failed') AND created_at < (NOW() - INTERVAL 2 HOUR) LIMIT 20");
    foreach ($rows as $r) {
        $res = set_order_status((int) $r['id'], 'cancelled', 'Cancelled automatically: M-Pesa payment was not completed within 2 hours');
        if ($res['ok']) q("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'", [$r['id']]);
    }
}
