<?php
declare(strict_types=1);

namespace Orbit\Repositories;

use PDO;

final class ProjectRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allForWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, created_at FROM projects WHERE workspace_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function countForWorkspace(int $workspaceId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM projects WHERE workspace_id = ?');
        $stmt->execute([$workspaceId]);
        return (int) $stmt->fetchColumn();
    }

    public function existsInWorkspace(int $projectId, int $workspaceId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM projects WHERE id = ? AND workspace_id = ?');
        $stmt->execute([$projectId, $workspaceId]);
        return $stmt->fetchColumn() !== false;
    }

    public function create(int $workspaceId, string $name, string $description): int
    {
        $stmt = $this->db->prepare('INSERT INTO projects (workspace_id, name, description) VALUES (?, ?, ?)');
        $stmt->execute([$workspaceId, $name, $description]);
        return (int) $this->db->lastInsertId();
    }
}
