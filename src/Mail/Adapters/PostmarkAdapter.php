<?php

namespace Escalated\Laravel\Mail\Adapters;

use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;

class PostmarkAdapter implements InboundAdapter
{
    /**
     * Parse a Postmark inbound webhook JSON payload into an InboundMessage.
     *
     * Postmark sends a JSON POST with fields:
     * - From, FromName, FromFull: {Email, Name}
     * - To, ToFull: [{Email, Name}]
     * - Subject
     * - TextBody, HtmlBody, StrippedTextReply
     * - MessageID, Headers: [{Name, Value}]
     * - Attachments: [{Name, Content (base64), ContentType, ContentLength}]
     */
    public function parseRequest(Request $request): InboundMessage
    {
        $data = $request->json()->all();

        $fromFull = $data['FromFull'] ?? [];
        $fromEmail = $fromFull['Email'] ?? ($data['From'] ?? '');
        $fromName = $fromFull['Name'] ?? ($data['FromName'] ?? null);

        $toFull = $data['ToFull'] ?? [];
        $toEmail = ! empty($toFull) ? ($toFull[0]['Email'] ?? '') : ($data['To'] ?? '');

        $headers = $this->parseHeaders($data['Headers'] ?? []);
        $inReplyTo = $headers['In-Reply-To'] ?? null;
        $references = $headers['References'] ?? null;

        $attachments = $this->parseAttachments($data['Attachments'] ?? []);

        return new InboundMessage(
            fromEmail: $this->extractEmail($fromEmail),
            fromName: ! empty($fromName) ? $fromName : null,
            toEmail: $this->extractEmail($toEmail),
            subject: $data['Subject'] ?? '',
            bodyText: $data['TextBody'] ?? null,
            bodyHtml: $data['HtmlBody'] ?? null,
            messageId: $data['MessageID'] ?? null,
            inReplyTo: $inReplyTo,
            references: $references,
            headers: $headers,
            attachments: $attachments,
        );
    }

    /**
     * Verify the Postmark inbound webhook request.
     *
     * Postmark does not sign inbound webhooks, so verification is done
     * by checking for a configured token in the request URL or payload.
     * The recommended approach is to use a secret URL path segment.
     */
    public function verifyRequest(Request $request): bool
    {
        $configuredToken = EscalatedSettings::get('postmark_inbound_token', config('escalated.inbound_email.postmark.token'));

        // If no token is configured, skip verification (not recommended for production)
        if (empty($configuredToken)) {
            return true;
        }

        // Check the X-Postmark-Token header (custom header sent by Postmark if configured)
        $requestToken = $request->header('X-Postmark-Token');

        if (! empty($requestToken)) {
            return hash_equals($configuredToken, $requestToken);
        }

        // Fallback: check query parameter
        return hash_equals($configuredToken, $request->query('token', ''));
    }

    /**
     * Extract email address from a string that might contain "Name <email>" format.
     */
    protected function extractEmail(string $email): string
    {
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            return $matches[1];
        }

        return trim($email);
    }

    /**
     * Parse Postmark headers array into key => value pairs.
     */
    protected function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            if (isset($header['Name'], $header['Value'])) {
                $parsed[$header['Name']] = $header['Value'];
            }
        }

        return $parsed;
    }

    /**
     * Parse Postmark attachments into normalized format.
     */
    protected function parseAttachments(array $attachments): array
    {
        $parsed = [];

        foreach ($attachments as $attachment) {
            $content = base64_decode($attachment['Content'] ?? '', true);

            if ($content !== false) {
                $parsed[] = [
                    'filename' => $attachment['Name'] ?? 'attachment',
                    'content' => $content,
                    'contentType' => $attachment['ContentType'] ?? 'application/octet-stream',
                    'size' => $attachment['ContentLength'] ?? strlen($content),
                ];
            }
        }

        return $parsed;
    }
}
