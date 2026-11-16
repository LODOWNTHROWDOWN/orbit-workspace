<?php
declare(strict_types=1);

namespace Orbit\Security;

/**
 * CSRF token bound to the visitor's workspace: each workspace gets a different
 * token, and the token is useless without the matching HttpOnly cookie.
 */
final class Csrf
{
    private readonly string $token;

    public function __construct(string $secret, string $workspaceTokenHash)
    {
        $this->token = hash_hmac('sha256', 'orbit-csrf-v1|' . $workspaceTokenHash, $secret);
    }

    public function token(): string
    {
        return $this->token;
    }

    public function isValid(string $candidate): bool
    {
        return hash_equals($this->token, $candidate);
    }
}
