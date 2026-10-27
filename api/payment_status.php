<?php
/** Polled by pay.php. Reports the payment state; falls back to an STK Query if the callback is slow. */
require __DIR__ . '/../includes/bootstrap.php';

$order = get_order(get('o'));
if (!$order || !can_view_order($order)) json_out(['ok' => false], 404);

$p = fetch_one("SELECT * FROM payments WHERE order_id = ? AND method = 'mpesa' ORDER BY id DESC LIMIT 1", [$order['id']]);
if ($p && $p['status'] === 'pending' && $p['checkout_request_id'] && !mpesa_is_simulated()) {
    $last = $_SESSION['stk_query_at'][$p['id']] ?? 0;
    if (time() - $last >= 12 && time() - strtotime($p['created_at']) >= 15) {
        $_SESSION['stk_query_at'][$p['id']] = time();
        mpesa_stk_query($p['checkout_request_id']);
        $p = fetch_one('SELECT * FROM payments WHERE id = ?', [$p['id']]);
    }
}
session_write_close();
json_out(['ok' => true, 'status' => $p['status'] ?? 'initiated', 'paid' => ($p['status'] ?? '') === 'success', 'message' => $p['result_desc'] ?? '']);
