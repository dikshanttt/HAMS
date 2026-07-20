<?php
/**
 * Database connection (PDO).
 * Works for either PostgreSQL or MSSQL — just change DB_DRIVER below
 * once your team decides. Everything else in the app uses PDO
 * prepared statements, so the rest of the codebase doesn't change.
 */

// ---- EDIT THESE FOR YOUR ENVIRONMENT --------------------------------
define('DB_DRIVER', 'pgsql');      // 'pgsql' or 'sqlsrv'
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');         // 1433 for MSSQL
define('DB_NAME', 'hms');
define('DB_USER', 'postgres');
define('DB_PASS', '');
// ----------------------------------------------------------------------

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'pgsql') {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        } elseif (DB_DRIVER === 'sqlsrv') {
            $dsn = "sqlsrv:Server=" . DB_HOST . "," . DB_PORT . ";Database=" . DB_NAME;
        } else {
            throw new Exception('Unsupported DB_DRIVER: ' . DB_DRIVER);
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        // In production, log this instead of echoing it.
        die('Database connection failed: ' . $e->getMessage());
    }
}
