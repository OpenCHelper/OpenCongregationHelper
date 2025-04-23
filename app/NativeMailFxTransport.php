<?php

namespace App;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
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
    public function __construct() {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = implode(',', collect($email->getFrom())->map(fn (Address $email) => $email->toString())->all());
        $to = implode(',', collect($email->getTo())->map(fn (Address $email) => $email->toString())->all());
        $headers = $email->getPreparedHeaders()->toString();

        Log::debug('Sending email using mail() function', [
            'from' => $from,
            'to' => $to,
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'headers' => $headers,
        ]);

        if (!mail($to, $email->getSubject(), $email->getHtmlBody(), $headers)) {
            throw new TransportException('Failed to send email using mail() function');
        }
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'nativemailfx';
    }
}