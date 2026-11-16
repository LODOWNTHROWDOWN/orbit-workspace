<?php
declare(strict_types=1);

namespace Orbit;

use Orbit\Http\HttpException;

final class Validator
{
    public static function text(mixed $value, int $maxLength, bool $required = false): string
    {
        if (!is_string($value)) {
            throw new HttpException(422, 'Invalid text field.');
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $maxLength) {
            throw new HttpException(422, 'Text is empty or too long.');
        }
        return $value;
    }

    public static function id(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, 'Not found.');
        }
        return (int) $value;
    }

    /** @param list<string> $allowed */
    public static function oneOf(mixed $value, array $allowed, string $message): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new HttpException(422, $message);
        }
        return $value;
    }

    /** Accepts null or a real calendar date in YYYY-MM-DD form. */
    public static function optionalDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new HttpException(422, 'Invalid due date.');
        }
        return $value;
    }
}
