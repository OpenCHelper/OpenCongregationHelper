<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Traits\Localizable;

/**
 * An import column recognised only by its English names, whatever the language of the user: Filament also matches the
 * headers of the file against the label of the column, which is translated, so the label is read in English here.
 *
 * The header of the example file, when given, is in English too, so that it is recognised again when it is imported
 * (otherwise it is the name of the column, as in Filament).
 */
class EnglishImportColumn extends ImportColumn
{
    use Localizable;

    /**
     * The language of the headers of the files.
     */
    public const LOCALE = 'en';

    /**
     * @return array<string>
     */
    public function getGuesses(): array
    {
        return $this->withLocale(self::LOCALE, fn (): array => parent::getGuesses());
    }

    public function getExampleHeader(): string
    {
        return $this->withLocale(self::LOCALE, fn (): string => parent::getExampleHeader());
    }
}
