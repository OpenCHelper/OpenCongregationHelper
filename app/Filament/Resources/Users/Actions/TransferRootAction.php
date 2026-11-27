<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Make another user the root user, in place of the current one (see `User::makeRootUser()`): offered only to the root
 * user, on the page of a super administrator who can log in, after confirming their password.
 *
 * Only in production: on demos and previews, where anyone can sign in as the root user, it stays the first user.
 */
class TransferRootAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'transferRoot';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-resources.user.action_transfer_root'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (User $record): bool => static::isRootUserSignedIn() && ! $record->isRootUser())
            ->disabled(fn (User $record): bool => static::getRefusal($record) !== null)
            ->tooltip(fn (User $record): ?string => static::getRefusal($record))
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalHeading(fn (User $record): string => __('filament-resources.user.transfer_root_heading', ['name' => $record->name]))
            ->modalDescription(fn (User $record): string => __('filament-resources.user.transfer_root_description', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('filament-resources.user.action_transfer_root'))
            ->schema([
                TextInput::make('password')
                    ->label(__('filament-resources.user.transfer_root_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),
            ])
            ->action(function (User $record): void {
                // Checked again: the page may have been open for a while
                if (! static::isRootUserSignedIn() || static::getRefusal($record) !== null) {
                    $this->halt();
                }

                $record->makeRootUser();

                Notification::make()
                    ->success()
                    ->title(__('filament-resources.user.transfer_root_done', ['name' => $record->name]))
                    ->send();
            });
    }

    protected static function isRootUserSignedIn(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isRootUser();
    }

    /**
     * Why the user cannot become the root user, or null when they can.
     */
    public static function getRefusal(User $user): ?string
    {
        if (! app()->environment('production')) {
            return __('filament-resources.user.transfer_root_not_in_production');
        }

        if (! $user->canActuallyLogIn() || ! $user->hasRole('super_admin')) {
            return __('filament-resources.user.transfer_root_requirements');
        }

        return null;
    }
}
