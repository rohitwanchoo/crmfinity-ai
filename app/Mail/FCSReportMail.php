<?php

namespace App\Mail;

use App\Models\MCAApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FCSReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public MCAApplication $application;

    public string $pdfPath;

    public string $pdfFilename;

    public ?string $customMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(MCAApplication $application, string $pdfPath, string $pdfFilename, ?string $customMessage = null)
    {
        $this->application = $application;
        $this->pdfPath = $pdfPath;
        $this->pdfFilename = $pdfFilename;
        $this->customMessage = $customMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'File Control Sheet - '.$this->application->business_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fcs-report',
            with: [
                'application' => $this->application,
                'customMessage' => $this->customMessage,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorage($this->pdfPath)
                ->as($this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
