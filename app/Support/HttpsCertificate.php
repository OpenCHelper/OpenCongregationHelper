<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Check the HTTPS certificate of a host: whether the browsers trust it, and until when.
 *
 * Besides the verification of PHP, the certificate itself is checked (its names and its dates), as the browsers do: on
 * some hosting the connection the server makes to itself is not the one the browsers make. A certificate that cannot be
 * read is reported as not checked, never as valid.
 *
 * Bound in the container, so that the tests replace it instead of connecting to the host.
 */
class HttpsCertificate
{
    /**
     * Seconds waited for the host to answer.
     */
    public const TIMEOUT_SECONDS = 5;

    /**
     * @return array{valid: ?bool, expires_at: ?CarbonImmutable, error: ?string} `valid` is `null` when the certificate
     *                                                                           could not be checked
     */
    public function inspect(string $host, int $port = 443): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $errorMessage = '';

        set_error_handler(function (int $level, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;

            return true;
        });

        try {
            $client = stream_socket_client("ssl://{$host}:{$port}", $errorCode, $errorString, self::TIMEOUT_SECONDS, STREAM_CLIENT_CONNECT, $context);
        } finally {
            restore_error_handler();
        }

        if ($client === false) {
            $error = trim($errorString !== '' ? $errorString : $errorMessage);

            return ['valid' => false, 'expires_at' => null, 'error' => $error !== '' ? $error : 'connection failed'];
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $details = $certificate !== null ? openssl_x509_parse($certificate) : false;

        if (! is_array($details)) {
            return ['valid' => null, 'expires_at' => null, 'error' => 'the certificate could not be read'];
        }

        return self::assess($details, $host);
    }

    /**
     * Check the names and the dates of a certificate (as parsed by `openssl_x509_parse()`) for a host.
     *
     * @param  array<string, mixed>  $details
     * @return array{valid: bool, expires_at: ?CarbonImmutable, error: ?string}
     */
    public static function assess(array $details, string $host): array
    {
        $expiresAt = isset($details['validTo_time_t']) ? CarbonImmutable::createFromTimestamp((int) $details['validTo_time_t']) : null;
        $validFrom = isset($details['validFrom_time_t']) ? CarbonImmutable::createFromTimestamp((int) $details['validFrom_time_t']) : null;

        if ($expiresAt === null || $expiresAt->isPast()) {
            return ['valid' => false, 'expires_at' => $expiresAt, 'error' => 'the certificate has expired'];
        }

        if ($validFrom !== null && $validFrom->isFuture()) {
            return ['valid' => false, 'expires_at' => $expiresAt, 'error' => 'the certificate is not valid yet'];
        }

        $names = self::names($details);

        foreach ($names as $name) {
            if (self::matchesHost($name, $host)) {
                return ['valid' => true, 'expires_at' => $expiresAt, 'error' => null];
            }
        }

        return [
            'valid' => false,
            'expires_at' => $expiresAt,
            'error' => 'the certificate is for '.($names !== [] ? implode(', ', $names) : 'no name').", not for {$host}",
        ];
    }

    /**
     * Whether a name of a certificate covers a host: a wildcard (`*.example.com`) covers a single level only, so
     * `a.example.com` but neither `example.com` nor `b.a.example.com`.
     */
    public static function matchesHost(string $name, string $host): bool
    {
        $name = strtolower(rtrim($name, '.'));
        $host = strtolower(rtrim($host, '.'));

        if (! str_starts_with($name, '*.')) {
            return $name === $host;
        }

        $suffix = substr($name, 1);
        $label = substr($host, 0, -strlen($suffix));

        return str_ends_with($host, $suffix) && $label !== '' && ! str_contains($label, '.');
    }

    /**
     * The names of a certificate: its DNS alternative names, or else its common name.
     *
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private static function names(array $details): array
    {
        $alternativeNames = (string) ($details['extensions']['subjectAltName'] ?? '');

        preg_match_all('/DNS:\s*([^,\s]+)/', $alternativeNames, $matches);

        if ($matches[1] !== []) {
            return $matches[1];
        }

        $commonName = $details['subject']['CN'] ?? null;

        return is_string($commonName) && $commonName !== '' ? [$commonName] : [];
    }
}
