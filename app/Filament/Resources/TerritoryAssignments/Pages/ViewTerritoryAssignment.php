<?php

namespace App\Filament\Resources\TerritoryAssignments\Pages;

use App\Filament\Resources\TerritoryAssignments\Actions\RequestReturnAction;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewTerritoryAssignment extends ViewRecord
{
    protected static string $resource = TerritoryAssignmentResource::class;

    /**
     * The assignees can open their own assignments without being allowed to list them (e.g. from a notification): the
     * access to the assignment itself is still checked by the `view` policy, and the query of the resource only finds
     * the assignments the user can see (their own, unless allowed to list all of them).
     */
    public static function authorizeResourceAccess(): void
    {
        //
    }

    /**
     * The breadcrumbs of the list of the assignments, not linked for the users who cannot open it (e.g. an assignee
     * opening their own assignment).
     *
     * @return array<int|string, string>
     */
    public function getResourceBreadcrumbs(): array
    {
        $breadcrumbs = parent::getResourceBreadcrumbs();

        return static::getResource()::canAccess() ? $breadcrumbs : array_values($breadcrumbs);
    }

    /**
     * Whether the user can share the assignment, or stop sharing it: only those allowed to view the assignments, as
     * before the assignees could open their own (a public link must not be created, or revoked, by the assignee alone).
     */
    public static function canManageSharing(): bool
    {
        return Auth::user()?->can('View:TerritoryAssignment') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            RequestReturnAction::make(),
            ActionGroup::make([
                Action::make('share')
                    ->color('primary')
                    ->label(__('filament-resources.territory_assignment.action_copy.label'))
                    ->tooltip(__('filament-resources.territory_assignment.action_copy.tooltip'))
                    ->icon(Heroicon::OutlinedShare)
                    ->authorize(fn (): bool => static::canManageSharing())
                    ->modalHeading(__('filament-resources.territory_assignment.share_modal.title'))
                    ->modalDescription(__('filament-resources.territory_assignment.share_modal.description'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-resources.territory_assignment.share_modal.close'))
                    ->fillForm(fn () => [
                        'share_url' => $this->record->share()->getShareableUrl(),
                    ])
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('share_url')
                                    ->label(__('filament-resources.territory_assignment.share_modal.url_label'))
                                    ->visible(fn ($get) => ! empty($get('share_url')))
                                    ->readonly()
                                    ->suffixActions([
                                        Action::make('copy_url_suffix')
                                            ->icon(Heroicon::OutlinedClipboardDocument)
                                            ->tooltip(__('filament-resources.territory_assignment.share_modal.copy_button'))
                                            ->action(function ($action, $livewire, $get) {
                                                $url = $get('share_url');

                                                if (! $url) {
                                                    $action->halt();

                                                    return;
                                                }

                                                $livewire->js("
                                                    window.navigator.clipboard.writeText('$url').then(function() {
                                                        console.log('URL copied to clipboard');
                                                    }).catch(function(err) {
                                                        console.error('Failed to copy URL: ', err);
                                                        // Fallback method
                                                        const textArea = document.createElement('textarea');
                                                        textArea.value = '$url';
                                                        textArea.style.position = 'fixed';
                                                        textArea.style.left = '-999999px';
                                                        textArea.style.top = '-999999px';
                                                        document.body.appendChild(textArea);
                                                        textArea.focus();
                                                        textArea.select();
                                                        try {
                                                            document.execCommand('copy');
                                                            console.log('Fallback: URL copied to clipboard');
                                                        } catch (fallbackErr) {
                                                            console.error('Fallback: Failed to copy URL: ', fallbackErr);
                                                        }
                                                        document.body.removeChild(textArea);
                                                    });
                                                ");

                                                $action->success();
                                            })
                                            ->successNotification(
                                                Notification::make()
                                                    ->title(__('filament-resources.territory_assignment.action_copy.notifications.success.title'))
                                                    ->body(__('filament-resources.territory_assignment.action_copy.notifications.success.body'))
                                                    ->success()
                                                    ->duration(2000)
                                                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                                            ),
                                    ]),
                            ]),
                    ])
                    ->extraModalFooterActions([
                        Action::make('stop_sharing')
                            ->authorize(fn (): bool => static::canManageSharing())
                            ->requiresConfirmation()
                            ->cancelParentActions()
                            ->label(__('filament-resources.territory_assignment.share_modal.stop_sharing'))
                            ->icon(Heroicon::NoSymbol)
                            ->color('danger')
                            ->modalHeading(__('filament-resources.territory_assignment.share_modal.stop_sharing_confirm_title'))
                            ->modalDescription(__('filament-resources.territory_assignment.share_modal.stop_sharing_confirm_description'))
                            ->action(function ($action) {
                                if (! $this->record->shared) {
                                    $action->halt();

                                    return;
                                }

                                $this->record->shared->delete();
                                $this->record->refresh();
                                $action->success();
                            })
                            ->successNotification(
                                Notification::make()
                                    ->title(__('filament-resources.territory_assignment.share_modal.stop_sharing_success'))
                                    ->duration(2000)
                            ),
                    ]),
            ])
                ->label(__('filament-resources.territory_assignment.action_group.label'))
                ->icon(Heroicon::EllipsisVertical)
                ->size(Size::Small)
                ->color('gray')
                ->button(),
        ];
    }
}
