<?php

declare(strict_types=1);

namespace SuccessTree\Db;

/**
 * Shared literal formatting: only strings need driver-specific escaping.
 */
abstract class AbstractAdapter implements DbAdapterInterface
{
    /**
     * @param mixed $value
     */
    public function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                return 'NULL';
            }
            // var_export is locale independent ("1.5", "1.0E+25").
            return var_export($value, true);
        }
        if (is_array($value) || is_object($value)) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = (string)$value;
            } else {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                $value = $json === false ? '' : $json;
            }
        }
        return $this->quoteString((string)$value);
    }

    /** Returns the quoted SQL literal (with surrounding quotes). */
    abstract protected function quoteString(string $value): string;

    /**
     * Portable manual escaping, used only when no native escaper is available.
     * - sqlite : standard SQL, quotes doubled, backslashes are literal.
     * - mysql  : \0 \n \r \\ " \x1a escaped with a backslash and ' doubled ('' is safe both with
     *            and without NO_BACKSLASH_ESCAPES, so a quote can never terminate the literal).
     */
    public static function manualQuote(string $value, string $driver): string
    {
        if ($driver === 'sqlite') {
            return "'" . str_replace(["\0", "'"], ['', "''"], $value) . "'";
        }
        $escaped = strtr($value, [
            "\\"   => "\\\\",
            "\0"   => "\\0",
            "\n"   => "\\n",
            "\r"   => "\\r",
            "'"    => "''",
            '"'    => '\\"',
            "\x1a" => "\\Z",
        ]);
        return "'" . $escaped . "'";
    }
}
