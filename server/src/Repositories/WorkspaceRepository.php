<?php
declare(strict_types=1);

namespace Orbit\Repositories;

use PDO;

final class WorkspaceRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findActiveId(string $tokenHash): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM workspaces WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()');
        $stmt->execute([$tokenHash]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function countActive(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM workspaces WHERE expires_at > UTC_TIMESTAMP()')->fetchColumn();
    }

    public function create(string $tokenHash, int $lifetimeSeconds): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workspaces (token_hash, expires_at) VALUES (?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))'
        );
        $stmt->execute([$tokenHash, $lifetimeSeconds]);
        return (int) $this->db->lastInsertId();
    }

    /** Projects and tasks are removed too, via ON DELETE CASCADE. */
    public function deleteExpired(): void
    {
        $this->db->exec('DELETE FROM workspaces WHERE expires_at < UTC_TIMESTAMP()');
    }
}
