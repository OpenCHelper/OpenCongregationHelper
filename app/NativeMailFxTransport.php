<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Some shared hosting services provide a working mail() function, but
 * don't support manually configuring SMTP servers nor give access to
 * the shell to run a sendmail command.
 *
 * This transport uses the mail() function to send emails.
 */
class NativeMailFxTransport extends AbstractTransport
{
    /**
     * Create a new transport instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $headers = $email->getPreparedHeaders();

        // Use the encoded header values, so that non-ASCII names and subjects
        // are delivered correctly (RFC 2047).
        $to = (string) $headers->get('To')?->getBodyAsString();
        $subject = (string) $headers->get('Subject')?->getBodyAsString();

        // `mail()` adds the `To` and `Subject` headers by itself: keeping them
        // here would duplicate them in the delivered message.
        $headers->remove('To');
        $headers->remove('Subject');

        // The MIME headers of the body (e.g. `Content-Type` with the multipart
        // boundary) must be part of the message headers, otherwise clients
        // cannot decode the body.
        $body = $email->getBody();
        $additionalHeaders = rtrim($headers->toString().$body->getPreparedHeaders()->toString(), "\r\n");

        Log::debug('Sending email using mail() function', [
            'to' => $to,
            'subject' => $subject,
        ]);

        if (! $this->deliver($to, $subject, $body->bodyToString(), $additionalHeaders)) {
            throw new TransportException('Failed to send email using mail() function');
        }
    }

    /**
     * Hand the message over to PHP's native `mail()` function.
     */
    protected function deliver(string $to, string $subject, string $body, string $headers): bool
    {
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'nativemailfx';
    }
}
