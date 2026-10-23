<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingRequested;
use App\Models\Booking;
use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\Payments\OzowGateway;
use App\Services\Payments\PayfastGateway;
use RuntimeException;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'ministry' => ['required', 'string', 'max:100'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $booking = Booking::create([
            ...$validated,
            'reference' => $this->generateReference(),
            'status' => 'pending',
        ]);

        $this->sendEmailNotifications($booking);

        return response()->json([
            'data' => $this->transformBooking($booking),
        ], 201);
    }

    public function pay(Request $request, Booking $booking, OzowGateway $ozow, PayfastGateway $payfast)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:ozow,payfast'],
        ]);

        if ($booking->status === 'paid') {
            return response()->json([
                'message' => 'Booking is already paid.',
                'data' => $this->transformBooking($booking),
            ], 422);
        }

        $paymentReference = $booking->payment_reference ?: Str::uuid()->toString();

        $booking->update([
            'status' => 'payment_pending',
            'payment_reference' => $paymentReference,
            'payment_provider' => $validated['provider'],
        ]);

        try {
            $checkout = $validated['provider'] === 'ozow'
                ? $ozow->checkout($booking->fresh())
                : $payfast->checkout($booking->fresh());
        } catch (RuntimeException $exception) {
            $booking->update(['status' => 'pending']);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $checkout]);
    }

    private function generateReference(): string
    {
        return 'PM-' . Str::upper(Str::random(8));
    }

    private function transformBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'name' => $booking->name,
            'email' => $booking->email,
            'phone' => $booking->phone,
            'ministry' => $booking->ministry,
            'appointment_date' => $booking->appointment_date,
            'appointment_time' => $booking->appointment_time,
            'notes' => $booking->notes,
            'amount' => $booking->amount,
            'status' => $booking->status,
        ];
    }

    private function sendEmailNotifications(Booking $booking): void
    {
        $ministry = Ministry::query()
            ->where('slug', $booking->ministry)
            ->first();

        if (! $ministry) {
            $ministry = Ministry::query()
                ->where('name', $booking->ministry)
                ->first();
        }

        $recipientEmails = [];

        if ($ministry) {
            $memberships = MinistryMembership::query()
                ->where('ministry_id', $ministry->id)
                ->where(function ($query) {
                    $query->whereIn('role', ['admin', 'leader'])
                        ->orWhereHas('roleRecord', function ($roleQuery) {
                            $roleQuery->whereIn('slug', ['admin', 'leader']);
                        });
                })
                ->with('user')
                ->get();

            $recipientEmails = $memberships
                ->pluck('user.email')
                ->filter()
                ->all();
        }

        $superadminEmails = User::query()
            ->where('is_superadmin', true)
            ->pluck('email')
            ->filter()
            ->all();

        $allRecipients = collect([...$recipientEmails, ...$superadminEmails])
            ->unique()
            ->values();

        if ($allRecipients->isEmpty()) {
            return;
        }

        $allRecipients->each(function ($email) use ($booking, $ministry) {
            Mail::to($email)->send(new BookingRequested($booking, $ministry));
        });
    }
}
