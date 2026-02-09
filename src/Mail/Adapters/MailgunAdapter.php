<?php

namespace Escalated\Laravel\Mail\Adapters;

use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;

class MailgunAdapter implements InboundAdapter
{
    /**
     * Parse a Mailgun inbound webhook POST into an InboundMessage.
     *
     * Mailgun sends a multipart/form-data POST with fields:
     * - sender, from, recipient, subject
     * - body-plain, body-html, stripped-text, stripped-html
     * - Message-Id, In-Reply-To, References
     * - message-headers (JSON array of [name, value] pairs)
     * - attachment-count, attachment-x (files)
     * - timestamp, token, signature (for verification)
     */
    public function parseRequest(Request $request): InboundMessage
    {
        $fromEmail = $this->extractEmail($request->input('sender', $request->input('from', '')));
        $fromName = $this->extractName($request->input('from', ''));

        $headers = $this->parseHeaders($request->input('message-headers', '[]'));

        $attachments = $this->parseAttachments($request);

        return new InboundMessage(
            fromEmail: $fromEmail,
            fromName: $fromName ?: null,
            toEmail: $this->extractEmail($request->input('recipient', '')),
            subject: $request->input('subject', ''),
            bodyText: $request->input('body-plain') ?: $request->input('stripped-text'),
            bodyHtml: $request->input('body-html') ?: $request->input('stripped-html'),
            messageId: $request->input('Message-Id'),
            inReplyTo: $request->input('In-Reply-To'),
            references: $request->input('References'),
            headers: $headers,
            attachments: $attachments,
        );
    }

    /**
     * Verify the Mailgun webhook signature.
     *
     * Mailgun signs each webhook POST with:
     * - timestamp: Unix timestamp
     * - token: Random string
     * - signature: HMAC-SHA256 of timestamp + token using signing key
     */
    public function verifyRequest(Request $request): bool
    {
        $signingKey = EscalatedSettings::get('mailgun_signing_key', config('escalated.inbound_email.mailgun.signing_key'));

        if (empty($signingKey)) {
            return false;
        }

        $timestamp = $request->input('timestamp', '');
        $token = $request->input('token', '');
        $signature = $request->input('signature', '');

        if (empty($timestamp) || empty($token) || empty($signature)) {
            return false;
        }

        // Reject timestamps older than 5 minutes to prevent replay attacks
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.$token, $signingKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Extract the email address from a "Name <email>" formatted string.
     */
    protected function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return $matches[1];
        }

        return trim($from);
    }

    /**
     * Extract the display name from a "Name <email>" formatted string.
     */
    protected function extractName(string $from): string
    {
        if (preg_match('/^(.+?)\s*</', $from, $matches)) {
            return trim($matches[1], '" ');
        }

        return '';
    }

    /**
     * Parse the Mailgun message-headers JSON into a key => value array.
     */
    protected function parseHeaders(string $headersJson): array
    {
        $parsed = json_decode($headersJson, true);
        $headers = [];

        if (is_array($parsed)) {
            foreach ($parsed as $header) {
                if (is_array($header) && count($header) === 2) {
                    $headers[$header[0]] = $header[1];
                }
            }
        }

        return $headers;
    }

    /**
     * Parse file attachments from the Mailgun request.
     */
    protected function parseAttachments(Request $request): array
    {
        $attachments = [];
        $count = (int) $request->input('attachment-count', 0);

        for ($i = 1; $i <= $count; $i++) {
            $file = $request->file("attachment-{$i}");
            if ($file) {
                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'content' => file_get_contents($file->getRealPath()),
                    'contentType' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        return $attachments;
    }
}
