<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the files of the `public` disk (e.g. the profile photos) when the web server cannot serve them through the
 * `public/storage` link, as on hostings without symbolic links (e.g. Altervista): their requests reach the application.
 */
class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        $disk = Storage::disk('public');

        // Hidden files (e.g. `.gitignore`) are not uploads
        $isHidden = collect(explode('/', $path))->contains(fn (string $segment): bool => str_starts_with($segment, '.'));

        try {
            abort_if($isHidden || ! $disk->fileExists($path), 404);
        } catch (PathTraversalDetected) {
            abort(404);
        }

        return $disk->response($path, headers: ['Cache-Control' => 'public, max-age=604800']);
    }
}
