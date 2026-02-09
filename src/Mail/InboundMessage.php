<?php

namespace Escalated\Laravel\Mail;

class InboundMessage
{
    /**
     * @param  string  $fromEmail  Sender email address.
     * @param  string|null  $fromName  Sender display name.
     * @param  string  $toEmail  Recipient email address.
     * @param  string  $subject  Email subject line.
     * @param  string|null  $bodyText  Plain-text body.
     * @param  string|null  $bodyHtml  HTML body.
     * @param  string|null  $messageId  The Message-ID header value.
     * @param  string|null  $inReplyTo  The In-Reply-To header value.
     * @param  string|null  $references  The References header value.
     * @param  array  $headers  All raw headers as key => value pairs.
     * @param  array  $attachments  Array of attachment arrays with keys: filename, content, contentType, size.
     */
    public function __construct(
        public string $fromEmail,
        public ?string $fromName,
        public string $toEmail,
        public string $subject,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public ?string $references = null,
        public array $headers = [],
        public array $attachments = [],
    ) {}

    /**
     * Get the best available body content, preferring plain text.
     */
    public function getBody(): string
    {
        if (! empty($this->bodyText)) {
            return $this->bodyText;
        }

        if (! empty($this->bodyHtml)) {
            return strip_tags($this->bodyHtml);
        }

        return '';
    }

    /**
     * Get all raw headers as a single string for storage.
     */
    public function getRawHeadersString(): ?string
    {
        if (empty($this->headers)) {
            return null;
        }

        $lines = [];
        foreach ($this->headers as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }

        return implode("\r\n", $lines);
    }
}
