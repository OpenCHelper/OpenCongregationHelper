<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\TerritoryAssignmentResource;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;

class ManageCampaignAssignments extends ManageRelatedRecords
{
    protected static string $resource = CampaignResource::class;

    protected static string $relationship = 'assignments';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return ucfirst(__('filament-resources.territory_assignment.plural_model_label'));
    }

    public static function getNavigationBadge(): ?string
    {
        return Livewire::current()->getRecord()->assignments()->count();
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-resources.campaign.manage_assignments_title');
    }

    public function form(Form $form): Form
    {
        return TerritoryAssignmentResource::form($form);
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
            ->defaultSort('started_at', 'desc')
            ->heading(ucfirst(__('filament-resources.territory_assignment.plural_model_label')))
            ->description(__('filament-resources.campaign.assignments_relation_description'))
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
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
                        Forms\Components\Wizard\Step::make(__('filament-resources.campaign.assignments_create_step_details'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('territory_id')
                                    ->label(__('filament-resources.territory_assignment.field_territory'))
                                    ->columns(1)
                                    ->relationship('territory', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('assignee_id')
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
                                Forms\Components\DatePicker::make('started_at')
                                    ->label(__('filament-resources.territory_assignment.field_started_at'))
                                    ->columns(1)
                                    ->default(now())
                                    ->required(),
                            ])->columnSpanFull(),
                        Forms\Components\Wizard\Step::make(__('filament-resources.campaign.assignments_create_step_additional'))
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('filament-resources.territory_assignment.field_notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Wizard\Step::make(__('filament-resources.campaign.assignments_create_step_review'))
                            ->description(__('filament-resources.campaign.assignments_create_step_review_description'))
                            ->schema([
                                Forms\Components\Section::make()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Placeholder::make('review_campaign_name')
                                            ->label(__('filament-resources.campaign.field_name'))
                                            ->columns(1)
                                            ->content($this->getRecord()->name),
                                        Forms\Components\Placeholder::make('review_assignee')
                                            ->label(__('filament-resources.territory_assignment.field_assignee'))
                                            ->columns(1)
                                            ->content(fn (Get $get) => $get('assignee_id')
                                                ? User::find($get('assignee_id'))->full_name
                                                : ''),
                                        Forms\Components\Placeholder::make('territory_review')
                                            ->label(__('filament-resources.territory_assignment.field_territory'))
                                            ->columns(1)
                                            ->content(fn (Get $get) => $get('territory_id') 
                                                ? Territory::find($get('territory_id'))->name 
                                                : ''),
                                        Forms\Components\DatePicker::make('started_at')
                                            ->label(__('filament-resources.territory_assignment.field_started_at'))
                                            ->columns(1)
                                            ->disabled(),
                                        Forms\Components\Placeholder::make('review_notes')
                                            ->label(__('filament-resources.territory_assignment.field_notes'))
                                            ->columnSpanFull()
                                            ->content(fn (Get $get) => $get('notes') ?: new HtmlString(
                                                '<span class="text-gray-500 italic">'
                                                . __('filament-resources.territory_assignment.info_notes_placeholder')
                                                . '</span>'
                                            )),
                                    ]),
                            ]),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('terminate_assignments')
                        ->label(__('filament-resources.campaign.bulk_action_terminate_assignments'))
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('filament-resources.campaign.bulk_action_terminate_assignments_modal_heading'))
                        ->modalDescription(__('filament-resources.campaign.bulk_action_terminate_assignments_modal_description'))
                        ->modalSubmitActionLabel(__('filament-resources.campaign.bulk_action_terminate_assignments_submit'))
                        ->action(function (Collection $records): void {
                            $terminatedCount = 0;
                            $skippedCount = 0;
                            
                            foreach ($records as $assignment) {
                                // Skip if assignment is already terminated
                                if ($assignment->ended_at !== null) {
                                    $skippedCount++;
                                    Log::warning("Skipping assignment {$assignment->id} because it is already terminated");
                                    continue;
                                }
                                
                                try {
                                    $assignment->update([
                                        'ended_at' => now(),
                                    ]);
                                    $terminatedCount++;
                                } catch (\Exception $e) {
                                    Log::error('Error terminating assignment ' . $assignment->id . ': ' . $e->getMessage());
                                }
                            }
                            
                            // Show success notification
                            if ($terminatedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title(__('filament-resources.campaign.bulk_action_terminate_success_title'))
                                    ->body(__('filament-resources.campaign.bulk_action_terminate_success_body', [
                                        'count' => $terminatedCount,
                                        'skipped' => $skippedCount
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
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
