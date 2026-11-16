<?php
declare(strict_types=1);

namespace Orbit\Controllers;

use Orbit\Http\HttpException;
use Orbit\Http\Request;
use Orbit\Http\Response;
use Orbit\Repositories\ProjectRepository;
use Orbit\Repositories\TaskRepository;
use Orbit\Validator;

final class TaskController
{
    public const MAX_PER_PROJECT = 150;
    private const PRIORITIES = ['low', 'medium', 'high'];
    private const STATUSES = ['todo', 'progress', 'done'];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks,
        private readonly int $workspaceId,
    ) {
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $projectId = $this->ownedProjectId($params['projectId']);
        return new Response(['tasks' => $this->tasks->allForProject($projectId)]);
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params): Response
    {
        $projectId = $this->ownedProjectId($params['projectId']);
        $body = $request->json();
        $title = Validator::text($body['title'] ?? null, 140, true);
        $description = Validator::text($body['description'] ?? '', 1000);
        $priority = Validator::oneOf($body['priority'] ?? 'medium', self::PRIORITIES, 'Invalid priority.');
        $dueDate = Validator::optionalDate($body['due_date'] ?? null);

        if ($this->tasks->countForProject($projectId) >= self::MAX_PER_PROJECT) {
            throw new HttpException(422, 'Demo limit: ' . self::MAX_PER_PROJECT . ' tasks per project.');
        }

        $id = $this->tasks->create($projectId, $title, $description, $priority, $dueDate);
        return new Response(['id' => $id], 201);
    }

    /** @param array<string, string> $params */
    public function updateStatus(Request $request, array $params): Response
    {
        $taskId = Validator::id($params['id']);
        if (!$this->tasks->existsInWorkspace($taskId, $this->workspaceId)) {
            throw new HttpException(404, 'Task not found.');
        }

        $status = Validator::oneOf($request->json()['status'] ?? null, self::STATUSES, 'Invalid status.');
        $this->tasks->updateStatus($taskId, $this->workspaceId, $status);
        return new Response(['updated' => true]);
    }

    private function ownedProjectId(string $rawId): int
    {
        $projectId = Validator::id($rawId);
        if (!$this->projects->existsInWorkspace($projectId, $this->workspaceId)) {
            throw new HttpException(404, 'Project not found.');
        }
        return $projectId;
    }
}
