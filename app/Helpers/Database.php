<?php
// =============================================================================
// app/Helpers/Database.php — Database connection using PDO
// =============================================================================
// PDO is PHP's built-in database library. It's safe, fast, and supports
// prepared statements which protect against SQL injection attacks.
//
// We use a "singleton" pattern here — meaning only ONE connection is created
// for the entire request, no matter how many times you call Database::get().
// =============================================================================

namespace App\Helpers;

use PDO;
use PDOException;

class Database
{
    // Holds the single connection instance
    private static ?PDO $instance = null;

    // Private constructor — you can't do "new Database()", must use ::get()
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Get the database connection
    // Usage anywhere in your app: $db = Database::get();
    // -------------------------------------------------------------------------
    public static function get(): PDO
    {
        if (self::$instance === null) {
            $cfg = config('db');  // reads from config/app.php

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['name'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    // Throw exceptions on errors (instead of silent failures)
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                    // Return rows as associative arrays: $row['name'] not $row[0]
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Don't "emulate" prepared statements — use real ones
                    // This is more secure and slightly faster
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // In production: log the error, show a friendly message
                // Never show the raw database error to users!
                error_log('DB Connection failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed']));
            }
        }

        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Convenience: run a SELECT and get all rows
    // Usage: $users = Database::query("SELECT * FROM users WHERE active = ?", [1]);
    // -------------------------------------------------------------------------
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Convenience: run a SELECT and get ONE row
    // Usage: $user = Database::row("SELECT * FROM users WHERE id = ?", [$id]);
    // -------------------------------------------------------------------------
    public static function row(string $sql, array $params = []): ?array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // -------------------------------------------------------------------------
    // Convenience: run INSERT / UPDATE / DELETE
    // Usage: Database::run("UPDATE users SET name = ? WHERE id = ?", [$name, $id]);
    // -------------------------------------------------------------------------
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // -------------------------------------------------------------------------
    // Get the ID of the last INSERT
    // Usage: $newId = Database::lastId();
    // -------------------------------------------------------------------------
    public static function lastId(): string
    {
        return self::get()->lastInsertId();
    }
}
