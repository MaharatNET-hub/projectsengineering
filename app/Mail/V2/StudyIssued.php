<?php

namespace App\Mail\V2;

use App\Models\V2\Study;
use App\Models\V2\Submission;
use App\V2\Site;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the client: the issued review, with the marked-up file from the category check once the engineer
 * approved it (each PDF attached when small enough, otherwise the link only).
 */
class StudyIssued extends Mailable
{
    public bool $attached;

    /** The approved marked-up file of the linked category check, if any. */
    public ?string $markup = null;

    public function __construct(public Study $s, public string $note = '')
    {
        $max = config('v2.mail_attach_mb', 8) * 1048576;
        $f = $s->reportPath();
        $this->attached = is_file($f) && filesize($f) <= $max;
        $check = Submission::where('study_id', $s->id)->first();
        if ($check && ! empty($check->review()['final']) && ($o = $check->outputPath()) && filesize($o) <= $max) {
            $this->markup = $o;
        }
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
        return array_values(array_filter([
            $this->attached ? Attachment::fromPath($this->s->reportPath())->as("Study-{$this->s->code}.pdf")->withMime('application/pdf') : null,
            $this->markup ? Attachment::fromPath($this->markup)->as("Study-{$this->s->code}-marked-up.pdf")->withMime('application/pdf') : null,
        ]));
    }
}
