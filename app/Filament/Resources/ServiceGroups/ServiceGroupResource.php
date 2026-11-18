<?php

namespace App\Filament\Resources\ServiceGroups;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ServiceGroups\Pages\CreateServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\EditServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\ListServiceGroups;
use App\Filament\Resources\ServiceGroups\Pages\ManageServiceGroupMembers;
use App\Filament\Resources\ServiceGroups\Pages\OrganizeServiceGroups;
use App\Filament\Resources\ServiceGroups\Pages\ViewServiceGroup;
use App\Models\ServiceGroup;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

class ServiceGroupResource extends Resource
{
    protected static ?string $model = ServiceGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament-resources.service_group.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-resources.service_group.plural_model_label');
    }

    /**
     * Only English capitalizes every word of a title: "Service Groups", but "Gruppi di servizio".
     */
    #[Override]
    public static function getTitleCaseModelLabel(): string
    {
        return static::toTitleCase(static::getModelLabel());
    }

    #[Override]
    public static function getTitleCasePluralModelLabel(): string
    {
        return static::toTitleCase(static::getPluralModelLabel());
    }

    protected static function toTitleCase(string $label): string
    {
        return str_starts_with(app()->getLocale(), 'en') ? Str::ucwords($label) : Str::ucfirst($label);
    }

    #[Override]
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Administration;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('filament-resources.service_group.field_name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                static::leaderSelect('coordinator_id', 'coordinator', ['assistant_coordinator1_id', 'assistant_coordinator2_id']),
                static::leaderSelect('assistant_coordinator1_id', 'assistantCoordinator1', ['coordinator_id', 'assistant_coordinator2_id']),
                static::leaderSelect('assistant_coordinator2_id', 'assistantCoordinator2', ['coordinator_id', 'assistant_coordinator1_id']),
            ]);
    }

    /**
     * Select of a person leading the group, who cannot hold two roles in the same group.
     *
     * @param  array<int, string>  $otherLeaderFields  The fields of the other leaders of the group
     */
    protected static function leaderSelect(string $field, string $relationship, array $otherLeaderFields): Select
    {
        $select = Select::make($field)
            ->label(__("filament-resources.service_group.field_{$field}"))
            ->relationship($relationship, 'first_name', fn (Builder $query): Builder => $query->orderBy('first_name')->orderBy('last_name'))
            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFullName())
            ->searchable(['first_name', 'last_name'])
            ->preload()
            ->validationMessages(['different' => __('filament-resources.service_group.validation_leader_twice')]);

        foreach ($otherLeaderFields as $otherLeaderField) {
            $select->different($otherLeaderField);
        }

        return $select;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['coordinator', 'assistantCoordinator1', 'assistantCoordinator2']))
            ->recordUrl(fn (Model $record): string => ServiceGroupResource::getUrl('view', ['record' => $record]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-resources.service_group.column_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coordinator.full_name')
                    ->label(__('filament-resources.service_group.column_coordinator'))
                    ->placeholder('—'),
                TextColumn::make('assistants')
                    ->label(__('filament-resources.service_group.column_assistants'))
                    ->state(fn (ServiceGroup $record): array => collect([$record->assistantCoordinator1, $record->assistantCoordinator2])
                        ->filter()
                        ->map(fn (User $user): string => $user->getFullName())
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('filament-resources.service_group.column_members_count'))
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewServiceGroup::class,
            ManageServiceGroupMembers::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceGroups::route('/'),
            'create' => CreateServiceGroup::route('/create'),
            'organize' => OrganizeServiceGroups::route('/organize'),
            'view' => ViewServiceGroup::route('/{record}'),
            'edit' => EditServiceGroup::route('/{record}/edit'),
            'members' => ManageServiceGroupMembers::route('/{record}/members'),
        ];
    }
}
