<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ArtisanScheduledController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param Request $request
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
            $exitCode = Artisan::call("schedule:run", $params);
        } catch (Exception $e) {
            Log::error('Error executing Artisan command "schedule:run" with params: ' . json_encode($params) . ' - ' . $e->getMessage());
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

        return response()->json(array(
            'success' => true,
            'output' => $output,
            'returnCode' => $exitCode,
        ));
    }
}
