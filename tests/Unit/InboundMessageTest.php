<?php

use Escalated\Laravel\Mail\InboundMessage;

it('returns text body when available', function () {
    $message = new InboundMessage(
        fromEmail: 'test@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: 'Plain text body',
        bodyHtml: '<p>HTML body</p>',
    );

    expect($message->getBody())->toBe('Plain text body');
});

it('strips html tags when only html body is available', function () {
    $message = new InboundMessage(
        fromEmail: 'test@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: null,
        bodyHtml: '<p>Hello <b>world</b></p>',
    );

    expect($message->getBody())->toBe('Hello world');
});

it('returns empty string when no body is available', function () {
    $message = new InboundMessage(
        fromEmail: 'test@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: null,
        bodyHtml: null,
    );

    expect($message->getBody())->toBe('');
});

it('formats raw headers as string', function () {
    $message = new InboundMessage(
        fromEmail: 'test@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: 'Body',
        bodyHtml: null,
        headers: ['From' => 'test@example.com', 'Subject' => 'Test'],
    );

    $raw = $message->getRawHeadersString();
    expect($raw)->toContain('From: test@example.com');
    expect($raw)->toContain('Subject: Test');
});

it('returns null for empty headers', function () {
    $message = new InboundMessage(
        fromEmail: 'test@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: 'Body',
        bodyHtml: null,
        headers: [],
    );

    expect($message->getRawHeadersString())->toBeNull();
});
