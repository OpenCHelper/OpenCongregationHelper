<?php

namespace App\Filament\Resources;

use App\Filament\Actions\Assignment as AssignmentActions;
use App\Filament\Resources\TerritoryResource\Pages;
use App\Models\Territory;
use App\Models\TerritoryAssignmentType;
use App\Models\User;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Dotswan\MapPicker\Fields\Map;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\Page;


class TerritoryResource extends Resource
{
    protected static ?string $model = Territory::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 0;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;


    public static function getModelLabel(): string
    {
        return 'territorio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'territori';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reparto territori';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                GazeBanner::make()
                    ->lock()
                    ->columnSpanFull()
                    // ->canTakeControl(fn() => auth()->user()->isAdmin())
                    ->hiddenOn(['create', 'view']),
                Forms\Components\Section::make('Informazioni fondamentali')
                    ->description('Informazioni necessarie per l\'identificazione del territorio')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Select::make('territory_zone_id')
                            ->required()
                            ->relationship(name: 'territoryZone', titleAttribute: 'name')
                            ->preload()
                            ->searchable()
                            ->createOptionForm(fn(Form $form) => TerritoryZoneResource::form($form)),
                        Forms\Components\Toggle::make('is_commercial')
                            // ->inline(false)
                            ->onIcon('heroicon-o-building-storefront')
                            ->offIcon('heroicon-o-home'),
                        Forms\Components\Tabs::make('Tabs')
                            ->columnSpanFull()
                            ->contained(false)
                            ->tabs([
                                Forms\Components\Tabs\Tab::make('TabImage')
                                    ->label('Immagine')
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->extraAttributes(['style' => 'min-height: 320px;'])
                                            ->image()
                                            ->panelLayout('integrated')
                                            ->imageEditor()
                                            ->openable()
                                            ->previewable(),
                                    ]),
                                Forms\Components\Tabs\Tab::make('TabMap')
                                    ->label('Mappa')
                                    ->icon('heroicon-o-map')
                                    ->schema([
                                        Map::make('location')
                                            ->label('Location')
                                            ->columnSpanFull()
                                            ->extraStyles(['style' => 'min-height: 50vh', 'border-radius: 8px'])
                                            ->dehydrated(false)
                                            ->afterStateUpdated(function (Set $set, ?array $state): void {
                                                $set('geojson',   json_encode($state['geojson'] ?? ''));
                                            })
                                            ->afterStateHydrated(function ($state, $record, Set $set): void {
                                                $geojson = json_decode(strip_tags($record->geojson ?? ''));
                                                $location = [
                                                    'geojson' => $geojson,
                                                ];
                                                if ($record->geojson ?? null != null && $geojson !== null) {
                                                    $location['lat'] = -1;
                                                    $location['lng'] = -1;
                                                }

                                                $set('location', $location);
                                            })
                                            ->showMarker(false)
                                            ->showFullscreenControl()
                                            ->showZoomControl()
                                            ->draggable()
                                            ->zoom(15)
                                            ->showMyLocationButton()
                                            ->geoMan()
                                            ->geoManEditable($form->getOperation() !== 'view')
                                            ->geoManPosition('topleft')
                                            ->rotateMode(false)
                                            ->clickable(false) //click to move marker
                                            ->drawMarker(false)
                                            ->drawPolygon($form->getOperation() !== 'view')
                                            ->drawPolyline(false)
                                            ->drawCircle(false)
                                            ->drawCircleMarker(false)
                                            ->dragMode(false)
                                            ->cutPolygon(false)
                                            ->editPolygon($form->getOperation() !== 'view')
                                            ->deleteLayer($form->getOperation() !== 'view')
                                            ->setColor('#3388ff')
                                            ->setFilledColor('#cad9ec'),
                                        Forms\Components\TextInput::make('geojson')
                                            // ->hiddenLabel()
                                            ->readOnly(),
                                ]),
                            ])
                            ->activeTab(function (?Model $record) {
                                return $record?->geojson ? 2 : 1;
                            })
                    ]),
                Forms\Components\Section::make('Informazioni aggiuntive')
                    ->description('Informazioni utili in fase di lavorazione e/o gestione del territorio')
                    ->columns(1)
                    ->schema([
                        // Forms\Components\TextInput::make('dimensione')
                        //     ->numeric(),
                        Forms\Components\Textarea::make('notes')
                            ->helperText('Inserisci eventuali note utili per la lavorazione del territorio')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->recordUrl(fn (Model $record): string => TerritoryResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('territoryZone.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_commercial')
                    ->boolean()
                    ->label('Commerciale?')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('currentAssignment')
                    ->state(function (Territory $record): ?string {
                        return $record->currentAssignment()->assignee->full_name ?? null;
                    })
                    ->label('Assegnazione attuale')
                    ->default('')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_commercial')
                    ->nullable()
                    ->boolean()
                    ->label('Tipo territorio')
                    ->placeholder('Qualsiasi')
                    ->trueLabel('Commerciale')
                    ->falseLabel('Residenziale'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    AssignmentActions\CreateAssignmentActionTable::make('create_assignment'),
                    AssignmentActions\DeleteAssignmentActionTable::make('cancel_assignment'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('create_assignment_bulk')
                        ->label('Assegna massivamente')
                        ->icon('heroicon-o-document-arrow-up')
                        ->form([
                            Forms\Components\Select::make('assignee_id')
                                ->label('Assegnato a')
                                ->options(User::get(['id', 'first_name', 'last_name'])->pluck('full_name', 'id')->toArray())
                                ->searchable()
                                ->required(),
                            Forms\Components\Select::make('assignment_type')
                                ->label('Tipo di assegnazione')
                                ->options(TerritoryAssignmentType::query()->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment() !== null && $record->currentAssignment()->date_end === null) {
                                    Log::warning("Skipping record {$record->id} because it has an active assignment");
                                    continue;
                                }

                                $record->assignments()->create([
                                    'assignee_id' => $data['assignee_id'],
                                    'territory_id' => $record->id,
                                    'territory_assignment_type_id' => $data['assignment_type'],
                                    'created_by' => Auth::id(),
                                ]);
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    // AssignmentActions\BulkCreateAssignmentActionTable::make('create_assignment_bulk')
                    //     ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('cancel_assignment_bulk')
                        ->label('Termina assegnazioni')
                        ->icon('heroicon-o-document-arrow-down')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Skip if the last assignment is not null
                                if ($record->currentAssignment() === null || $record->currentAssignment()->date_end !== null) {
                                    Log::warning("Skipping record {$record->id} because it does not has an active assignment");
                                    continue;
                                }

                                $record->currentAssignment()->update([
                                    'date_end' => now(),
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                    ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerritories::route('/'),
            'create' => Pages\CreateTerritory::route('/create'),
            'edit' => Pages\EditTerritory::route('/{record}/edit'),
            'view' => Pages\ViewTerritory::route('/{record}'),
            'assignments' => Pages\ManageTerritoryAssignments::route('/{record}/assignments'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewTerritory::class,
            Pages\ManageTerritoryAssignments::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
