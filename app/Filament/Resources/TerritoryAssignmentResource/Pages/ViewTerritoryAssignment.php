<?php

namespace App\Filament\Resources\TerritoryAssignmentResource\Pages;

use App\Filament\Resources\TerritoryAssignmentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ViewTerritoryAssignment extends ViewRecord
{
    protected static string $resource = TerritoryAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                Action::make('share')
                    ->color('primary')
                    ->label(__('filament-resources.territory_assignment.action_copy.label'))
                    ->tooltip(__('filament-resources.territory_assignment.action_copy.tooltip'))
                    ->icon('heroicon-o-share')
                    ->modalHeading(__('filament-resources.territory_assignment.share_modal.title'))
                    ->modalDescription(__('filament-resources.territory_assignment.share_modal.description'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament-resources.territory_assignment.share_modal.close'))
                    ->fillForm(fn () => [
                        'share_url' => $this->record->share()->getShareableUrl(),
                    ])
                    ->form([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('share_url')
                                    ->label(__('filament-resources.territory_assignment.share_modal.url_label'))
                                    ->visible(fn ($get) => !empty($get('share_url')))
                                    ->readonly()
                                    ->suffixActions([
                                        Forms\Components\Actions\Action::make('copy_url_suffix')
                                            ->icon('heroicon-o-clipboard-document')
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
                                                    ->icon('heroicon-o-clipboard-document-check')
                                            ),
                                    ]),
                            ])
                    ])
                    ->extraModalFooterActions([
                        Action::make('stop_sharing')
                            ->requiresConfirmation()
                            ->cancelParentActions()
                            ->label(__('filament-resources.territory_assignment.share_modal.stop_sharing'))
                            ->icon('heroicon-o-no-symbol')
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
            ->icon('heroicon-m-ellipsis-vertical')
            ->size(\Filament\Support\Enums\ActionSize::Small)
            ->color('gray')
            ->button(),
        ];
    }
}
