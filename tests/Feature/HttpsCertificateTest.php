<?php

use App\Support\HttpsCertificate;

/**
 * The details of a certificate, as parsed by `openssl_x509_parse()`.
 *
 * @return array<string, mixed>
 */
function certificateDetails(string $alternativeNames, string $expiresIn = '+1 year', string $validFrom = '-1 day'): array
{
    return [
        'subject' => ['CN' => 'altervista.org'],
        'validFrom_time_t' => strtotime($validFrom),
        'validTo_time_t' => strtotime($expiresIn),
        'extensions' => ['subjectAltName' => $alternativeNames],
    ];
}

test('a wildcard name covers a single level only', function (string $name, string $host, bool $matches) {
    expect(HttpsCertificate::matchesHost($name, $host))->toBe($matches);
})->with([
    'exact' => ['example.com', 'example.com', true],
    'case' => ['Example.COM', 'example.com', true],
    'wildcard' => ['*.example.com', 'a.example.com', true],
    'wildcard, two levels' => ['*.altervista.org', 'pr-107.ochtest.altervista.org', false],
    'wildcard, bare domain' => ['*.example.com', 'example.com', false],
    'other host' => ['example.com', 'example.org', false],
]);

test('a certificate for another name is not valid', function () {
    $result = HttpsCertificate::assess(certificateDetails('DNS:*.altervista.org, DNS:altervista.org'), 'pr-107.ochtest.altervista.org');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->toContain('*.altervista.org')
        ->and($result['error'])->toContain('pr-107.ochtest.altervista.org');
});

test('a certificate covering the host is valid until it expires', function () {
    expect(HttpsCertificate::assess(certificateDetails('DNS:*.ochtest.altervista.org'), 'pr-107.ochtest.altervista.org'))
        ->toMatchArray(['valid' => true, 'error' => null])
        ->and(HttpsCertificate::assess(certificateDetails('DNS:example.com', expiresIn: '-1 day'), 'example.com')['valid'])->toBeFalse()
        ->and(HttpsCertificate::assess(certificateDetails('DNS:example.com', validFrom: '+1 day'), 'example.com')['valid'])->toBeFalse();
});

test('the common name is used when the certificate has no alternative names', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $request = openssl_csr_new(['commonName' => 'och.example.com'], $key);
    $certificate = openssl_csr_sign($request, null, $key, 30);

    $details = openssl_x509_parse($certificate);

    expect(HttpsCertificate::assess($details, 'och.example.com')['valid'])->toBeTrue()
        ->and(HttpsCertificate::assess($details, 'other.example.com')['valid'])->toBeFalse();
});

/**
 * A certificate checker whose connections (verified, then not) give the results given.
 *
 * @param  array{connected: bool, details: array<string, mixed>|null, error: string}  $verified
 * @param  array{connected: bool, details: array<string, mixed>|null, error: string}  $unverified
 */
function certificateConnecting(array $verified, array $unverified): HttpsCertificate
{
    return new class($verified, $unverified) extends HttpsCertificate
    {
        public function __construct(private array $verified, private array $unverified) {}

        protected function connect(string $host, int $port, bool $verify): array
        {
            return $verify ? $this->verified : $this->unverified;
        }
    };
}

test('a host the server cannot reach is not checked, rather than invalid', function () {
    $failed = ['connected' => false, 'details' => null, 'error' => 'Connection refused'];

    expect(certificateConnecting($failed, $failed)->inspect('example.com'))
        ->toBe(['valid' => null, 'expires_at' => null, 'error' => 'Connection refused']);
});

test('a certificate PHP does not trust is invalid, also with the right names and dates', function () {
    $result = certificateConnecting(
        ['connected' => false, 'details' => null, 'error' => 'certificate verify failed'],
        ['connected' => true, 'details' => certificateDetails('DNS:example.com'), 'error' => ''],
    )->inspect('example.com');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->toBe('certificate verify failed');
});

test('an expired certificate is invalid, also when PHP refuses it', function () {
    $result = certificateConnecting(
        ['connected' => false, 'details' => null, 'error' => 'certificate verify failed'],
        ['connected' => true, 'details' => certificateDetails('DNS:example.com', expiresIn: '-1 day', validFrom: '-1 year'), 'error' => ''],
    )->inspect('example.com');

    expect($result['valid'])->toBeFalse()
        ->and($result['error'])->toBe('the certificate has expired');
});
