{{--
    Card of a record shown on phones instead of the columns of the table (see `RecordCardColumn`). It renders the columns
    of the table that are enabled in the columns menu, so that they look and behave as in the table.
--}}
@php
    $record = $getRecord();
    ['heading' => $headingColumns, 'aside' => $asideColumns, 'fullWidth' => $fullWidthColumns, 'details' => $detailColumns] = $getCardColumns();
    $detailColumns = array_filter($detailColumns, fn ($column): bool => $hasDetailValue($column->record($record)));
@endphp

<div class="fi-ta-record-card flex w-full flex-col gap-2 pt-1.5">
    @if (filled($headingColumns) || filled($asideColumns))
        <div class="flex items-start justify-between gap-3">
            <div class="fi-ta-record-card-heading min-w-0 flex-1">
                @foreach ($headingColumns as $column)
                    <div>{{ $column->record($record) }}</div>
                @endforeach
            </div>

            @if (filled($asideColumns))
                <div class="flex shrink-0 flex-col items-end gap-1">
                    @foreach ($asideColumns as $column)
                        <div>{{ $column->record($record) }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @foreach ($fullWidthColumns as $column)
        <div>{{ $column->record($record) }}</div>
    @endforeach

    @if (filled($detailColumns))
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            @foreach ($detailColumns as $column)
                <div class="min-w-0">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $column->getLabel() }}</dt>
                    <dd>{{ $column->record($record) }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
