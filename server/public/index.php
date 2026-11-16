<?php
declare(strict_types=1);

/**
 * Front controller for the JSON API. Every /api/* request is rewritten here
 * by .htaccess. In Docker the classes live in /opt/orbit/src.
 */
require is_file(__DIR__ . '/../src/autoload.php') ? __DIR__ . '/../src/autoload.php' : '/opt/orbit/src/autoload.php';

use Orbit\Controllers\ProjectController;
use Orbit\Controllers\TaskController;
use Orbit\Database;
use Orbit\Http\HttpException;
use Orbit\Http\Request;
use Orbit\Http\Response;
use Orbit\Http\Router;
use Orbit\Repositories\ProjectRepository;
use Orbit\Repositories\TaskRepository;
use Orbit\Repositories\WorkspaceRepository;
use Orbit\Security\Csrf;
use Orbit\Workspace\DemoSeeder;
use Orbit\Workspace\WorkspaceSession;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$request = Request::fromGlobals();

try {
    $db = Database::connection();

    if ($request->path === '/api/health') {
        (new Response(['status' => 'ok']))->send();
        exit;
    }
    if (!str_starts_with($request->path, '/api/')) {
        throw new HttpException(404, 'Not found.');
    }

    $workspaces = new WorkspaceRepository($db);
    $projects = new ProjectRepository($db);
    $tasks = new TaskRepository($db);

    // Occasionally clean up expired demo workspaces.
    if (random_int(1, 100) === 1) {
        $workspaces->deleteExpired();
    }

    $workspace = (new WorkspaceSession($workspaces, new DemoSeeder($projects, $tasks)))->resolve($request);

    $csrf = new Csrf(getenv('CSRF_SECRET') ?: 'dev-only-change-me', $workspace->tokenHash);
    if ($request->method !== 'GET' && !$csrf->isValid($request->header('X-CSRF-Token'))) {
        throw new HttpException(403, 'Invalid CSRF token. Refresh the page.');
    }

    $projectController = new ProjectController($projects, $workspace->id);
    $taskController = new TaskController($projects, $tasks, $workspace->id);

    $router = new Router();
    $router->get('/api/bootstrap', fn () => new Response(['csrfToken' => $csrf->token(), 'demoExpiresHours' => 24]));
    $router->get('/api/projects', $projectController->index(...));
    $router->post('/api/projects', $projectController->store(...));
    $router->get('/api/projects/{projectId}/tasks', $taskController->index(...));
    $router->post('/api/projects/{projectId}/tasks', $taskController->store(...));
    $router->patch('/api/tasks/{id}', $taskController->updateStatus(...));

    $response = $router->dispatch($request);
} catch (HttpException $e) {
    $response = Response::error($e->status, $e->getMessage());
} catch (PDOException $e) {
    error_log($e->getMessage());
    $response = Response::error(503, 'Database unavailable. Check deployment configuration.');
}

$response->send();
