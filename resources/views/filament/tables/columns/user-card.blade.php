{{--
    Card of a user in the table of `UserResource`, shown on phones instead of the columns. It renders the columns of the
    table that are enabled in the columns menu, so that they look and behave as in the table.
--}}
@php
    $record = $getRecord();
    $livewire = $getLivewire();
    $table = $livewire->getTable();

    $getEnabledColumns = fn (array $names): array => array_values(array_filter(
        array_map(fn (string $name) => $table->getColumn($name), $names),
        fn ($column): bool => $column && ! $livewire->isTableColumnToggledHidden($column->getName()),
    ));

    $badgeColumns = $getEnabledColumns(['departments', 'roles.name']);
    $detailColumns = $getEnabledColumns(['email_verified_at', 'two_factor_authentication', 'created_at', 'updated_at']);
@endphp

<div class="flex w-full flex-col gap-2">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-semibold text-gray-950 dark:text-white">{{ $record->getFullName() }}</p>
            <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $record->email }}</p>
        </div>

        @if ($accessColumn = $table->getColumn('can_login'))
            <div class="shrink-0" title="{{ $accessColumn->getLabel() }}">{{ $accessColumn->record($record) }}</div>
        @endif
    </div>

    @foreach ($badgeColumns as $column)
        <div>{{ $column->record($record) }}</div>
    @endforeach

    @if (filled($detailColumns))
        <dl class="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-1 text-sm">
            @foreach ($detailColumns as $column)
                <dt class="text-gray-500 dark:text-gray-400">{{ $column->getLabel() }}</dt>
                <dd>{{ $column->record($record) }}</dd>
            @endforeach
        </dl>
    @endif
</div>
