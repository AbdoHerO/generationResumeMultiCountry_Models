<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper: one connection, prepared statements only, exceptions on error.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $socket = (string) config('database.socket', '');
            $name = (string) config('database.database');
            $dsn = $socket !== ''
                ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $name)
                : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', config('database.host'), (int) config('database.port'), $name);
            self::$pdo = new PDO($dsn, (string) config('database.username'), (string) config('database.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    /** @param array<int|string, mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? (string) $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @param array<int|string, mixed> $params @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string, mixed> $params @return list<array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @param array<int|string, mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** For tests and CLI tools that switch databases. */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }
}
