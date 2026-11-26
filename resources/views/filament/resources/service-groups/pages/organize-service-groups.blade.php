@php
    use App\Filament\Resources\ServiceGroups\Pages\ManageServiceGroupMembers;
    use App\Filament\Resources\ServiceGroups\ServiceGroupResource;

    // A column for the publishers without a group, followed by a column for each group
    $columns = collect([[
        'key' => 'none',
        'id' => null,
        'title' => __('filament-resources.service_group.board_without_group'),
        'group' => null,
        'publishers' => $this->publishersWithoutGroup,
    ]])->concat($this->serviceGroups->map(fn ($serviceGroup) => [
        'key' => $serviceGroup->id,
        'id' => $serviceGroup->id,
        'title' => $serviceGroup->name,
        'group' => $serviceGroup,
        'publishers' => $serviceGroup->members,
    ]));
@endphp

<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('filament-resources.service_group.board_help') }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach ($columns as $column)
            <x-filament::section
                compact
                wire:key="service-group-column-{{ $column['key'] }}"
                @class(['opacity-90' => $column['id'] === null])
            >
                <x-slot name="heading">
                    @if ($column['group'])
                        <a
                            href="{{ ServiceGroupResource::getUrl('view', ['record' => $column['group']]) }}"
                            class="hover:underline"
                        >{{ $column['title'] }}</a>
                    @else
                        {{ $column['title'] }}
                    @endif
                </x-slot>

                <x-slot name="afterHeader">
                    <x-filament::badge color="gray">
                        {{ $column['publishers']->count() }}
                    </x-filament::badge>
                </x-slot>

                <ul
                    x-sortable
                    x-sortable-group="service-groups"
                    data-sortable-animation-duration="150"
                    x-on:add.stop="$wire.movePublisher(Number($event.item.getAttribute('x-sortable-item')), @js($column['id']))"
                    class="flex min-h-24 flex-col gap-1.5"
                    data-service-group="{{ $column['key'] }}"
                >
                    @forelse ($column['publishers'] as $publisher)
                        @php
                            $roleInGroup = $column['group'] ? ManageServiceGroupMembers::getRoleInGroup($column['group'], $publisher) : null;
                        @endphp

                        <li
                            x-sortable-item="{{ $publisher->id }}"
                            x-sortable-handle
                            wire:key="publisher-{{ $publisher->id }}"
                            class="flex cursor-grab items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/5 active:cursor-grabbing dark:bg-white/5 dark:ring-white/10"
                        >
                            <x-filament::icon
                                icon="heroicon-m-bars-2"
                                class="size-4 shrink-0 text-gray-400 dark:text-gray-500"
                            />

                            <span class="grow break-words text-gray-950 dark:text-white">
                                {{ $publisher->getFullName() }}
                            </span>

                            @if ($roleInGroup)
                                <x-filament::badge size="sm" color="primary" class="shrink-0">
                                    {{ $roleInGroup }}
                                </x-filament::badge>
                            @endif
                        </li>
                    @empty
                        <li class="flex min-h-20 items-center justify-center rounded-lg border-2 border-dashed border-gray-200 px-3 text-center text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">
                            {{ __('filament-resources.service_group.board_empty') }}
                        </li>
                    @endforelse
                </ul>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
