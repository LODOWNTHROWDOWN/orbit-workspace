<?php
declare(strict_types=1);

namespace Orbit\Workspace;

use Orbit\Repositories\ProjectRepository;
use Orbit\Repositories\TaskRepository;

/** Fills a new workspace with fictional sample projects and tasks. */
final class DemoSeeder
{
    private const PROJECTS = [
        ['Website Redesign', 'Refresh content, navigation and accessibility'],
        ['Mobile App', 'Plan and develop a companion experience'],
        ['Marketing Launch', 'Organize launch activities and assets'],
    ];

    private const TASKS = [
        ['Review requirements', 'Confirm scope and acceptance criteria', 'todo', 'high'],
        ['Build initial interface', 'Develop responsive screens', 'progress', 'medium'],
        ['Set up project structure', 'Prepare application foundation', 'done', 'low'],
    ];

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks,
    ) {
    }

    public function seed(int $workspaceId): void
    {
        foreach (self::PROJECTS as [$name, $description]) {
            $projectId = $this->projects->create($workspaceId, $name, $description);
            foreach (self::TASKS as [$title, $taskDescription, $status, $priority]) {
                $this->tasks->create($projectId, $title, $taskDescription, $priority, null, $status);
            }
        }
    }
}
