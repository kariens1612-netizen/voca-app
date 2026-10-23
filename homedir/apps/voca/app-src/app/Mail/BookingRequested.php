<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Ministry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingRequested extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public ?Ministry $ministry;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, ?Ministry $ministry)
    {
        $this->booking = $booking;
        $this->ministry = $ministry;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $ministryName = $this->ministry?->name ?? $this->booking->ministry;
        $subject = $ministryName
            ? "Booking request: {$ministryName}"
            : 'New booking request';

        return $this->subject($subject)
            ->view('emails.booking-requested');
    }
}
