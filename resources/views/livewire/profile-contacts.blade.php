<div>
    {{-- Not contained: the table is already a card, so it must not be wrapped in the card of the section. --}}
    <x-filament::section
        aside
        :contained="false"
        :heading="__('profile.contacts.section_title')"
        :description="__('profile.contacts.section_description')"
    >
        {{ $this->table }}
    </x-filament::section>
</div>
