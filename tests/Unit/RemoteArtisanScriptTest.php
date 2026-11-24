<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * `scripts/remote_artisan.sh` runs an Artisan command through the endpoint of an installation: `curl` and `lftp` are
 * replaced by stubs, the first recording the request it gets and answering with the given response.
 */

beforeEach(function () {
    if ((new ExecutableFinder)->find('jq') === null) {
        $this->markTestSkipped('The script needs jq.');
    }

    $this->bin = sys_get_temp_dir().'/remote-artisan-'.bin2hex(random_bytes(6));
    mkdir($this->bin);

    file_put_contents($this->bin.'/curl', <<<'BASH'
        #!/usr/bin/env bash
        while (( $# > 0 )); do
            if [[ "$1" == "--data" ]]; then printf '%s' "$2" > "$STUB_REQUEST"; fi
            shift
        done
        printf '%s' "$STUB_RESPONSE"
        BASH);
    file_put_contents($this->bin.'/lftp', "#!/usr/bin/env bash\ncat > /dev/null\n");
    chmod($this->bin.'/curl', 0755);
    chmod($this->bin.'/lftp', 0755);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->bin);
});

/**
 * Run the script with the given arguments, the endpoint answering with the given response.
 *
 * @param  list<string>  $arguments
 * @param  array{output: string, returnCode: int}  $response
 */
function runRemoteArtisan(string $bin, array $arguments, array $response = ['output' => 'Done.', 'returnCode' => 0]): Process
{
    $process = new Process(
        ['bash', dirname(__DIR__, 2).'/scripts/remote_artisan.sh', '--base-url', 'https://och-instance.example.com',
            '--ftp-hostname', 'ftp.example.com', '--ftp-username', 'user', '--ftp-password', 'secret', ...$arguments],
        dirname(__DIR__, 2),
        [
            'PATH' => $bin.':'.getenv('PATH'),
            'STUB_REQUEST' => $bin.'/request.json',
            'STUB_RESPONSE' => json_encode($response),
        ],
    );
    $process->run();

    return $process;
}

test('the options after the command are passed to it', function () {
    $process = runRemoteArtisan($this->bin, ['och:post-deploy', '--no-interaction']);

    $request = json_decode(file_get_contents($this->bin.'/request.json'), true);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('Done.')
        ->and($request)->toBe(['command' => 'och:post-deploy', 'params' => '--no-interaction']);
});

test('an unknown option before the command is refused', function () {
    $process = runRemoteArtisan($this->bin, ['--unknown', 'och:doctor']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain("Unknown option '--unknown'");
});

test('a failing command makes the script fail', function () {
    $process = runRemoteArtisan($this->bin, ['och:post-deploy'], ['output' => 'Stopped.', 'returnCode' => 1]);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('Stopped.')
        ->and($process->getErrorOutput())->toContain("Command 'och:post-deploy' failed with exit code 1");
});
