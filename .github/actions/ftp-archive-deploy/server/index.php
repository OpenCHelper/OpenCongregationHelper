<?php

/**
 * HTTP endpoint of the deployer of the space: `POST /_deployer/<version>/index.php` with a JSON body like
 * `{"action": "extract", "target": "pr-12", "offset": 0}`. See `SpaceDeployer` for the available actions.
 *
 * The `ftp-archive-deploy` action uploads it to `_deployer/<version>/` (a hash of these files), so that every run talks
 * to the version of the deployer matching its own client.
 *
 * Requests are authorized like the Artisan endpoint of the application: the client uploads a random token through FTP
 * in `tokens/<id>` (next to this file), sends it in the `X-Deployer-Token-Id` and `X-Deployer-Token` headers, and
 * deletes it afterwards.
 */

declare(strict_types=1);

require __DIR__.'/SpaceDeployer.php';

/**
 * @param  array<string, mixed>  $body
 */
function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    echo json_encode($body, JSON_UNESCAPED_SLASHES);

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

// The budget of every request is a fraction of the execution time limit of the server (read before trying to raise
// it), so that a batch always ends well before PHP stops it
$maxExecutionTime = (int) ini_get('max_execution_time');

$deployer = new SpaceDeployer(
    spacePath: dirname(__DIR__, 2),
    tokensPath: __DIR__.'/tokens',
    timeBudgetSeconds: SpaceDeployer::timeBudgetFor($maxExecutionTime),
    maxExecutionTime: $maxExecutionTime,
);

if (! $deployer->isAuthorized($_SERVER['HTTP_X_DEPLOYER_TOKEN_ID'] ?? null, $_SERVER['HTTP_X_DEPLOYER_TOKEN'] ?? null)) {
    respond(401, ['success' => false, 'error' => 'Unauthorized.']);
}

// Finish the current batch even if the client disconnects, and give it more time where the server allows it
ignore_user_abort(true);
@set_time_limit(60);

$input = json_decode((string) file_get_contents('php://input'), true);

try {
    respond(200, ['success' => true] + $deployer->handle(is_array($input) ? $input : []));
} catch (InvalidArgumentException $exception) {
    respond(422, ['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    respond(500, ['success' => false, 'error' => $exception->getMessage()]);
}
