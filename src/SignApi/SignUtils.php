<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * Small helpers for reading fields out of a decoded JSON array, shared by the
 * Sign value objects. Each helper throws a SignException (HTTP 400) with a
 * descriptive message on a type/presence violation so callers get consistent
 * error handling.
 *
 * Empty strings are treated as valid values; only presence and type are checked.
 */
final class SignUtils
{
    private function __construct()
    {
    }

    /**
     * Returns a required string field.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException if the field is missing or not a string
     */
    public static function requireString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value)) {
            throw new SignException(sprintf('The "%s" field is required and must be a string.', $field));
        }

        return $value;
    }

    /**
     * Returns an optional string field, or null if the key is absent.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException if the field is present but not a string
     */
    public static function optionalString(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data)) {
            return null;
        }
        $value = $data[$field];
        if (!is_string($value)) {
            throw new SignException(sprintf('The "%s" field must be a string.', $field));
        }

        return $value;
    }

    /**
     * Returns a required integer field.
     *
     * @param array<string, mixed> $data
     *
     * @throws SignException if the field is missing or not an integer
     */
    public static function requireInt(array $data, string $field): int
    {
        if (!array_key_exists($field, $data) || !is_int($data[$field])) {
            throw new SignException(sprintf('The "%s" field is required and must be an integer.', $field));
        }

        return $data[$field];
    }

    /**
     * Returns a required array field.
     *
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     *
     * @throws SignException if the field is missing or not an array
     */
    public static function requireArray(array $data, string $field): array
    {
        $value = $data[$field] ?? null;
        if (!is_array($value)) {
            throw new SignException(sprintf('The "%s" field is required and must be an object or array.', $field));
        }

        return $value;
    }
}
