<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

class ManageCampaignAssignments extends ManageRelatedRecords
{
    protected static string $resource = CampaignResource::class;

    protected static string $relationship = 'assignments';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return ucfirst(__('filament-resources.territory_assignment.plural_model_label'));
    }

    public static function getNavigationBadge(): ?string
    {
        return TerritoryAssignmentResource::scopeToVisibleAssignments(Livewire::current()->getRecord()->assignments())->count();
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-resources.campaign.manage_assignments_title');
    }

    public function form(Schema $schema): Schema
    {
        return TerritoryAssignmentResource::form($schema);
    }

    public function table(Table $table): Table
    {
        $inherited_table = TerritoryAssignmentResource::table($table);

        // Hide the campaign column since we're already in the campaign context
        $campaignColumn = $inherited_table->getColumn('campaign.name');
        if ($campaignColumn) {
            $campaignColumn->hidden();
        }

        return $inherited_table
            ->modifyQueryUsing(fn (Builder $query) => TerritoryAssignmentResource::scopeToVisibleAssignments($query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
            ))
            ->defaultSort('started_at', 'desc')
            ->heading(ucfirst(__('filament-resources.territory_assignment.plural_model_label')))
            ->description(__('filament-resources.campaign.assignments_relation_description'))
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['campaign_id'] = $this->getRecord()->id;

                        return $data;
                    })
                    ->hidden(fn () => $this->getRecord()->isTerminated())
                    ->modalWidth('5xl')
                    ->closeModalByClickingAway(false)
                    ->modalHeading(__('filament-resources.campaign.assignments_create_modal_heading'))
                    ->modalDescription(__('filament-resources.campaign.assignments_create_modal_description', ['campaign' => $this->getRecord()->name]))
                    ->stickyModalFooter()
                    ->stickyModalHeader()
                    ->steps([
                        Step::make(__('filament-resources.campaign.assignments_create_step_details'))
                            ->columns(2)
                            ->schema([
                                Select::make('territory_id')
                                    ->label(__('filament-resources.territory_assignment.field_territory'))
                                    ->columns(1)
                                    // Prevent assigning a territory that is already assigned
                                    ->relationship('territory', 'name', fn (Builder $query) => $query->unassigned())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('assignee_id')
                                    ->label(__('filament-resources.territory_assignment.field_assignee'))
                                    ->searchable(['first_name', 'last_name'])
                                    ->disabledOn('edit', 'view')
                                    ->required()
                                    ->relationship(
                                        name: 'assignee',
                                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                                    ->preload(),
                                DatePicker::make('started_at')
                                    ->label(__('filament-resources.territory_assignment.field_started_at'))
                                    ->columns(1)
                                    ->default(now())
                                    ->required(),
                            ])->columnSpanFull(),
                        Step::make(__('filament-resources.campaign.assignments_create_step_additional'))
                            ->schema([
                                Textarea::make('notes')
                                    ->label(__('filament-resources.territory_assignment.field_notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Step::make(__('filament-resources.campaign.assignments_create_step_review'))
                            ->description(__('filament-resources.campaign.assignments_create_step_review_description'))
                            ->schema([
                                Section::make()
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('review_campaign_name')
                                            ->label(__('filament-resources.campaign.field_name'))
                                            ->columns(1)
                                            ->state($this->getRecord()->name),
                                        TextEntry::make('review_assignee')
                                            ->label(__('filament-resources.territory_assignment.field_assignee'))
                                            ->columns(1)
                                            ->state(fn (Get $get) => $get('assignee_id')
                                                ? User::find($get('assignee_id'))->full_name
                                                : ''),
                                        TextEntry::make('territory_review')
                                            ->label(__('filament-resources.territory_assignment.field_territory'))
                                            ->columns(1)
                                            ->state(fn (Get $get) => $get('territory_id')
                                                ? Territory::find($get('territory_id'))->name
                                                : ''),
                                        DatePicker::make('started_at')
                                            ->label(__('filament-resources.territory_assignment.field_started_at'))
                                            ->columns(1)
                                            ->disabled(),
                                        TextEntry::make('review_notes')
                                            ->label(__('filament-resources.territory_assignment.field_notes'))
                                            ->columnSpanFull()
                                            ->state(fn (Get $get) => $get('notes') ?: new HtmlString(
                                                '<span class="text-gray-500 italic">'
                                                .__('filament-resources.territory_assignment.info_notes_placeholder')
                                                .'</span>'
                                            )),
                                    ]),
                            ]),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('terminate_assignments')
                        ->label(__('filament-resources.campaign.bulk_action_terminate_assignments'))
                        ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('filament-resources.campaign.bulk_action_terminate_assignments_modal_heading'))
                        ->modalDescription(__('filament-resources.campaign.bulk_action_terminate_assignments_modal_description'))
                        ->modalSubmitActionLabel(__('filament-resources.campaign.bulk_action_terminate_assignments_submit'))
                        ->authorize(fn (): bool => Gate::allows('update', new TerritoryAssignment))
                        ->action(function (Collection $records): void {
                            $terminatedCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $assignment) {
                                // Skip if assignment is already terminated or cannot be updated by the user
                                if ($assignment->ended_at !== null || Gate::denies('update', $assignment)) {
                                    $skippedCount++;
                                    Log::warning("Skipping assignment {$assignment->id} because it is already terminated or cannot be updated");

                                    continue;
                                }

                                try {
                                    $assignment->update([
                                        'ended_at' => now(),
                                    ]);
                                    $terminatedCount++;
                                } catch (Exception $e) {
                                    Log::error('Error terminating assignment '.$assignment->id.': '.$e->getMessage());
                                }
                            }

                            // Show success notification
                            if ($terminatedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title(__('filament-resources.campaign.bulk_action_terminate_success_title'))
                                    ->body(__('filament-resources.campaign.bulk_action_terminate_success_body', [
                                        'count' => $terminatedCount,
                                        'skipped' => $skippedCount,
                                    ]))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title(__('filament-resources.campaign.bulk_action_terminate_warning_title'))
                                    ->body(__('filament-resources.campaign.bulk_action_terminate_warning_body'))
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Keep existing bulk actions from TerritoryAssignmentResource
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
