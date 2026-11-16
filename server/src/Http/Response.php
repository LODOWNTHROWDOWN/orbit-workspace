<?php
declare(strict_types=1);

namespace Orbit\Http;

final class Response
{
    public function __construct(
        public readonly mixed $body,
        public readonly int $status = 200,
    ) {
    }

    public static function error(int $status, string $message): self
    {
        return new self(['error' => $message], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        echo json_encode($this->body, JSON_UNESCAPED_SLASHES);
    }
}
