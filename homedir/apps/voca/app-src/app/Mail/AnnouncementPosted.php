<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\Ministry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementPosted extends Mailable
{
    use Queueable, SerializesModels;

    public Announcement $announcement;
    public ?Ministry $ministry;

    /**
     * Create a new message instance.
     */
    public function __construct(Announcement $announcement, ?Ministry $ministry)
    {
        $this->announcement = $announcement;
        $this->ministry = $ministry;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $ministryName = $this->ministry?->name;
        $subject = $ministryName
            ? "{$ministryName}: {$this->announcement->title}"
            : $this->announcement->title;

        return $this->subject($subject)
            ->view('emails.announcement-posted');
    }
}
