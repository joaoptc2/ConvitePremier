<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function db_connect(array $cfg): PDO
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (($cfg['db_driver'] ?? 'mysql') === 'sqlite') {
        $dir = dirname($cfg['db_path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $cfg['db_path'], null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_name']);
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect($GLOBALS['config']);
    }
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Cria as tabelas (idempotente). */
function db_install_schema(PDO $pdo, string $driver): void
{
    $isMysql = $driver !== 'sqlite';
    $id = $isMysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $isMysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $text = 'TEXT';

    $pdo->exec("CREATE TABLE IF NOT EXISTS guests (
        id $id,
        token VARCHAR(32) NOT NULL,
        name VARCHAR(190) NOT NULL,
        treatment CHAR(1) NOT NULL DEFAULT 'm',
        phone VARCHAR(40) NULL,
        email VARCHAR(190) NULL,
        group_name VARCHAR(120) NULL,
        max_companions INT NOT NULL DEFAULT 0,
        notes $text NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        response_name VARCHAR(190) NULL,
        companions INT NOT NULL DEFAULT 0,
        companion_names $text NULL,
        message $text NULL,
        responded_at DATETIME NULL,
        sent_at DATETIME NULL,
        first_viewed_at DATETIME NULL,
        last_viewed_at DATETIME NULL,
        view_count INT NOT NULL DEFAULT 0,
        checked_in_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(64) NOT NULL PRIMARY KEY,
        svalue $text NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity (
        id $id,
        guest_id INT NULL,
        action VARCHAR(20) NOT NULL,
        detail VARCHAR(255) NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL
    )$tail");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $id,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL
    )$tail");

    $indexes = [
        'CREATE UNIQUE INDEX ux_guests_token ON guests (token)',
        'CREATE INDEX ix_guests_status ON guests (status)',
        'CREATE INDEX ix_activity_guest ON activity (guest_id)',
        'CREATE INDEX ix_login_ip ON login_attempts (ip)',
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($isMysql ? $sql : str_replace('INDEX ', 'INDEX IF NOT EXISTS ', $sql));
        } catch (PDOException $e) {
            // índice já existe (MySQL não suporta IF NOT EXISTS em índices)
        }
    }
}
