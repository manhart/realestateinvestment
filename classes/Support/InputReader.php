<?php
declare(strict_types=1);

namespace realestateinvestment\classes\Support;

final class InputReader
{
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? $default;
        return is_numeric($value) ? (float)$value : $default;
    }

    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? $default;
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? $default;
        if(is_bool($value)) {
            return $value;
        }
        if(is_numeric($value)) {
            return (int)$value === 1;
        }
        if(is_string($value)) {
            $normalized = strtolower($value);
            if(in_array($normalized, ['1', 'true', 'yes', 'ja', 'on'], true)) {
                return true;
            }
            if(in_array($normalized, ['0', 'false', 'no', 'nein', 'off'], true)) {
                return false;
            }
        }
        return $default;
    }

    public static function rate(array $data, string $key, float $defaultPercent = 0.0): float
    {
        return self::float($data, $key, $defaultPercent) / 100;
    }

    public static function month(array $data, string $key, int $default = 1): int
    {
        return min(max(self::int($data, $key, $default), 1), 12);
    }

    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }
}
