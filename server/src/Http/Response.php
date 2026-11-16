<?php
declare(strict_types=1);

namespace Orbit\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly mixed $body,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function error(int $status, string $message, array $headers = []): self
    {
        return new self(['error' => $message], $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($this->body, JSON_UNESCAPED_SLASHES);
    }
}
