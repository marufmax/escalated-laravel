<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class AdminSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Escalated/Admin/Settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'guest_tickets_enabled' => ['required', 'boolean'],
            'allow_customer_close' => ['required', 'boolean'],
            'auto_close_resolved_after_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_attachments_per_reply' => ['required', 'integer', 'min:1', 'max:20'],
            'max_attachment_size_kb' => ['required', 'integer', 'min:512', 'max:102400'],
            'ticket_reference_prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'inbound_email_enabled' => ['sometimes', 'boolean'],
            'inbound_email_adapter' => ['sometimes', 'string', 'in:mailgun,postmark,ses,imap'],
            'inbound_email_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mailgun_signing_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postmark_inbound_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ses_region' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ses_topic_arn' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['sometimes', 'nullable', 'string', 'in:ssl,tls,none'],
            'imap_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imap_mailbox' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            EscalatedSettings::set($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return redirect()->back()->with('success', 'Settings updated.');
    }

    protected function getSettings(): array
    {
        return [
            'guest_tickets_enabled' => EscalatedSettings::getBool('guest_tickets_enabled', true),
            'allow_customer_close' => EscalatedSettings::getBool('allow_customer_close', true),
            'auto_close_resolved_after_days' => EscalatedSettings::getInt('auto_close_resolved_after_days', 7),
            'max_attachments_per_reply' => EscalatedSettings::getInt('max_attachments_per_reply', 5),
            'max_attachment_size_kb' => EscalatedSettings::getInt('max_attachment_size_kb', 10240),
            'ticket_reference_prefix' => EscalatedSettings::get('ticket_reference_prefix', 'ESC'),
            'inbound_email_enabled' => EscalatedSettings::getBool('inbound_email_enabled', (bool) config('escalated.inbound_email.enabled', false)),
            'inbound_email_adapter' => EscalatedSettings::get('inbound_email_adapter', config('escalated.inbound_email.adapter', 'mailgun')),
            'inbound_email_address' => EscalatedSettings::get('inbound_email_address', config('escalated.inbound_email.address', '')),
            'mailgun_signing_key' => EscalatedSettings::get('mailgun_signing_key', config('escalated.inbound_email.mailgun.signing_key', '')),
            'postmark_inbound_token' => EscalatedSettings::get('postmark_inbound_token', config('escalated.inbound_email.postmark.token', '')),
            'ses_region' => EscalatedSettings::get('ses_region', config('escalated.inbound_email.ses.region', 'us-east-1')),
            'ses_topic_arn' => EscalatedSettings::get('ses_topic_arn', config('escalated.inbound_email.ses.topic_arn', '')),
            'imap_host' => EscalatedSettings::get('imap_host', config('escalated.inbound_email.imap.host', '')),
            'imap_port' => EscalatedSettings::getInt('imap_port', (int) config('escalated.inbound_email.imap.port', 993)),
            'imap_encryption' => EscalatedSettings::get('imap_encryption', config('escalated.inbound_email.imap.encryption', 'ssl')),
            'imap_username' => EscalatedSettings::get('imap_username', config('escalated.inbound_email.imap.username', '')),
            'imap_password' => EscalatedSettings::get('imap_password', config('escalated.inbound_email.imap.password', '')),
            'imap_mailbox' => EscalatedSettings::get('imap_mailbox', config('escalated.inbound_email.imap.mailbox', 'INBOX')),
        ];
    }
}
