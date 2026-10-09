<?php

namespace App\Mail\V2;

use App\Models\V2\Study;
use App\V2\Site;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the client: we have your study, here is the tracking code. */
class StudyReceived extends Mailable
{
    public function __construct(public Study $s) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->s->code}] " . __('studies.mail.received_subject', [], $this->s->locale) . ' — ' . Site::name());
    }

    public function content(): Content
    {
        return new Content(view: 'v2.mail.study-received');
    }
}
