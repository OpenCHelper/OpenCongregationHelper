<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\AiAssistantConnections;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Section of the profile to connect AI assistants (e.g. ChatGPT, Claude) to the MCP server of the app, and to
 * disconnect the connected ones. Shown only while the super administrators allow them (see the Security settings).
 */
class ProfileAiAssistants extends Component
{
    /**
     * Disconnect one of the assistants of the user, which must be authorized again to reconnect.
     */
    public function disconnect(string $clientId): void
    {
        AiAssistantConnections::revoke($this->user(), $clientId);

        Notification::make()
            ->title(__('ai-assistants.profile.disconnected'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        $enabled = AiAssistantConnections::enabled();

        return view('livewire.profile-ai-assistants', [
            'enabled' => $enabled,
            'serverUrl' => $enabled ? AiAssistantConnections::serverUrl() : null,
            'connections' => $enabled ? AiAssistantConnections::forUser($this->user()) : collect(),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
