<?php
require __DIR__ . '/includes/bootstrap.php';
if (!is_post()) redirect('');
csrf_check();
$email = strtolower(post('email'));
if (!valid_email($email))            flash('error', 'Please enter a valid email address.');
elseif (empty($_POST['consent']))    flash('error', 'Please tick the box to confirm you want to receive our emails.');
else {
    q('INSERT INTO newsletter_subscribers (email, consent) VALUES (?, 1) ON DUPLICATE KEY UPDATE consent = 1', [$email]);
    flash('success', 'You are subscribed. Thank you.');
}
redirect(safe_next(parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '', ''));
