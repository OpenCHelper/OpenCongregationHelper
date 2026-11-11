<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ArtisanScheduledController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke()
    {
        $params = [
            '--no-interaction' => true,
            '--no-ansi' => true,
            // '--quiet' => true,
        ];

        try {
            // Log::info('Executing Artisan command "schedule:run" with params: ' . json_encode($params));
            // This endpoint is called on the `artisan.` subdomain: build the URLs of the
            // processed jobs (e.g. links in notification emails) from the application URL.
            $appUrl = rtrim((string) config('app.url'), '/');
            URL::forceRootUrl($appUrl);
            URL::forceScheme((string) parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');

            $exitCode = Artisan::call('schedule:run', $params);
        } catch (Exception $e) {
            Log::error('Error executing Artisan command "schedule:run" with params: '.json_encode($params).' - '.$e->getMessage());

            return response()->json([
                'success' => false,
                'output' => $e->getMessage(),
                'returnCode' => -1,
            ], status: 400);
        }

        $output = Artisan::output();

        // Log::info('Artisan command output: ' . PHP_EOL . implode("\n", array_map(function ($line) {
        //     return '    | ' . $line;
        // }, explode("\n", $output))));

        return response()->json([
            'success' => true,
            'output' => $output,
            'returnCode' => $exitCode,
        ]);
    }
}
