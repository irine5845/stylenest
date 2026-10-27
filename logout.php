<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) { csrf_check(); logout_user(); session_start(); flash('success', 'You have been signed out.'); }
redirect('');
