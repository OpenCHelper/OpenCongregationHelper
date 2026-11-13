<?php

use App\Models\Tag;

return [

    /*
     * The given function generates a URL friendly "slug" from the tag name property before saving it.
     * Defaults to Str::slug (https://laravel.com/docs/master/helpers#method-str-slug)
     */
    'slugger' => null,

    /*
     * The fully qualified class name of the tag model.
     */
    'tag_model' => Tag::class,

    /*
     * The locale tag names and slugs are stored and looked up in, whatever the interface
     * language of the user (see App\Models\Tag::getLocale()). Changing it requires
     * rewriting the existing tags (see the `store_tag_names_in_a_single_locale` migration).
     */
    'locale' => 'en',

];
