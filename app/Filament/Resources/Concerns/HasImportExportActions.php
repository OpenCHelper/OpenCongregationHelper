<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Importer;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The "Import / Export" menu of the header of a page listing the records of a resource: importing needs to be allowed
 * to create them, exporting to see them.
 */
trait HasImportExportActions
{
    /**
     * @param  class-string<Importer>  $importer
     * @param  class-string<Exporter>  $exporter
     */
    protected function getImportExportActions(string $importer, string $exporter): ActionGroup
    {
        $resource = static::getResource();
        $model = $resource::getModel();

        return ActionGroup::make([
            ImportAction::make()
                ->pluralModelLabel($resource::getPluralModelLabel())
                ->authorize(fn (): bool => Gate::allows('create', $model))
                ->importer($importer),
            ExportAction::make()
                ->pluralModelLabel($resource::getPluralModelLabel())
                ->authorize(fn (): bool => Gate::allows('viewAny', $model))
                ->exporter($exporter),
        ])
            ->label(__('filament-resources.import_export'))
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('gray')
            ->button();
    }
}
