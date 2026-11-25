<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

/**
 * Card of a record, shown on phones instead of the columns of a table stacked with `stackedOnMobile()`.
 *
 * It renders the enabled columns of the table, so that they look and behave as in the table: the heading columns at the
 * top, the aside ones on their right, the full-width ones below and all the others as labelled details. Add it with
 * `RecordCardColumn::addTo()`, which also hides the other columns on phones.
 */
class RecordCardColumn extends ViewColumn
{
    protected string $view = 'filament.tables.columns.record-card';

    /** @var list<string> */
    protected array $headingColumns = [];

    /** @var list<string> */
    protected array $asideColumns = [];

    /** @var list<string> */
    protected array $fullWidthColumns = [];

    /** @var list<string> */
    protected array $excludedColumns = [];

    protected bool $hidesEmptyDetails = false;

    public static function getDefaultName(): ?string
    {
        return 'card';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            // Without a label, so that it's not listed in the columns menu: it can't be hidden anyway
            ->label('')
            ->extraCellAttributes(['class' => 'fi-ta-cell-without-stacked-label'])
            ->hiddenFrom('sm');
    }

    /**
     * Add the card to the table, stack the table on phones and hide its other columns there. Call it after the columns
     * of the table are set, e.g. with `->tap(fn (Table $table) => RecordCardColumn::make()->heading('name')->addTo($table))`.
     */
    public function addTo(Table $table): void
    {
        foreach ($table->getColumns() as $column) {
            // Columns already hidden on phones (e.g. with `visibleFrom('lg')`) keep their breakpoint
            if (blank($column->getVisibleFrom()) && blank($column->getHiddenFrom())) {
                $column->visibleFrom('sm');
            }
        }

        // In its own group when the table has groups: the cell above an ungrouped column in the row of the groups is
        // shown at any width, and would shift the groups on larger screens. The group, without a label, keeps the card
        // out of the columns menu, so the card needs one: in a group, a column without a label can be toggled
        $card = $table->hasColumnGroups()
            ? ColumnGroup::make('', [$this->label($table->getModelLabel())])->hiddenFrom('sm')
            : $this;

        // Last, so that on larger screens the first visible column keeps the padding and the summary label of the table
        $table
            ->stackedOnMobile()
            ->pushColumns([$card]);
    }

    /**
     * The columns at the top of the card, e.g. the name of the record.
     */
    public function heading(string ...$columns): static
    {
        $this->headingColumns = $columns;

        return $this;
    }

    /**
     * The columns on the right of the heading, e.g. a status.
     */
    public function aside(string ...$columns): static
    {
        $this->asideColumns = $columns;

        return $this;
    }

    /**
     * The columns that take the full width of the card without a label, e.g. lists of badges.
     */
    public function fullWidth(string ...$columns): static
    {
        $this->fullWidthColumns = $columns;

        return $this;
    }

    /**
     * The columns not shown in the card, e.g. because they repeat the heading.
     */
    public function except(string ...$columns): static
    {
        $this->excludedColumns = $columns;

        return $this;
    }

    /**
     * Also leave out the details that are zero or would only show their placeholder, e.g. the publications that a
     * publisher doesn't receive.
     */
    public function withoutEmptyDetails(bool $condition = true): static
    {
        $this->hidesEmptyDetails = $condition;

        return $this;
    }

    /**
     * Whether a detail is worth showing for the record: details without a value are left out, unless they have a
     * placeholder telling what that means (e.g. "Not assigned").
     */
    public function hasDetailValue(Column $column): bool
    {
        $state = $column->getState();

        if ($this->hidesEmptyDetails) {
            return filled($state) && ($state !== 0);
        }

        return filled($state) || filled($column->getPlaceholder());
    }

    /**
     * @return array{heading: list<Column>, aside: list<Column>, fullWidth: list<Column>, details: list<Column>}
     */
    public function getCardColumns(): array
    {
        $columns = array_filter(
            $this->getTable()->getVisibleColumns(),
            fn (Column $column): bool => $column->getName() !== $this->getName()
                && ! in_array($column->getName(), $this->excludedColumns, true),
        );

        $pick = fn (array $names): array => array_values(array_filter(
            array_map(fn (string $name): ?Column => $columns[$name] ?? null, $names),
        ));

        return [
            'heading' => $pick($this->headingColumns),
            'aside' => $pick($this->asideColumns),
            'fullWidth' => $pick($this->fullWidthColumns),
            'details' => array_values(array_diff_key($columns, array_flip([
                ...$this->headingColumns,
                ...$this->asideColumns,
                ...$this->fullWidthColumns,
            ]))),
        ];
    }
}
