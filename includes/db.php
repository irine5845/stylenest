<?php
/** Shared PDO connection and small query helpers (all queries use prepared statements). */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+03:00'");
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('DB connection failed: ' . $e->getMessage());
        if (config('debug')) {
            die('<h1>Database connection failed</h1><p>' . htmlspecialchars($e->getMessage()) .
                '</p><p>Check config/env.php and that you imported database/schema.sql and database/seed.sql.</p>');
        }
        die('The store is temporarily unavailable. Please try again shortly.');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function fetch_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function fetch_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function fetch_value(string $sql, array $params = [])      { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function insert_id(): int { return (int) db()->lastInsertId(); }
