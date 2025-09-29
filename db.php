# app/db.php
<?php
$config = require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $pdo = new PDO($cfg['db']['dsn'], $cfg['db']['user'], $cfg['db']['pass'], $cfg['db']['options']);
    }
    return $pdo;
}
