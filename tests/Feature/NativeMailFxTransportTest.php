<?php

use App\NativeMailFxTransport;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->transport = new class extends NativeMailFxTransport
    {
        /** @var array{to: string, subject: string, body: string, headers: string}|null */
        public ?array $delivered = null;

        protected function deliver(string $to, string $subject, string $body, string $headers): bool
        {
            $this->delivered = compact('to', 'subject', 'body', 'headers');

            return true;
        }
    };

    $this->email = (new Email)
        ->from(new Address('noreply@example.com', 'OpenCongregationHelper'))
        ->to(new Address('mario@example.com', 'Mario Rossità'))
        ->subject('Territorio assegnato: città')
        ->text('Plain text body')
        ->html('<p>HTML body with secret reset link</p>');
});

test('mail() receives the recipients and subject only once', function () {
    $this->transport->send($this->email);

    $delivered = $this->transport->delivered;

    expect($delivered['to'])->toContain('mario@example.com')
        ->and($delivered['subject'])->toContain('=?utf-8?')->not->toContain('à')
        ->and($delivered['headers'])->not->toMatch('/^To:/mi')
        ->and($delivered['headers'])->not->toMatch('/^Subject:/mi')
        ->and($delivered['headers'])->toMatch('/^From:/mi');
});

test('the message body keeps its MIME content type', function () {
    $this->transport->send($this->email);

    $delivered = $this->transport->delivered;

    expect($delivered['headers'])->toMatch('/^Content-Type: multipart\/alternative; boundary=/mi')
        ->and($delivered['body'])->toContain('Plain text body')
        ->and($delivered['body'])->toContain('HTML body with secret reset link')
        ->and($delivered['body'])->toContain('Content-Type: text/html');
});

test('the email content is not written to the logs', function () {
    Log::spy();

    $this->transport->send($this->email);

    Log::shouldHaveReceived('debug')
        ->withArgs(fn (string $message, array $context = []): bool => ! str_contains(json_encode($context), 'secret reset link'));
});
