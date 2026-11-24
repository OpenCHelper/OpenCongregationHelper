{{--
    AI assistants connected to the MCP server (see `App\Livewire\ProfileAiAssistants`).
--}}
<div>
    @if ($enabled)
        <x-filament::section
            aside
            :heading="__('ai-assistants.profile.title')"
            :description="__('ai-assistants.profile.description')"
        >
            <div class="app-ai-assistants space-y-6 text-sm text-gray-600 dark:text-gray-400">
                <div class="space-y-2">
                    <p class="font-medium text-gray-950 dark:text-white">{{ __('ai-assistants.profile.server_url') }}</p>

                    <div x-data="{ copied: false }" class="flex items-center gap-2">
                        <code class="min-w-0 flex-1 truncate rounded-lg bg-gray-100 px-3 py-2 font-mono text-xs text-gray-950 dark:bg-white/5 dark:text-white">{{ $serverUrl }}</code>

                        <x-filament::icon-button
                            icon="heroicon-o-clipboard-document"
                            :label="__('ai-assistants.profile.copy')"
                            x-on:click="window.navigator.clipboard.writeText(@js($serverUrl)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        />
                        <span x-show="copied" x-cloak class="text-success-600 dark:text-success-400">{{ __('ai-assistants.profile.copied') }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="font-medium text-gray-950 dark:text-white">{{ __('ai-assistants.profile.how_to') }}</p>
                    <ul class="list-disc space-y-1 ps-5">
                        <li>{!! __('ai-assistants.profile.how_to_claude') !!}</li>
                        <li>{!! __('ai-assistants.profile.how_to_chatgpt') !!}</li>
                        <li>{!! __('ai-assistants.profile.how_to_other') !!}</li>
                    </ul>
                    <p>{{ __('ai-assistants.profile.permissions') }}</p>
                </div>

                <div class="space-y-2">
                    <p class="font-medium text-gray-950 dark:text-white">{{ __('ai-assistants.profile.connected') }}</p>

                    @forelse ($connections as $connection)
                        <div wire:key="ai-assistant-{{ $connection['client_id'] }}" class="flex items-center justify-between gap-4 rounded-lg px-3 py-2 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-950 dark:text-white">{{ $connection['name'] }}</p>
                                <p class="text-xs">
                                    {{ __('ai-assistants.profile.connected_at', ['date' => $connection['connected_at']->translatedFormat('j M Y')]) }}
                                </p>
                            </div>

                            <x-filament::button
                                color="danger"
                                size="sm"
                                outlined
                                wire:click="disconnect(@js($connection['client_id']))"
                                wire:confirm="{{ __('ai-assistants.profile.disconnect_confirm', ['name' => $connection['name']]) }}"
                            >
                                {{ __('ai-assistants.profile.disconnect') }}
                            </x-filament::button>
                        </div>
                    @empty
                        <p>{{ __('ai-assistants.profile.none') }}</p>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @endif
</div>
