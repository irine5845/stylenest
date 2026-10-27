<?php
/**
 * Create or reset an admin account from the command line:
 *   php database/create_admin.php you@example.com "Your Name" "YourStrongPassword1"
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/db.php';
[$self, $email, $name, $pw] = array_pad($argv, 4, null);
if (!$email || !$name || !$pw) { fwrite(STDERR, "Usage: php database/create_admin.php email \"Full Name\" password\n"); exit(1); }
$email = strtolower($email);
if (!valid_email($email)) { fwrite(STDERR, "Invalid email.\n"); exit(1); }
if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) { fwrite(STDERR, "Password needs 8+ characters with a letter and a number.\n"); exit(1); }
$hash = password_hash($pw, PASSWORD_DEFAULT);
$st = q('SELECT id FROM users WHERE email = ?', [$email]);
if ($row = $st->fetch()) { q("UPDATE users SET name=?, password_hash=?, role='admin', is_active=1 WHERE id=?", [$name, $hash, $row['id']]); echo "Updated existing account to admin.\n"; }
else { q("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?, 'admin')", [$name, $email, $hash]); echo "Admin account created.\n"; }
