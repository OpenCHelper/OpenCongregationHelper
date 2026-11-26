<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Check the HTTPS certificate of a host: whether the browsers trust it, and until when.
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
     * @return array{valid: bool, expires_at: ?CarbonImmutable, error: ?string}
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
        $expiresAt = is_array($details) && isset($details['validTo_time_t'])
            ? CarbonImmutable::createFromTimestamp($details['validTo_time_t'])
            : null;

        return ['valid' => true, 'expires_at' => $expiresAt, 'error' => null];
    }
}
