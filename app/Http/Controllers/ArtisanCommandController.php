<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class ArtisanCommandController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function execute(Request $request, ?string $command = null)
    {
        $command = $command ?? $request->input('command');
        $params = $request->input('params');

        if (! is_string($command) || (! is_null($params) && ! is_string($params))) {
            return response()->json([
                'success' => false,
                'output' => "The 'command' and 'params' fields must be strings.",
                'returnCode' => -1,
            ], status: 422);
        }

        $params = (string) $params;
        Log::info("Executing Artisan command: '$command' with params: ".$params);

        if ($command == 'test-endpoint') {
            return response()->json([
                'success' => true,
                'output' => "Endpoint is up & running!\n\nCommand.....: >>> $command <<<\nParameters..: >>> $params <<<",
                'returnCode' => 0,
            ]);
        }

        $availableCommands = array_keys(Artisan::all());

        if (! in_array($command, $availableCommands)) {
            Log::error('Artisan command not found: '.$command);

            return response()->json([
                'success' => false,
                'output' => "Command '$command' is not a valid artisan command",
                'returnCode' => -1,
            ], status: 404);
        }

        // A buffer of its own: commands such as `config:cache` (run by `och:post-deploy`) boot a fresh application, whose
        // Artisan has none of the output of this call
        $buffer = new BufferedOutput;

        try {
            $exitCode = Artisan::call("$command $params", [], $buffer);
        } catch (Exception $e) {
            Log::error('Error executing Artisan command: '.$command.' with params: '.json_encode($params).' - '.$e->getMessage());

            return response()->json([
                'success' => false,
                'output' => $e->getMessage(),
                'returnCode' => -1,
            ], status: 400);
        }

        $output = $buffer->fetch();

        Log::info('Artisan command output: '.PHP_EOL.implode("\n", array_map(function ($line) {
            return '    | '.$line;
        }, explode("\n", $output))));

        return response()->json([
            'success' => true,
            'output' => $output,
            'returnCode' => $exitCode,
        ]);
    }

    public function list()
    {
        $availableCommands = array_keys(Artisan::all());

        return response()->json([
            'success' => true,
            'output' => $availableCommands,
        ]);
    }
}
