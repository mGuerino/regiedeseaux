<?php

namespace App\Mail;

use App\Models\Request;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttestationValidationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Request $request,
        public User $rejectedBy,
        public string $reason,
        public string $requestUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Attestation refusée — demande {$this->request->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attestation-validation-rejected',
            with: [
                'request' => $this->request,
                'rejectedBy' => $this->rejectedBy,
                'reason' => $this->reason,
                'requestUrl' => $this->requestUrl,
            ],
        );
    }
}
