<?php

namespace App\Filament\Pages;

use AchyutN\FilamentLogViewer\LogTable;
use Override;

/**
 * Log viewer page of `achyutn/filament-log-viewer`.
 *
 * The plugin stores its navigation label the first time it is read, so the label would stay in the language of the
 * request that read it first: this page translates it on every request instead.
 */
class Logs extends LogTable
{
    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament-log-viewer::log.navigation.label');
    }
}
