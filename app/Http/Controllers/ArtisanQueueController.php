<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ArtisanQueueController extends Controller
{

    /**
     * The maximum number of jobs that are allowed to process.
     *
     * @var int
     */
    private $max_jobs_limit = 10;

    /**
     * The maximum time in seconds that the queue worker is allowed to run.
     *
     * @var int
     */
    private $max_time_limit = 120;

    /**
     * The maximum timeout in seconds for each job.
     *
     * @var int
     */
    private $max_timeout_limit = 60;

    /**
     * The maximum number of attempts for each job.
     *
     * @var int
     */
    private $max_tries_limit = 5;

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        $max_jobs = request()->input('maxJobs', 1);
        $max_time = request()->input('maxTime', 60);
        $timeout = request()->input('timeout', 60);
        $tries = request()->input('tries', 1);

        // Ensure the parameters do not exceed the limits, nor are less than 1 (0 = infinite)
        if (!is_numeric($max_jobs) || $max_jobs < 1 || $max_jobs > $this->max_jobs_limit) {
            return response()->json([
                'success' => false,
                'output' => 'Invalid max_jobs parameter',
                'returnCode' => -1,
            ], status: 400);
        }
        if (!is_numeric($max_time) || $max_time < 1 || $max_time > $this->max_time_limit) {
            return response()->json([
                'success' => false,
                'output' => 'Invalid max_time parameter',
                'returnCode' => -1,
            ], status: 400);
        }
        if (!is_numeric($timeout) || $timeout < 1 || $timeout > $this->max_timeout_limit) {
            return response()->json([
                'success' => false,
                'output' => 'Invalid timeout parameter',
                'returnCode' => -1,
            ], status: 400);
        }
        if (!is_numeric($tries) || $tries < 1 || $tries > $this->max_tries_limit) {
            return response()->json([
                'success' => false,
                'output' => 'Invalid tries parameter',
                'returnCode' => -1,
            ], status: 400);
        }


        $params = [
            // '--json' => true,
            '--no-interaction' => true,
            '--no-ansi' => true,
            '--stop-when-empty' => true,
            '--max-jobs' => $max_jobs,
            '--max-time' => $max_time,
            '--timeout' => $timeout,
            '--tries' => $tries,
        ];
        
        try {
            // Log::info('Executing Artisan command: queue:work with params: ' . json_encode($params));
            $exitCode = Artisan::call("queue:work", $params);
        } catch (\Exception $e) {
            Log::error('Error executing Artisan command: queue:work with params: ' . json_encode($params) . ' - ' . $e->getMessage());
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
