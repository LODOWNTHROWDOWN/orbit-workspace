<?php
declare(strict_types=1);

namespace Orbit\Security;

use Orbit\Http\HttpException;
use PDO;

/**
 * Fixed-window rate limiter stored in MySQL (table rate_limits).
 * Keys (e.g. an IP address) are stored only as keyed hashes.
 */
final class RateLimiter
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $secret,
    ) {
    }

    /**
     * Counts one hit for $key and throws 429 once more than $limit hits
     * happen within the current $windowSeconds window.
     */
    public function hit(string $key, int $limit, int $windowSeconds, string $message): void
    {
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);

        // Atomic increment; LAST_INSERT_ID(expr) hands the new count back to this connection.
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)'
        );
        $stmt->execute([hash_hmac('sha256', $key, $this->secret), $windowStart]);
        $hits = (int) $this->db->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        if ($hits > $limit) {
            $retryAfter = $windowStart + $windowSeconds - $now;
            throw new HttpException(429, $message, ['Retry-After' => (string) $retryAfter]);
        }
    }

    public function deleteOlderThan(int $seconds): void
    {
        $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE window_start < ?');
        $stmt->execute([time() - $seconds]);
    }
}
