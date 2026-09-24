<?php

declare(strict_types=1);

namespace SuccessTree\Support;

/**
 * JSON helpers for the free-form JSON columns (theme, settings, reward, meta, params, data).
 */
final class Json
{
    const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Encode a PHP value for storage. null => null (SQL NULL). An empty array is stored as "{}"
     * because every JSON column of the schema is object-shaped by default.
     *
     * @param mixed $value
     */
    public static function encode($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === []) {
            return '{}';
        }
        $json = json_encode($value, self::FLAGS | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            return '{}';
        }
        return $json;
    }

    /**
     * Decode a stored JSON column into a PHP array (assoc). Invalid / NULL => [] .
     *
     * @param mixed $raw
     */
    public static function decode($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Prepare an array for JSON output: an empty array becomes {} (object), anything else is kept.
     *
     * @return array|object
     */
    public static function out(array $value)
    {
        return $value === [] ? new \stdClass() : $value;
    }

    /**
     * Encode for HTTP output.
     *
     * @param mixed $value
     */
    public static function encodeOutput($value): string
    {
        $json = json_encode($value, self::FLAGS | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? '{"ok":false,"error":"json_encode_failed"}' : $json;
    }

    public static function isList(array $a): bool
    {
        if ($a === []) {
            return true;
        }
        return array_keys($a) === range(0, count($a) - 1);
    }
}
