<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Helpers for the attributes translated in every language of the application (see `spatie/laravel-translatable`).
 */
class Translations
{
    /**
     * A fieldset with an input per language, filled and saved as the translations of the attribute (e.g. `name.it`, like
     * the tag names in `TagResource`: the record gives all of them to the form). The language of the user comes first
     * and is required; the others are optional and, when empty, fall back to another translation.
     *
     * @param  ?string  $placeholderKey  The translation key of the placeholder, shown in the language of each input
     */
    public static function inputs(string $attribute, string $label, ?string $placeholderKey = null, int $maxLength = 255): Fieldset
    {
        return Fieldset::make($label)
            ->columns(['default' => 1, 'sm' => count(static::getLocales()), 'lg' => count(static::getLocales())])
            ->schema(array_map(
                fn (string $locale): TextInput => TextInput::make("{$attribute}.{$locale}")
                    ->label(static::getLocales()[$locale])
                    // The example in the language of each input
                    ->placeholder($placeholderKey ? __($placeholderKey, [], $locale) : null)
                    ->required($locale === app()->getLocale())
                    ->maxLength($maxLength),
                static::getSortedLocales(),
            ));
    }

    /**
     * The character escaping the wildcards in `search()`: not a backslash, which MySQL and SQLite read differently.
     */
    private const LIKE_ESCAPE = '!';

    /**
     * Search the attribute in every language, so that a translation missing in the language of the user (shown in
     * another one) is found too.
     *
     * Case-insensitive on every database: MySQL compares the values read from JSON as binary strings, so both sides
     * are lowercased. The `%` and `_` typed are searched as they are, not as wildcards.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function search(Builder $query, string $attribute, string $search): Builder
    {
        $escape = self::LIKE_ESCAPE;
        $pattern = '%'.str_replace([$escape, '%', '_'], [$escape.$escape, $escape.'%', $escape.'_'], mb_strtolower($search)).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $query) use ($attribute, $pattern, $grammar, $escape): void {
            foreach (array_keys(static::getLocales()) as $locale) {
                $query->orWhereRaw('lower('.$grammar->wrap("{$attribute}->{$locale}").") like ? escape '{$escape}'", [$pattern]);
            }
        });
    }

    /**
     * The language chosen (e.g. in an import or export window) when it is one of the application, otherwise the current
     * one.
     */
    public static function resolveLocale(mixed $locale): string
    {
        return is_string($locale) && array_key_exists($locale, static::getLocales()) ? $locale : app()->getLocale();
    }

    /**
     * The languages of the application, by code.
     *
     * @return array<string, string>
     */
    public static function getLocales(): array
    {
        /** @var array<string, string> */
        return config('app.available_locales');
    }

    /**
     * The codes of the languages of the application, the language of the user first.
     *
     * @return list<string>
     */
    public static function getSortedLocales(): array
    {
        $locales = array_keys(static::getLocales());
        $current = app()->getLocale();

        return in_array($current, $locales, true)
            ? [$current, ...array_values(array_diff($locales, [$current]))]
            : $locales;
    }
}
