<?php

namespace App\Support;

/**
 * The cells of the exported files that a spreadsheet would run as a formula (starting with `=`, `+`, `-`, `@`, a tab or
 * a carriage return, e.g. typed in a title): they are prefixed with a quote, so that they are shown as text, and the
 * quote is removed again when the file is imported.
 */
class SpreadsheetFormula
{
    /**
     * The characters that start a formula in a spreadsheet.
     *
     * @var list<string>
     */
    public const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Show as text a cell that a spreadsheet would run as a formula; numbers (e.g. negative ones) are left as they are.
     * A cell that already looks escaped (e.g. typed as `'=x`) is escaped once more, so that `unescape()` gives it back
     * exactly.
     */
    public static function escape(mixed $value): mixed
    {
        return is_string($value) && static::needsQuote($value) ? "'".$value : $value;
    }

    /**
     * Remove the quote added by `escape()`, so that an exported file is imported again as it was: only the quote
     * `escape()` adds, never one typed before a number (e.g. `'-5`).
     */
    public static function unescape(mixed $value): mixed
    {
        return is_string($value) && str_starts_with($value, "'") && static::needsQuote(substr($value, 1)) ? substr($value, 1) : $value;
    }

    /**
     * Whether `escape()` quotes the value: a text starting with a formula character, or one already quoted like that.
     */
    protected static function needsQuote(string $value): bool
    {
        if ($value === '' || is_numeric($value)) {
            return false;
        }

        return in_array($value[0], self::TRIGGERS, true)
            || (str_starts_with($value, "'") && static::needsQuote(substr($value, 1)));
    }
}
