<?php
/**
 * M-Pesa (Safaricom Daraja) STK Push.
 *
 * - mode 'simulate' needs no credentials, so the full checkout flow can be tested on localhost.
 * - mode 'live' calls Daraja. Payment is only ever confirmed from Daraja's server-to-server
 *   callback (or an STK Query made by the server), never from the customer's browser.
 * - The customer's M-Pesa PIN is entered on their phone. It never touches this website.
 */

function mpesa_cfg(): array { return config('mpesa'); }
function mpesa_is_simulated(): bool { return (mpesa_cfg()['mode'] ?? 'simulate') !== 'live'; }
function mpesa_base(): string
{
    return (mpesa_cfg()['env'] ?? 'sandbox') === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_http(string $method, string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) return ['ok' => false, 'status' => 0, 'error' => $err, 'json' => null];
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'error' => null, 'json' => json_decode($resp, true)];
}

function mpesa_token(): ?string
{
    $c = mpesa_cfg();
    $cache = __DIR__ . '/../storage/mpesa_token.json';
    if (is_file($cache)) {
        $t = json_decode((string) file_get_contents($cache), true);
        if (!empty($t['token']) && ($t['expires'] ?? 0) > time() + 30 && ($t['key'] ?? '') === md5($c['consumer_key'] . mpesa_base())) return $t['token'];
    }
    $auth = base64_encode($c['consumer_key'] . ':' . $c['consumer_secret']);
    $r = mpesa_http('GET', mpesa_base() . '/oauth/v1/generate?grant_type=client_credentials', ['Authorization: Basic ' . $auth]);
    if (!$r['ok'] || empty($r['json']['access_token'])) { error_log('M-Pesa token failed: ' . json_encode($r)); return null; }
    @file_put_contents($cache, json_encode(['token' => $r['json']['access_token'], 'expires' => time() + 3000, 'key' => md5($c['consumer_key'] . mpesa_base())]), LOCK_EX);
    return $r['json']['access_token'];
}

function mpesa_timestamp(): string { return date('YmdHis'); }
function mpesa_password(string $ts): string { $c = mpesa_cfg(); return base64_encode($c['shortcode'] . $c['passkey'] . $ts); }

function mpesa_callback_url(): string
{
    $c = mpesa_cfg();
    $base = $c['callback_url'] ?: absolute_url('mpesa_callback.php');
    return $base . (str_contains($base, '?') ? '&' : '?') . 'key=' . rawurlencode($c['callback_secret']);
}

/** Asks Safaricom to prompt the customer's phone. Updates the payment row with the request IDs. */
function mpesa_stk_push(array $order, string $phone): array
{
    $amount = (int) ceil($order['total']);
    $payment = fetch_one("SELECT * FROM payments WHERE order_id = ? AND method = 'mpesa' ORDER BY id DESC LIMIT 1", [$order['id']]);
    if (!$payment) return ['ok' => false, 'message' => 'No payment record found for this order.'];

    if (mpesa_is_simulated()) {
        $ids = ['SIM-M-' . strtoupper(bin2hex(random_bytes(4))), 'SIM-C-' . strtoupper(bin2hex(random_bytes(6)))];
        q("UPDATE payments SET status = 'pending', phone = ?, merchant_request_id = ?, checkout_request_id = ?, result_code = NULL, result_desc = 'Simulated STK push' WHERE id = ?",
          [$phone, $ids[0], $ids[1], $payment['id']]);
        q("UPDATE orders SET payment_status = 'pending' WHERE id = ?", [$order['id']]);
        return ['ok' => true, 'message' => 'Simulated payment prompt created.'];
    }

    $c = mpesa_cfg();
    if (!$c['consumer_key'] || !$c['consumer_secret'] || !$c['passkey']) {
        return ['ok' => false, 'message' => 'M-Pesa is not configured yet. Please choose Cash on delivery or contact the store.'];
    }
    $token = mpesa_token();
    if (!$token) return ['ok' => false, 'message' => 'We could not reach M-Pesa. Please try again in a moment.'];

    $ts = mpesa_timestamp();
    $body = [
        'BusinessShortCode' => $c['shortcode'],
        'Password'          => mpesa_password($ts),
        'Timestamp'         => $ts,
        'TransactionType'   => $c['transaction_type'],
        'Amount'            => $amount,
        'PartyA'            => $phone,
        'PartyB'            => $c['shortcode'],
        'PhoneNumber'       => $phone,
        'CallBackURL'       => mpesa_callback_url(),
        'AccountReference'  => substr($order['order_number'], 0, 12),
        'TransactionDesc'   => 'StyleNest order',
    ];
    $r = mpesa_http('POST', mpesa_base() . '/mpesa/stkpush/v1/processrequest', ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], $body);
    $j = $r['json'] ?? [];
    if ($r['ok'] && ($j['ResponseCode'] ?? '') === '0') {
        q("UPDATE payments SET status = 'pending', phone = ?, merchant_request_id = ?, checkout_request_id = ?, result_code = NULL, result_desc = ? WHERE id = ?",
          [$phone, $j['MerchantRequestID'] ?? null, $j['CheckoutRequestID'] ?? null, substr($j['CustomerMessage'] ?? 'STK push sent', 0, 255), $payment['id']]);
        q("UPDATE orders SET payment_status = 'pending' WHERE id = ?", [$order['id']]);
        return ['ok' => true, 'message' => 'Check your phone and enter your M-Pesa PIN to complete the payment.'];
    }
    error_log('STK push failed: ' . json_encode($r));
    q("UPDATE payments SET status = 'failed', result_desc = ? WHERE id = ?", [substr($j['errorMessage'] ?? $r['error'] ?? 'STK push request failed', 0, 255), $payment['id']]);
    return ['ok' => false, 'message' => 'M-Pesa could not send the payment request. Check the number and try again.'];
}

/** Server-side status check, used as a fallback if the callback is delayed. */
function mpesa_stk_query(string $checkoutRequestId): void
{
    if (mpesa_is_simulated()) return;
    $c = mpesa_cfg();
    $token = mpesa_token();
    if (!$token) return;
    $ts = mpesa_timestamp();
    $r = mpesa_http('POST', mpesa_base() . '/mpesa/stkpushquery/v1/query', ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        ['BusinessShortCode' => $c['shortcode'], 'Password' => mpesa_password($ts), 'Timestamp' => $ts, 'CheckoutRequestID' => $checkoutRequestId]);
    $j = $r['json'] ?? [];
    if (isset($j['ResultCode'])) {
        mpesa_apply_result($checkoutRequestId, (int) $j['ResultCode'], (string) ($j['ResultDesc'] ?? ''), null, null, json_encode($j));
    }
}

/**
 * Records the outcome of a payment attempt. Idempotent: a payment that is already successful is never changed.
 * $paidAmount (when known) must match the order total before an order is marked paid.
 */
function mpesa_apply_result(string $checkoutRequestId, int $code, string $desc, ?string $receipt, ?float $paidAmount, string $raw): bool
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $p = fetch_one("SELECT * FROM payments WHERE checkout_request_id = ? AND method = 'mpesa' FOR UPDATE", [$checkoutRequestId]);
        if (!$p || $p['status'] === 'success') { $pdo->rollBack(); return (bool) $p; }

        if ($code === 0) {
            if ($paidAmount !== null && $paidAmount + 0.001 < (float) $p['amount']) {
                q("UPDATE payments SET status = 'failed', result_code = ?, result_desc = ?, raw_callback = ? WHERE id = ?",
                  [$code, 'Amount paid did not match order total', $raw, $p['id']]);
                q("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$p['order_id']]);
                order_history_add((int) $p['order_id'], 'payment_failed', 'Amount mismatch, needs review');
            } else {
                q("UPDATE payments SET status = 'success', mpesa_receipt = ?, result_code = 0, result_desc = ?, raw_callback = ? WHERE id = ?",
                  [$receipt, substr($desc, 0, 255), $raw, $p['id']]);
                q("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [$p['order_id']]);
                order_history_add((int) $p['order_id'], 'payment_paid', 'M-Pesa payment received' . ($receipt ? ' (' . $receipt . ')' : ''));
                if (fetch_value('SELECT status FROM orders WHERE id = ?', [$p['order_id']]) === 'cancelled') {
                    order_history_add((int) $p['order_id'], 'payment_paid', 'ATTENTION: paid after the order was cancelled. Reopen the order or refund the customer.');
                }
            }
        } else {
            $status = ($code === 1032) ? 'cancelled' : 'failed';   // 1032 = customer cancelled the prompt
            q("UPDATE payments SET status = ?, result_code = ?, result_desc = ?, raw_callback = ? WHERE id = ?",
              [$status, $code, substr($desc, 0, 255), $raw, $p['id']]);
            q("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'", [$p['order_id']]);
            order_history_add((int) $p['order_id'], 'payment_failed', substr($desc, 0, 200));
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('mpesa_apply_result: ' . $e->getMessage());
        return false;
    }
}
