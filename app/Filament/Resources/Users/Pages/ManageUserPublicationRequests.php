<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;

/**
 * The history of the publications requested by a user, like the history of their assignments.
 */
class ManageUserPublicationRequests extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    protected static string $relationship = 'publicationRequests';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    public static function getNavigationLabel(): string
    {
        return __('filament-pages.manage_user_publication_requests');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-pages.manage_user_publication_requests_title', ['name' => $this->getRecord()->getFullName()]);
    }

    public function getBreadcrumb(): string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Livewire::current()->getRecord()->publicationRequests()->pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Only for whoever can see the requests (e.g. the publications department), besides the user.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return PublicationRequestResource::canViewAny() && parent::canAccess($parameters);
    }

    public function form(Schema $schema): Schema
    {
        return PublicationRequestResource::form($schema);
    }

    public function table(Table $table): Table
    {
        $table = PublicationRequestResource::table($table);

        // Always the same as the current user
        $table->getColumn('user.full_name')->hidden();
        $table->getColumn('notes')->toggledHiddenByDefault();

        // In a menu, since the table is narrower next to the pages of the user
        return $table->recordActions([
            ActionGroup::make([
                PublicationRequestResource::markAsOrderedAction(),
                PublicationRequestResource::markAsDeliveredAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ]);
    }
}
