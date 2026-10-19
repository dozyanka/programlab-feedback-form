<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

/** Atomic fixed-window rate limiter backed by SQLite (works across PHP sessions). */
final class RateLimiter
{
    private PDO $db;

    public function __construct(string $databasePath)
    {
        $this->db = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $this->db->exec('PRAGMA journal_mode=WAL');
        $this->db->exec('CREATE TABLE IF NOT EXISTS rate_limits (
            key TEXT PRIMARY KEY,
            attempts INTEGER NOT NULL,
            reset_at INTEGER NOT NULL
        )');
    }

    public function allow(string $identity, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $key = hash_hmac('sha256', $identity, Config::get('APP_KEY', 'local-development-only-key'));
        $this->db->exec('BEGIN IMMEDIATE');

        try {
            $query = $this->db->prepare('SELECT attempts, reset_at FROM rate_limits WHERE key = :key');
            $query->execute(['key' => $key]);
            $record = $query->fetch();

            if (!$record || (int) $record['reset_at'] <= $now) {
                $save = $this->db->prepare('INSERT INTO rate_limits (key, attempts, reset_at)
                    VALUES (:key, 1, :reset_at)
                    ON CONFLICT(key) DO UPDATE SET attempts=1, reset_at=excluded.reset_at');
                $save->execute(['key' => $key, 'reset_at' => $now + $windowSeconds]);
                $allowed = true;
            } elseif ((int) $record['attempts'] < $limit) {
                $save = $this->db->prepare('UPDATE rate_limits SET attempts=attempts+1 WHERE key=:key');
                $save->execute(['key' => $key]);
                $allowed = true;
            } else {
                $allowed = false;
            }

            if (random_int(1, 100) === 1) {
                $cleanup = $this->db->prepare('DELETE FROM rate_limits WHERE reset_at < :cutoff');
                $cleanup->execute(['cutoff' => $now]);
            }
            // BEGIN IMMEDIATE was issued as SQL to obtain SQLite's write lock up
            // front. PDO::commit() only works with PDO::beginTransaction(), so
            // finish this SQL-started transaction using SQL as well.
            $this->db->exec('COMMIT');
            return $allowed;
        } catch (Throwable $error) {
            try {
                // Likewise, PDO::rollBack() cannot be used for this transaction.
                $this->db->exec('ROLLBACK');
            } catch (Throwable) {
                // SQLite might already have rolled back. Keep the original error.
            }
            throw $error;
        }
    }
}
