<?php
declare(strict_types=1);

namespace Orbit\Workspace;

final class Workspace
{
    public function __construct(
        public readonly int $id,
        public readonly string $tokenHash,
    ) {
    }
}
