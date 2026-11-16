<?php
declare(strict_types=1);

namespace Orbit;

/** Abuse limits for the public demo, kept in one place. */
final class Limits
{
    /** Unexpired workspaces allowed at once; new visitors get "at capacity" beyond this. */
    public const MAX_ACTIVE_WORKSPACES = 500;

    /** New workspaces one IP address may create per window. */
    public const WORKSPACES_PER_IP = 10;
    public const WORKSPACES_PER_IP_WINDOW = 3600;

    /** API requests one workspace may make per window. */
    public const REQUESTS_PER_WORKSPACE = 120;
    public const REQUESTS_PER_WORKSPACE_WINDOW = 60;

    /** Per-workspace content caps. */
    public const PROJECTS_PER_WORKSPACE = 10;
    public const TASKS_PER_PROJECT = 150;
}
