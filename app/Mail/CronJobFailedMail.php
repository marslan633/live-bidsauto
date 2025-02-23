<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CronJobFailedMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $errorMessage;
    public $cronJobName;


    /**
     * Create a new message instance.
     */
    public function __construct($errorMessage, $cronJobName)
    {
        $this->errorMessage = $errorMessage;
        $this->cronJobName = $cronJobName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cron Job Failed Mail '. $this->cronJobName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cron_job_failed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}