<?php
/** CSRF protection for every state-changing request. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf'] ?? '', $sent);
}
/** Call at the top of any POST handler. */
function csrf_check(): void
{
    if (csrf_valid()) return;
    if (is_ajax()) json_out(['ok' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
    http_response_code(419);
    flash('error', 'Your session expired. Please try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? '');
}
