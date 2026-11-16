<?php
declare(strict_types=1);

namespace Orbit\Controllers;

use Orbit\Http\HttpException;
use Orbit\Http\Request;
use Orbit\Http\Response;
use Orbit\Repositories\ProjectRepository;
use Orbit\Limits;
use Orbit\Validator;

final class ProjectController
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly int $workspaceId,
    ) {
    }

    public function index(Request $request): Response
    {
        return new Response(['projects' => $this->projects->allForWorkspace($this->workspaceId)]);
    }

    public function store(Request $request): Response
    {
        $body = $request->json();
        $name = Validator::text($body['name'] ?? null, 100, true);
        $description = Validator::text($body['description'] ?? '', 500);

        if ($this->projects->countForWorkspace($this->workspaceId) >= Limits::PROJECTS_PER_WORKSPACE) {
            throw new HttpException(422, 'Demo limit: ' . Limits::PROJECTS_PER_WORKSPACE . ' projects per workspace.');
        }

        $id = $this->projects->create($this->workspaceId, $name, $description);
        return new Response(['id' => $id], 201);
    }
}
