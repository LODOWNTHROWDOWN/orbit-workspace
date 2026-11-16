<?php
declare(strict_types=1);

namespace Orbit\Http;

final class Request
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $server,
        private readonly array $cookies,
        private readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            $_SERVER,
            $_COOKIE,
            (string) file_get_contents('php://input'),
        );
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }

    public function cookie(string $name): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * The visitor's IP address. Railway's edge proxy sets X-Real-IP to the
     * client address; without it (local Docker) fall back to the socket peer.
     */
    public function clientIp(): string
    {
        $realIp = trim($this->header('X-Real-IP'));
        if (filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
            return $realIp;
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? 'unknown');
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return ($https !== '' && $https !== 'off') || getenv('RAILWAY_ENVIRONMENT_NAME') !== false;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Expected a JSON object.');
        }
        return $data;
    }
}
