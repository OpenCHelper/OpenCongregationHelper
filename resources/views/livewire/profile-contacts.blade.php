<x-action-section>
    <x-slot name="title">
        {{ __('profile.contacts.section_title') }}
    </x-slot>
    <x-slot name="description">
        {{ __('profile.contacts.section_description') }}
    </x-slot>
    <x-slot name="content">
        {{ $this->table }}
    </x-slot>
</x-action-section>
