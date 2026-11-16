<?php
declare(strict_types=1);

namespace Orbit\Workspace;

use Orbit\Http\Request;
use Orbit\Repositories\WorkspaceRepository;

/**
 * Finds the visitor's demo workspace from their cookie, or creates and seeds
 * a fresh one (valid for 24 hours) when there is no valid cookie.
 */
final class WorkspaceSession
{
    private const COOKIE = 'orbit_demo';
    private const LIFETIME_SECONDS = 86400;

    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly DemoSeeder $seeder,
    ) {
    }

    public function resolve(Request $request): Workspace
    {
        $token = $request->cookie(self::COOKIE);
        if ($token !== null && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $hash = hash('sha256', $token);
            $id = $this->workspaces->findActiveId($hash);
            if ($id !== null) {
                return new Workspace($id, $hash);
            }
        }

        return $this->start($request->isHttps());
    }

    private function start(bool $https): Workspace
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $id = $this->workspaces->create($hash, self::LIFETIME_SECONDS);

        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::LIFETIME_SECONDS,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->seeder->seed($id);

        return new Workspace($id, $hash);
    }
}
