<?php

namespace App\Filament\Resources\TerritoryAssignments\Pages;

use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\TerritoryAssignment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ManageTerritoryAssignments extends ManageRecords
{
    protected static string $resource = TerritoryAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $overdueCount = TerritoryAssignmentResource::getOverdueAssignmentsQuery()->count();
        $dueSoonCount = TerritoryAssignmentResource::getVisibleAssignmentsQuery()->dueSoon()->count();

        return [
            'all' => Tab::make('all')
                ->label(__('filament-resources.territory_assignment.tab_all')),
            'overdue' => Tab::make('overdue')
                ->label(__('filament-resources.territory_assignment.tab_overdue'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge($overdueCount ?: null)
                ->badgeColor('danger'),
            'due_soon' => Tab::make('due_soon')
                ->label(__('filament-resources.territory_assignment.tab_due_soon'))
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query) => $query->dueSoon())
                ->badge($dueSoonCount ?: null)
                ->badgeColor('warning'),
        ];
    }

    /**
     * The tabs, followed by a banner warning about the territories still out for a campaign that has ended.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                $this->getOverdueAssignmentsCallout(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getOverdueAssignmentsCallout(): Callout
    {
        return Callout::make(fn (): string => trans_choice('filament-resources.territory_assignment.overdue_callout_heading', TerritoryAssignmentResource::getOverdueAssignmentsQuery()->count()))
            ->description(__('filament-resources.territory_assignment.overdue_callout_description'))
            ->key('overdueAssignmentsCallout')
            ->danger()
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->visible(fn (): bool => TerritoryAssignmentResource::getOverdueAssignmentsQuery()->exists())
            ->actions([
                Action::make('showOverdueAssignments')
                    ->button()
                    ->label(__('filament-resources.territory_assignment.overdue_callout_show'))
                    ->color('gray')
                    ->hidden(fn (): bool => $this->activeTab === 'overdue')
                    ->action(function (): void {
                        $this->activeTab = 'overdue';
                        $this->updatedActiveTab();
                    }),
                Action::make('returnOverdueAssignments')
                    ->button()
                    ->label(__('filament-resources.territory_assignment.overdue_callout_return_all'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-resources.territory_assignment.overdue_return_all_modal_heading'))
                    ->modalDescription(__('filament-resources.territory_assignment.overdue_return_all_modal_description'))
                    ->modalSubmitActionLabel(__('filament-resources.territory_assignment.action_end'))
                    ->authorize(fn (): bool => Gate::allows('update', new TerritoryAssignment))
                    ->action(function (): void {
                        $returnedCount = 0;

                        TerritoryAssignmentResource::getOverdueAssignmentsQuery()
                            ->lazyById()
                            ->each(function (TerritoryAssignment $assignment) use (&$returnedCount): void {
                                if (Gate::denies('update', $assignment)) {
                                    return;
                                }

                                $assignment->update(['ended_at' => now()]);
                                $returnedCount++;
                            });

                        Notification::make()
                            ->success()
                            ->title(trans_choice('filament-resources.territory_assignment.overdue_return_all_success', $returnedCount))
                            ->send();
                    }),
            ]);
    }
}
