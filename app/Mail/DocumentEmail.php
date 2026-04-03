<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DocumentEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $messageContent,
        public Collection $documents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: [
                new Address(
                    config('mail.reply_to.address'),
                    config('mail.reply_to.name'),
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document',
            with: [
                'messageContent' => $this->messageContent,
                'documents' => $this->documents,
            ],
        );
    }

    public function attachments(): array
    {
        return $this->documents->map(function (Document $document) {
            return Attachment::fromStorageDisk('public', $document->file_name)
                ->as($document->document_name);
        })->toArray();
    }
}
