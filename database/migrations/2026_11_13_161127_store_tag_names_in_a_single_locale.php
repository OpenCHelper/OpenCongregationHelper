<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Store the names of the existing tags in the single locale of `config('tags.locale')`.
 *
 * The tags used to be stored and looked up in the interface language of the user, so the same tag
 * could be stored twice (e.g. `{"en": "x"}` and `{"it": "x"}`). This migration adds the name in the
 * tags locale to the tags that miss it, then merges the tags with the same name and type into the
 * oldest one, moving their links to it without duplicating them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $locale = config('tags.locale', 'en');

        DB::transaction(function () use ($locale): void {
            /** @var array<string, int> $survivingTagIds the oldest tag of each type and name */
            $survivingTagIds = [];

            foreach (DB::table('tags')->orderBy('id')->get(['id', 'name', 'slug', 'type']) as $tag) {
                $names = $this->decodeTranslations($tag->name);
                $slugs = $this->decodeTranslations($tag->slug);
                $name = $this->nameInLocale($names, $locale);

                if ($name === null) {
                    continue;
                }

                $key = json_encode([$tag->type, $name]);

                if (isset($survivingTagIds[$key])) {
                    $this->mergeTagInto($tag->id, $survivingTagIds[$key]);

                    continue;
                }

                $survivingTagIds[$key] = $tag->id;

                if (($names[$locale] ?? null) !== $name || blank($slugs[$locale] ?? null)) {
                    DB::table('tags')->where('id', $tag->id)->update([
                        'name' => json_encode([$locale => $name] + $names),
                        'slug' => json_encode([$locale => Str::slug($name)] + $slugs),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * The merged tags cannot be split again, and the names added in the tags locale are harmless.
     */
    public function down(): void
    {
        //
    }

    /**
     * Decode a translatable JSON column.
     *
     * @return array<string, string>
     */
    private function decodeTranslations(?string $json): array
    {
        $translations = json_decode($json ?? '', true);

        return is_array($translations) ? array_filter($translations, fn ($value) => is_string($value) && $value !== '') : [];
    }

    /**
     * Get the name of a tag in the given locale, falling back to the default and fallback locales of the
     * application, then to any other locale.
     *
     * @param  array<string, string>  $names
     */
    private function nameInLocale(array $names, string $locale): ?string
    {
        foreach ([$locale, config('app.fallback_locale'), config('app.locale')] as $candidateLocale) {
            if (isset($names[$candidateLocale])) {
                return $names[$candidateLocale];
            }
        }

        return reset($names) ?: null;
    }

    /**
     * Move the links of a tag to another one (skipping those it already has), then delete it.
     */
    private function mergeTagInto(int $duplicateTagId, int $survivingTagId): void
    {
        $linkedTaggables = DB::table('taggables')
            ->where('tag_id', $survivingTagId)
            ->get(['taggable_type', 'taggable_id'])
            ->mapWithKeys(fn (object $link): array => ["{$link->taggable_type}#{$link->taggable_id}" => true])
            ->all();

        foreach (DB::table('taggables')->where('tag_id', $duplicateTagId)->get(['taggable_type', 'taggable_id']) as $link) {
            $links = DB::table('taggables')
                ->where('tag_id', $duplicateTagId)
                ->where('taggable_type', $link->taggable_type)
                ->where('taggable_id', $link->taggable_id);

            if (isset($linkedTaggables["{$link->taggable_type}#{$link->taggable_id}"])) {
                $links->delete();

                continue;
            }

            $links->update(['tag_id' => $survivingTagId]);
            $linkedTaggables["{$link->taggable_type}#{$link->taggable_id}"] = true;
        }

        DB::table('tags')->where('id', $duplicateTagId)->delete();
    }
};
