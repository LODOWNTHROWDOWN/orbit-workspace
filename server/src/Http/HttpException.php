<?php
declare(strict_types=1);

namespace Orbit\Http;

use RuntimeException;

/** Thrown anywhere in a request to stop and return a JSON error to the client. */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers extra response headers, e.g. Retry-After */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
