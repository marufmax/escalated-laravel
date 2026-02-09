<?php

namespace Escalated\Laravel\Mail\Adapters;

use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImapAdapter implements InboundAdapter
{
    /**
     * IMAP adapter does not parse HTTP requests — it fetches emails directly.
     * This method is not used for IMAP. See fetchMessages() instead.
     *
     * @throws \RuntimeException Always, since IMAP does not use HTTP webhooks.
     */
    public function parseRequest(Request $request): InboundMessage
    {
        throw new \RuntimeException('ImapAdapter does not support HTTP request parsing. Use fetchMessages() instead.');
    }

    /**
     * IMAP adapter does not receive HTTP webhooks, so verification is not applicable.
     */
    public function verifyRequest(Request $request): bool
    {
        return false;
    }

    /**
     * Connect to the IMAP server and fetch unread messages.
     *
     * @return InboundMessage[]
     */
    public function fetchMessages(): array
    {
        $config = config('escalated.inbound_email.imap');

        $host = EscalatedSettings::get('imap_host', $config['host'] ?? '');
        $port = EscalatedSettings::getInt('imap_port', (int) ($config['port'] ?? 993));
        $encryption = EscalatedSettings::get('imap_encryption', $config['encryption'] ?? 'ssl');
        $username = EscalatedSettings::get('imap_username', $config['username'] ?? '');
        $password = EscalatedSettings::get('imap_password', $config['password'] ?? '');
        $mailbox = EscalatedSettings::get('imap_mailbox', $config['mailbox'] ?? 'INBOX');

        if (empty($host) || empty($username) || empty($password)) {
            throw new \RuntimeException('IMAP configuration is incomplete. Check host, username, and password.');
        }

        $connectionString = $this->buildConnectionString($host, $port, $encryption, $mailbox);
        $connection = @imap_open($connectionString, $username, $password);

        if ($connection === false) {
            $error = imap_last_error() ?: 'Unknown error';
            throw new \RuntimeException("Failed to connect to IMAP server: {$error}");
        }

        try {
            return $this->processUnreadMessages($connection);
        } finally {
            imap_close($connection);
        }
    }

    /**
     * Build the IMAP connection string.
     */
    protected function buildConnectionString(string $host, int $port, string $encryption, string $mailbox): string
    {
        $flags = match ($encryption) {
            'ssl' => '/imap/ssl',
            'tls' => '/imap/tls',
            'notls' => '/imap/notls',
            default => '/imap/ssl',
        };

        // Add /novalidate-cert for self-signed certificates in non-production
        if (! app()->isProduction()) {
            $flags .= '/novalidate-cert';
        }

        return "{{$host}:{$port}{$flags}}{$mailbox}";
    }

    /**
     * Process all unread messages from the IMAP connection.
     *
     * @return InboundMessage[]
     */
    protected function processUnreadMessages($connection): array
    {
        $messages = [];
        $messageIds = imap_search($connection, 'UNSEEN');

        if ($messageIds === false) {
            return [];
        }

        foreach ($messageIds as $messageId) {
            try {
                $message = $this->parseImapMessage($connection, $messageId);
                if ($message) {
                    $messages[] = $message;
                    // Mark as seen after successful parsing
                    imap_setflag_full($connection, (string) $messageId, '\\Seen');
                }
            } catch (\Throwable $e) {
                Log::error('Escalated: Failed to parse IMAP message.', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $messages;
    }

    /**
     * Parse a single IMAP message into an InboundMessage.
     */
    protected function parseImapMessage($connection, int $messageNum): ?InboundMessage
    {
        $header = imap_headerinfo($connection, $messageNum);
        if (! $header) {
            return null;
        }

        $overview = imap_fetch_overview($connection, (string) $messageNum);
        $rawHeaders = imap_fetchheader($connection, $messageNum);

        // Extract sender
        $fromAddress = $header->from[0] ?? null;
        $fromEmail = $fromAddress ? ($fromAddress->mailbox.'@'.$fromAddress->host) : '';
        $fromName = $fromAddress->personal ?? null;

        // Extract recipient
        $toAddress = $header->to[0] ?? null;
        $toEmail = $toAddress ? ($toAddress->mailbox.'@'.$toAddress->host) : '';

        // Extract subject (decode if MIME-encoded)
        $subject = '';
        if (! empty($overview[0]->subject)) {
            $decoded = imap_mime_header_decode($overview[0]->subject);
            $subject = implode('', array_map(fn ($part) => $part->text, $decoded));
        }

        // Parse headers for In-Reply-To and References
        $headers = $this->parseRawHeaders($rawHeaders);

        // Extract body
        $structure = imap_fetchstructure($connection, $messageNum);
        $bodyText = null;
        $bodyHtml = null;
        $attachments = [];

        if ($structure) {
            $this->parseStructure($connection, $messageNum, $structure, '', $bodyText, $bodyHtml, $attachments);
        }

        return new InboundMessage(
            fromEmail: $fromEmail,
            fromName: ! empty($fromName) ? $this->decodeMimeString($fromName) : null,
            toEmail: $toEmail,
            subject: $subject,
            bodyText: $bodyText,
            bodyHtml: $bodyHtml,
            messageId: $headers['Message-ID'] ?? ($header->message_id ?? null),
            inReplyTo: $headers['In-Reply-To'] ?? ($header->in_reply_to ?? null),
            references: $headers['References'] ?? ($header->references ?? null),
            headers: $headers,
            attachments: $attachments,
        );
    }

    /**
     * Parse raw email headers into key => value pairs.
     */
    protected function parseRawHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines = preg_split('/\r?\n/', $rawHeaders);
        $currentKey = '';
        $currentValue = '';

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Continuation line (starts with whitespace)
            if (preg_match('/^\s+/', $line)) {
                $currentValue .= ' '.trim($line);

                continue;
            }

            // Save previous header
            if ($currentKey) {
                $headers[$currentKey] = trim($currentValue);
            }

            // Parse new header line
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $currentKey = trim(substr($line, 0, $colonPos));
                $currentValue = trim(substr($line, $colonPos + 1));
            }
        }

        // Save last header
        if ($currentKey) {
            $headers[$currentKey] = trim($currentValue);
        }

        return $headers;
    }

    /**
     * Recursively parse the MIME structure to extract body and attachments.
     */
    protected function parseStructure(
        $connection,
        int $messageNum,
        object $structure,
        string $partNumber,
        ?string &$bodyText,
        ?string &$bodyHtml,
        array &$attachments,
    ): void {
        // Multipart message
        if (! empty($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $subPartNumber = $partNumber ? "{$partNumber}.".($index + 1) : (string) ($index + 1);
                $this->parseStructure($connection, $messageNum, $part, $subPartNumber, $bodyText, $bodyHtml, $attachments);
            }

            return;
        }

        // Single part — determine if it is body text, html, or attachment
        $isAttachment = false;
        $filename = null;

        // Check disposition
        if (! empty($structure->disposition) && strtolower($structure->disposition) === 'attachment') {
            $isAttachment = true;
        }

        // Check dparameters for filename
        if (! empty($structure->dparameters)) {
            foreach ($structure->dparameters as $param) {
                if (strtolower($param->attribute) === 'filename') {
                    $filename = $param->value;
                    $isAttachment = true;
                }
            }
        }

        // Check parameters for name
        if (! empty($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute) === 'name') {
                    $filename = $filename ?? $param->value;
                }
            }
        }

        // Fetch the part content
        $content = $partNumber
            ? imap_fetchbody($connection, $messageNum, $partNumber)
            : imap_body($connection, $messageNum);

        // Decode based on encoding
        $content = $this->decodePartContent($content, $structure->encoding ?? 0);

        if ($isAttachment && $filename) {
            $attachments[] = [
                'filename' => $this->decodeMimeString($filename),
                'content' => $content,
                'contentType' => $this->getPartContentType($structure),
                'size' => strlen($content),
            ];
        } elseif (($structure->type ?? 0) === 0) {
            // Text type
            $subtype = strtolower($structure->subtype ?? 'plain');

            if ($subtype === 'plain' && $bodyText === null) {
                $bodyText = $this->convertCharset($content, $structure);
            } elseif ($subtype === 'html' && $bodyHtml === null) {
                $bodyHtml = $this->convertCharset($content, $structure);
            }
        }
    }

    /**
     * Decode part content based on the IMAP encoding type.
     */
    protected function decodePartContent(string $content, int $encoding): string
    {
        return match ($encoding) {
            0 => $content,                           // 7BIT
            1 => $content,                           // 8BIT
            2 => $content,                           // BINARY
            3 => base64_decode($content, true) ?: $content,   // BASE64
            4 => quoted_printable_decode($content),  // QUOTED-PRINTABLE
            default => $content,
        };
    }

    /**
     * Get the content type string from a structure part.
     */
    protected function getPartContentType(object $structure): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'model', 'other'];
        $type = $types[$structure->type ?? 8] ?? 'other';
        $subtype = strtolower($structure->subtype ?? 'octet-stream');

        return "{$type}/{$subtype}";
    }

    /**
     * Convert content charset to UTF-8 if needed.
     */
    protected function convertCharset(string $content, object $structure): string
    {
        $charset = 'UTF-8';

        if (! empty($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    $charset = strtoupper($param->value);
                    break;
                }
            }
        }

        if ($charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT', $content);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $content;
    }

    /**
     * Decode a MIME-encoded string (e.g., =?UTF-8?Q?Subject?=).
     */
    protected function decodeMimeString(string $string): string
    {
        $decoded = imap_mime_header_decode($string);
        $result = '';

        foreach ($decoded as $part) {
            $result .= $part->text;
        }

        return $result;
    }
}
