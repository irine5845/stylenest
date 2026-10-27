<?php
// Loads env.php when present, otherwise falls back to env.example.php.
$file = is_file(__DIR__ . '/env.php') ? __DIR__ . '/env.php' : __DIR__ . '/env.example.php';
return require $file;
