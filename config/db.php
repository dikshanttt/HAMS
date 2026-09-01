<?php

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'hms');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: 'Dikshant123');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dbUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');

        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'] ?? '127.0.0.1';
            $port = $parsed['port'] ?? '5432';
            $user = $parsed['user'] ?? 'postgres';
            $pass = $parsed['pass'] ?? '';
            $name = ltrim($parsed['path'] ?? 'hms', '/');

            // Default to require for cloud databases
            $sslmode = 'require';
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                if (isset($queryParams['sslmode'])) {
                    $sslmode = $queryParams['sslmode'];
                }
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
            $dbUser = $user;
            $dbPass = $pass;
        } else {
            $dsn = "pgsql:host=" . DB_HOST
                 . ";port=" . DB_PORT
                 . ";dbname=" . DB_NAME;

            if (getenv('DB_SSL') === 'true' || getenv('DB_SSL') === '1' || (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost')) {
                $dsn .= ";sslmode=require";
            }

            $dbUser = DB_USER;
            $dbPass = DB_PASS;
        }

        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;

    } catch (PDOException $e) {
        error_log('HMS database connection failed: ' . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }
}