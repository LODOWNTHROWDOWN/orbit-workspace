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
use Orbit\Limits;
use Orbit\Repositories\ProjectRepository;
use Orbit\Repositories\TaskRepository;
use Orbit\Repositories\WorkspaceRepository;
use Orbit\Security\Csrf;
use Orbit\Security\RateLimiter;
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

    $secret = getenv('CSRF_SECRET') ?: 'dev-only-change-me';
    $workspaces = new WorkspaceRepository($db);
    $projects = new ProjectRepository($db);
    $tasks = new TaskRepository($db);
    $limiter = new RateLimiter($db, $secret);

    // Occasionally clean up expired demo workspaces and old rate-limit counters.
    if (random_int(1, 100) === 1) {
        $workspaces->deleteExpired();
        $limiter->deleteOlderThan(86400);
    }

    // Only the page-load request (bootstrap) may start a new workspace. Any other
    // request without a valid session is refused, so cookie-less bots hitting
    // random endpoints can't create workspaces.
    $session = new WorkspaceSession($workspaces, new DemoSeeder($projects, $tasks), $limiter);
    $workspace = $session->find($request);
    if ($workspace === null) {
        if ($request->method !== 'GET' || $request->path !== '/api/bootstrap') {
            throw new HttpException(401, 'Your demo session has expired. Refresh the page to start a new one.');
        }
        $workspace = $session->start($request);
    }

    $limiter->hit(
        'requests:workspace:' . $workspace->id,
        Limits::REQUESTS_PER_WORKSPACE,
        Limits::REQUESTS_PER_WORKSPACE_WINDOW,
        'Too many requests. Please slow down and try again in a minute.',
    );

    $csrf = new Csrf($secret, $workspace->tokenHash);
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
    $response = Response::error($e->status, $e->getMessage(), $e->headers);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $response = Response::error(503, 'Database unavailable. Check deployment configuration.');
}

$response->send();
