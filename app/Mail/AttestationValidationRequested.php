<?php

namespace App\Mail;

use App\Models\Request;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttestationValidationRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Request $request,
        public User $requestedBy,
        public string $validationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Attestation à valider — demande {$this->request->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attestation-validation-requested',
            with: [
                'request' => $this->request,
                'requestedBy' => $this->requestedBy,
                'validationUrl' => $this->validationUrl,
            ],
        );
    }
}
