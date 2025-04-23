<?php

namespace App;

use Illuminate\Support\Facades\Log;
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
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-type:text/html;charset=UTF-8',
            'From: ' . $from,
        ]);

        Log::debug('Sending email', [
            'from' => $from,
            'to' => $to,
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'headers' => $headers,
        ]);

        mail($to, $email->getSubject(), $email->getHtmlBody(), $headers);
    }

    /**
     * Get the string representation of the transport.
     */
    public function __toString(): string
    {
        return 'nativemailfx';
    }
}