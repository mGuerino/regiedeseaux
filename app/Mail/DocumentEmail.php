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
use Illuminate\Support\Facades\Storage;

class DocumentEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subject,
        public string $messageContent,
        public Collection $documents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
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
            $filePath = Storage::path($document->file_name);
            
            return Attachment::fromPath($filePath)
                ->as($document->document_name)
                ->withMime(Storage::mimeType($document->file_name));
        })->toArray();
    }
}
