<?php

namespace App\Http\Controllers;

use App\Settings\GeneralSettings;
use App\Support\Installation;
use Illuminate\Http\JsonResponse;

/**
 * Web app manifest, which makes the app installable on mobile and desktop as a Progressive Web App.
 */
class WebManifestController extends Controller
{
    /**
     * Default color of the browser UI around the installed app (amber-700, as the brand mark of the home page), while
     * no primary color is chosen on the Settings page.
     */
    public const THEME_COLOR = '#b45309';

    /**
     * Color of the splash screen shown while the installed app starts (stone-50, as the home page).
     */
    public const BACKGROUND_COLOR = '#fafaf9';

    /**
     * Get the color of the browser UI around the installed app: the primary color chosen on the Settings page, if any
     * (none before the installation, while the settings cannot be read yet).
     */
    public static function themeColor(): string
    {
        if (Installation::needsSetup()) {
            return self::THEME_COLOR;
        }

        return app(GeneralSettings::class)->primary_color ?? self::THEME_COLOR;
    }

    public function __invoke(): JsonResponse
    {
        return response()->json($this->manifest(), headers: [
            'Content-Type' => 'application/manifest+json',
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     short_name: string,
     *     description: string,
     *     lang: string,
     *     start_url: string,
     *     scope: string,
     *     display: string,
     *     theme_color: string,
     *     background_color: string,
     *     icons: list<array{src: string, sizes: string, type: string, purpose: string}>,
     * }
     */
    private function manifest(): array
    {
        $appName = config('app.name');

        return [
            'id' => '/app',
            'name' => $appName,
            'short_name' => $appName,
            'description' => __('welcome.meta_description'),
            'lang' => str_replace('_', '-', app()->getLocale()),
            'start_url' => '/app',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => self::themeColor(),
            'background_color' => self::BACKGROUND_COLOR,
            'icons' => [
                ['src' => asset('icons/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }
}
