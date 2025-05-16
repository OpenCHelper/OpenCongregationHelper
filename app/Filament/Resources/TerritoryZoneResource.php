<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerritoryZoneResource\Pages;
use App\Filament\Resources\TerritoryZoneResource\RelationManagers;
use App\Models\TerritoryZone;
use DiscoveryDesign\FilamentGaze\Forms\Components\GazeBanner;
use Filament\Forms;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\SpatieTagsColumn;
use Spatie\Tags\Tag;

class TerritoryZoneResource extends Resource
{
    protected static ?string $model = TerritoryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'zona';
    }

    public static function getPluralModelLabel(): string
    {
        return 'zone';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reparto territori';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // GazeBanner::make()
                //     ->lock()
                //     ->columnSpanFull()
                //     // ->canTakeControl(fn() => auth()->user()->isAdmin())
                //     ->hiddenOn(['create', 'view']),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(50),
                Forms\Components\ColorPicker::make('color'),
                SpatieTagsInput::make('tags')
                    ->type('zones')
                    ->nestedRecursiveRules([
                        'min:3',
                        'max:255',
                    ]),
                // Forms\Components\TagsInput::make('asd')
                //     ->suggestions([
                //         'tag1',
                //         'tag2',
                //         'tag3',
                //     ])
                //     ->nestedRecursiveRules([
                //         'min:3',
                //         'max:255',
                //     ])
                //     ->label('Tags'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): string => TerritoryZoneResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('territories_count')
                    ->counts('territories')
                    ->label('Totale territori')
                    ->sortable(),
                SpatieTagsColumn::make('tags')
                    ->type('zones'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tags')
                    ->multiple()
                    ->options(Tag::getWithType('zones')->pluck('name', 'name'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['values'], function (Builder $query, $data): Builder {
                            return $query->withAnyTags(array_values($data), 'zones');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            Pages\ViewTerritoryZone::class,
            Pages\ManageZoneTerritories::class,
        ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerritoryZones::route('/'),
            'create' => Pages\CreateTerritoryZone::route('/create'),
            'view' => Pages\ViewTerritoryZone::route('/{record}'),
            'edit' => Pages\EditTerritoryZone::route('/{record}/edit'),
            'territories' => Pages\ManageZoneTerritories::route('/{record}/territories'),
        ];
    }
}
