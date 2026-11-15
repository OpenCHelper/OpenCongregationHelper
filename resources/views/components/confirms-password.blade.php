@props(['title' => __('Confirm Password'), 'content' => __('For your security, please confirm your password to continue.'), 'button' => __('Confirm')])

@php
    $confirmableId = md5($attributes->wire('then'));
@endphp

<span
    {{ $attributes->wire('then') }}
    x-data
    x-ref="span"
    x-on:click="$wire.startConfirmingPassword('{{ $confirmableId }}')"
    x-on:password-confirmed.window="setTimeout(() => $event.detail.id === '{{ $confirmableId }}' && $refs.span.dispatchEvent(new CustomEvent('then', { bubbles: false })), 250);"
>
    {{ $slot }}
</span>

@once
{{-- The modal follows `$confirmingPassword` of the `ConfirmsPasswords` trait of Jetstream, which is toggled on the server. --}}
<x-filament::modal
    id="confirm-password"
    :icon="Filament\Support\Icons\Heroicon::OutlinedLockClosed"
    :heading="$title"
    :description="$content"
    alignment="center"
    footer-actions-alignment="center"
    width="md"
    x-effect="if ($wire.confirmingPassword) { isOpen || open() } else if (isOpen) { close() }"
    x-on:modal-closed="if ($wire.confirmingPassword) { $wire.stopConfirmingPassword() }"
    wire:submit.prevent="confirmPassword"
>
    <x-filament-forms::field-wrapper
        id="confirmable_password"
        :label="__('Password')"
        label-sr-only
        state-path="confirmable_password"
    >
        <x-filament::input.wrapper :valid="! $errors->has('confirmable_password')">
            <x-filament::input
                id="confirmable_password"
                type="password"
                autocomplete="current-password"
                :placeholder="__('Password')"
                wire:model="confirmablePassword"
                x-on:confirming-password.window="setTimeout(() => $el.focus(), 250)"
            />
        </x-filament::input.wrapper>
    </x-filament-forms::field-wrapper>

    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="close()">
            {{ __('Cancel') }}
        </x-filament::button>

        <x-filament::button type="submit" form="confirmPassword" dusk="confirm-password-button">
            {{ $button }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>
@endonce
