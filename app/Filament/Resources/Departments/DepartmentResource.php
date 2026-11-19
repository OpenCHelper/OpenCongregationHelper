<?php

namespace App\Filament\Resources\Departments;

use App\Enums\DepartmentKey;
use App\Enums\NavigationGroup;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\Pages\ViewDepartment;
use App\Filament\Resources\Departments\RelationManagers\MembersRelationManager;
use App\Models\Department;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;

/**
 * The departments of the congregation and their members.
 *
 * The departments are defined by `DepartmentKey`: they can't be created nor deleted, only their members change.
 */
class DepartmentResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Department::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::RectangleGroup;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('departments.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('departments.resource.plural_model_label');
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Administration;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->ordered();
    }

    /**
     * Get the names of the sections of the menu managed by the department.
     *
     * @return list<string>
     */
    public static function getSectionLabels(DepartmentKey $department): array
    {
        return array_map(
            static fn (string $resource): string => $resource::getNavigationLabel(),
            $department->getResources(),
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('sections')
                            ->label(__('departments.resource.field_sections'))
                            ->state(fn (Department $record): array => static::getSectionLabels($record->key))
                            ->badge()
                            ->color(fn (Department $record): string => $record->key->getColor())
                            ->helperText(__('departments.resource.field_sections_helper')),
                        TextEntry::make('overseers')
                            ->label(__('departments.resource.field_overseers'))
                            ->state(fn (Department $record): array => $record->overseers()->get()->map(fn (User $user): string => $user->getFullName())->all())
                            ->icon(Heroicon::OutlinedStar)
                            ->placeholder(__('departments.resource.no_overseer')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('overseers')->withCount('members'))
            ->recordUrl(fn (Department $record): ?string => static::canView($record) ? static::getUrl('view', ['record' => $record]) : null)
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label(__('departments.resource.column_name'))
                    ->formatStateUsing(fn (DepartmentKey $state): string => $state->getLabel())
                    ->description(fn (Department $record): string => $record->key->getDescription())
                    ->icon(fn (Department $record) => $record->key->getIcon())
                    ->iconColor(fn (Department $record): string => $record->key->getColor())
                    ->weight(FontWeight::SemiBold)
                    ->wrap(),
                TextColumn::make('sections')
                    ->label(__('departments.resource.column_sections'))
                    ->state(fn (Department $record): array => static::getSectionLabels($record->key))
                    ->badge()
                    ->color(fn (Department $record): string => $record->key->getColor())
                    ->visibleFrom('lg'),
                TextColumn::make('overseers')
                    ->label(__('departments.resource.column_overseers'))
                    ->state(fn (Department $record): array => $record->overseers->map(fn (User $user): string => $user->getFullName())->all())
                    ->icon(Heroicon::OutlinedStar)
                    ->listWithLineBreaks()
                    ->placeholder(__('departments.resource.no_overseer')),
                TextColumn::make('members_count')
                    ->label(__('departments.resource.column_members'))
                    ->icon(Heroicon::OutlinedUsers)
                    ->alignCenter(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getDocumentation(): array
    {
        return [
            'administration.departments',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'view' => ViewDepartment::route('/{record}'),
        ];
    }
}
