<?php

namespace App\Mail\V2;

use App\Models\V2\Study;
use App\V2\Site;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the client: the issued review (PDF attached when small enough, otherwise the link only). */
class StudyIssued extends Mailable
{
    public bool $attached;

    public function __construct(public Study $s, public string $note = '')
    {
        $f = $s->reportPath();
        $this->attached = is_file($f) && filesize($f) <= config('v2.mail_attach_mb', 8) * 1048576;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->s->code}] " . __('studies.mail.issued_subject', [], $this->s->locale) . ': '
            . __('studies.decision.' . $this->s->decision, [], $this->s->locale) . ' — ' . Site::name());
    }

    public function content(): Content
    {
        return new Content(view: 'v2.mail.study-issued');
    }

    public function attachments(): array
    {
        return $this->attached ? [Attachment::fromPath($this->s->reportPath())->as("Study-{$this->s->code}.pdf")->withMime('application/pdf')] : [];
    }
}
