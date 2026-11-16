<?php
declare(strict_types=1);

namespace Orbit\Workspace;

use Orbit\Http\HttpException;
use Orbit\Http\Request;
use Orbit\Limits;
use Orbit\Repositories\WorkspaceRepository;
use Orbit\Security\RateLimiter;

/**
 * Finds the visitor's demo workspace from their cookie, or creates and seeds
 * a fresh one (valid for 24 hours). Creation is rate limited per IP and
 * capped globally so bots can't fill the database.
 */
final class WorkspaceSession
{
    private const COOKIE = 'orbit_demo';
    private const LIFETIME_SECONDS = 86400;

    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly DemoSeeder $seeder,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function find(Request $request): ?Workspace
    {
        $token = $request->cookie(self::COOKIE);
        if ($token === null || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $id = $this->workspaces->findActiveId($hash);
        return $id === null ? null : new Workspace($id, $hash);
    }

    public function start(Request $request): Workspace
    {
        $this->limiter->hit(
            'workspace-create:' . $request->clientIp(),
            Limits::WORKSPACES_PER_IP,
            Limits::WORKSPACES_PER_IP_WINDOW,
            'Too many new demo sessions from your network. Please try again later.',
        );
        if ($this->workspaces->countActive() >= Limits::MAX_ACTIVE_WORKSPACES) {
            throw new HttpException(503, 'The demo is at capacity right now. Please try again later.', ['Retry-After' => '3600']);
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $id = $this->workspaces->create($hash, self::LIFETIME_SECONDS);

        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::LIFETIME_SECONDS,
            'path' => '/',
            'secure' => $request->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->seeder->seed($id);

        return new Workspace($id, $hash);
    }
}
