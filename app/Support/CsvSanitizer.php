<?php

namespace App\Support;

final class CsvSanitizer
{
    public static function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;
    }
}
