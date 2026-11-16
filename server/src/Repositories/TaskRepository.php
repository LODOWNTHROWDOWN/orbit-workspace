<?php
declare(strict_types=1);

namespace Orbit\Repositories;

use PDO;

final class TaskRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function allForProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, description, status, priority, due_date FROM tasks WHERE project_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function countForProject(int $projectId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tasks WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public function existsInWorkspace(int $taskId, int $workspaceId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ? AND p.workspace_id = ?'
        );
        $stmt->execute([$taskId, $workspaceId]);
        return $stmt->fetchColumn() !== false;
    }

    public function create(
        int $projectId,
        string $title,
        string $description,
        string $priority,
        ?string $dueDate,
        string $status = 'todo',
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (project_id, title, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$projectId, $title, $description, $status, $priority, $dueDate]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $taskId, int $workspaceId, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks t INNER JOIN projects p ON p.id = t.project_id SET t.status = ? WHERE t.id = ? AND p.workspace_id = ?'
        );
        $stmt->execute([$status, $taskId, $workspaceId]);
    }
}
