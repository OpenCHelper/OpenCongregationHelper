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
use Spatie\Translatable\HasTranslations;

/**
 * A publication that the publishers can receive on paper, e.g. The Watchtower: requested once (e.g. a book), or
 * again every period when it is renewable (`renewal_months`). Its name is translated in every language of the
 * application, while its code is the same in all of them.
 */
class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    use HasTranslations;
    use LogsActivity;

    /**
     * The attributes translated in every language of the application.
     *
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'code',
        'renewal_months',
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
            'renewal_months' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['name', 'category', 'code', 'renewal_months', 'is_active']);
    }

    /**
     * Get the subscriptions to the publication, the stopped ones included.
     *
     * @return HasMany<PublicationSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(PublicationSubscription::class);
    }

    /**
     * Get the subscriptions to the publication still requested again every period.
     *
     * @return HasMany<PublicationSubscription, $this>
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->active();
    }

    /**
     * Get the requests of the publication.
     *
     * @return HasMany<PublicationRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PublicationRequest::class);
    }

    /**
     * Check if the publication is requested again every period (e.g. The Watchtower every month).
     */
    public function isRenewable(): bool
    {
        return (int) $this->renewal_months > 0;
    }

    /**
     * Get the renewable publications offered to the publishers, in their order: the ones received regularly.
     *
     * @return Collection<int, self>
     */
    public static function offeredRenewable(): Collection
    {
        return static::offered()->filter(fn (self $publication): bool => $publication->isRenewable())->values();
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
            ->orderBy($this->qualifyColumn('name->'.app()->getLocale()));
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
     * The label shown in the headers of the columns: the code, the same in every language.
     */
    public function getShortLabel(): string
    {
        return $this->code;
    }
}
