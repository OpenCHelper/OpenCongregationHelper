@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div>
    <x-filament::section
        aside
        :heading="__('profile.two_factor_authentication.title')"
        :description="__('profile.two_factor_authentication.description')"
    >
        <div class="grid gap-6">
            <div class="grid gap-y-2">
                <h3 class="flex items-center gap-x-2 text-sm font-semibold text-gray-950 dark:text-white">
                    @if ($this->enabled && ! $showingConfirmation)
                        <x-filament::icon :icon="Heroicon::OutlinedShieldCheck" class="size-5 text-success-500 dark:text-success-400" />
                    @endif

                    @if ($this->enabled)
                        @if ($showingConfirmation)
                            {{ __('profile.two_factor_authentication.finish_enabling') }}
                        @else
                            {{ __('profile.two_factor_authentication.enabled') }}
                        @endif
                    @else
                        {{ __('profile.two_factor_authentication.not_enabled') }}
                    @endif
                </h3>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('profile.two_factor_authentication.when_enabled_description') }}
                </p>
            </div>

            @if ($this->enabled)
                @if ($showingQrCode)
                    <div class="grid gap-y-4">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            @if ($showingConfirmation)
                                {{ __('profile.two_factor_authentication.scan_qr_code') }}
                            @else
                                {{ __('profile.two_factor_authentication.qr_code_enabled') }}
                            @endif
                        </p>

                        <div class="w-fit rounded-lg bg-white p-2 ring-1 ring-gray-950/5">
                            {!! $this->user->twoFactorQrCodeSvg() !!}
                        </div>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('profile.two_factor_authentication.setup_key') }}:
                            <code class="font-mono text-gray-950 dark:text-white">{{ decrypt($this->user->two_factor_secret) }}</code>
                        </p>

                        @if ($showingConfirmation)
                            <x-filament-forms::field-wrapper
                                id="code"
                                :label="__('profile.two_factor_authentication.code')"
                                required
                                state-path="code"
                                class="max-w-xs"
                            >
                                <x-filament::input.wrapper :valid="! $errors->has('code')">
                                    <x-filament::input
                                        id="code"
                                        type="text"
                                        name="code"
                                        inputmode="numeric"
                                        autofocus
                                        autocomplete="one-time-code"
                                        wire:model="code"
                                        wire:keydown.enter="confirmTwoFactorAuthentication"
                                    />
                                </x-filament::input.wrapper>
                            </x-filament-forms::field-wrapper>
                        @endif
                    </div>
                @endif

                @if ($showingRecoveryCodes)
                    <div class="grid gap-y-4">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('profile.two_factor_authentication.recovery_codes_description') }}
                        </p>

                        <div class="grid gap-1 rounded-lg bg-gray-50 px-4 py-3 font-mono text-sm text-gray-950 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-white dark:ring-white/10">
                            @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                                <div>{{ $code }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex flex-wrap items-center gap-3">
                @if (! $this->enabled)
                    <x-confirms-password wire:then="enableTwoFactorAuthentication">
                        <x-filament::button :icon="Heroicon::ShieldCheck">
                            {{ __('profile.two_factor_authentication.enable') }}
                        </x-filament::button>
                    </x-confirms-password>
                @else
                    @if ($showingRecoveryCodes)
                        <x-confirms-password wire:then="regenerateRecoveryCodes">
                            <x-filament::button color="gray">
                                {{ __('profile.two_factor_authentication.regenerate_recovery_codes') }}
                            </x-filament::button>
                        </x-confirms-password>
                    @elseif ($showingConfirmation)
                        <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                            <x-filament::button>
                                {{ __('profile.two_factor_authentication.confirm') }}
                            </x-filament::button>
                        </x-confirms-password>
                    @else
                        <x-confirms-password wire:then="showRecoveryCodes">
                            <x-filament::button color="gray">
                                {{ __('profile.two_factor_authentication.show_recovery_codes') }}
                            </x-filament::button>
                        </x-confirms-password>
                    @endif

                    @if ($showingConfirmation)
                        <x-confirms-password wire:then="disableTwoFactorAuthentication">
                            <x-filament::button color="gray">
                                {{ __('profile.two_factor_authentication.cancel') }}
                            </x-filament::button>
                        </x-confirms-password>
                    @else
                        <x-confirms-password wire:then="disableTwoFactorAuthentication">
                            <x-filament::button color="danger">
                                {{ __('profile.two_factor_authentication.disable') }}
                            </x-filament::button>
                        </x-confirms-password>
                    @endif
                @endif
            </div>
        </x-slot>
    </x-filament::section>
</div>
