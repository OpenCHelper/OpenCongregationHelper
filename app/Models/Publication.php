<?php

namespace App\Models;

use App\Enums\PublicationCategory;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A publication that the publishers can receive on paper, e.g. The Watchtower.
 */
class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'short_name',
        'sort',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PublicationCategory::class,
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['name', 'category', 'short_name', 'is_active']);
    }

    /**
     * Get the copies of the publication received by the publishers.
     *
     * @return HasMany<PeriodicalSubscriptionItem, $this>
     */
    public function subscriptionItems(): HasMany
    {
        return $this->hasMany(PeriodicalSubscriptionItem::class);
    }

    /**
     * Sort the publications by category (as declared in `PublicationCategory`), then as chosen in their list.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        $cases = collect(PublicationCategory::cases())
            ->map(fn (PublicationCategory $category, int $index): string => "when '{$category->value}' then {$index}")
            ->implode(' ');

        // Wrapped by the grammar, which adds the table prefix (e.g. of the previews) that raw SQL would miss
        $column = $query->getQuery()->getGrammar()->wrap($this->qualifyColumn('category'));

        return $query
            ->orderByRaw("case {$column} {$cases} else ".count(PublicationCategory::cases()).' end')
            ->orderBy('sort')
            ->orderBy('name');
    }

    /**
     * Get the publications offered to the publishers, in their order.
     *
     * @return Collection<int, self>
     */
    public static function offered(): Collection
    {
        return static::query()->where('is_active', true)->ordered()->get();
    }

    /**
     * The name shown in the headers of the columns.
     */
    public function getShortLabel(): string
    {
        return filled($this->short_name) ? $this->short_name : $this->name;
    }
}
