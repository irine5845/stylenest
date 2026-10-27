<?php
/**
 * Safaricom Daraja posts the STK Push result here (server to server).
 * The URL carries a secret key so random visitors cannot fake a payment, and the amount is checked
 * before an order is marked paid.
 */
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/orders_lib.php';
require __DIR__ . '/includes/mpesa_lib.php';
date_default_timezone_set('Africa/Nairobi');

$secret = (string) (mpesa_cfg()['callback_secret'] ?? '');
if ($secret === '' || !hash_equals($secret, (string) ($_GET['key'] ?? ''))) { http_response_code(403); exit('Forbidden'); }

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
$cb   = $data['Body']['stkCallback'] ?? null;

header('Content-Type: application/json');
if (!$cb || empty($cb['CheckoutRequestID'])) { http_response_code(400); echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Bad payload']); exit; }

$receipt = null; $amount = null;
foreach (($cb['CallbackMetadata']['Item'] ?? []) as $item) {
    if (($item['Name'] ?? '') === 'MpesaReceiptNumber') $receipt = substr((string) $item['Value'], 0, 40);
    if (($item['Name'] ?? '') === 'Amount') $amount = (float) $item['Value'];
}
mpesa_apply_result((string) $cb['CheckoutRequestID'], (int) ($cb['ResultCode'] ?? 1), (string) ($cb['ResultDesc'] ?? ''), $receipt, $amount, $raw);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
