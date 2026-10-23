<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\OzowGateway;
use App\Services\Payments\PayfastGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentNotificationController extends Controller
{
    public function ozow(Request $request, OzowGateway $gateway)
    {
        if (! $gateway->validNotification($request)) {
            return response('Invalid notification', 400);
        }

        $booking = Booking::where('payment_reference', $request->input('TransactionReference'))->first();
        if (! $booking || $booking->payment_provider !== 'ozow' || ! $this->amountMatches($booking, $request->input('Amount'))) {
            return response('Payment not found', 400);
        }

        $status = strtolower((string) $request->input('Status'));
        $this->record($booking, $status === 'complete', $status, (string) $request->input('TransactionId'), $request->all());

        return response('OK');
    }

    public function payfast(Request $request, PayfastGateway $gateway)
    {
        if (! $gateway->validNotification($request)) {
            return response('Invalid notification', 400);
        }

        $booking = Booking::where('payment_reference', $request->input('m_payment_id'))->first();
        if (! $booking || $booking->payment_provider !== 'payfast' || ! $this->amountMatches($booking, $request->input('amount_gross'))) {
            return response('Payment not found', 400);
        }

        $status = strtoupper((string) $request->input('payment_status'));
        $this->record($booking, $status === 'COMPLETE', strtolower($status), (string) $request->input('pf_payment_id'), $request->all());

        return response('OK');
    }

    private function amountMatches(Booking $booking, mixed $amount): bool
    {
        return abs((float) $booking->amount - (float) $amount) < 0.01;
    }

    private function record(Booking $booking, bool $paid, string $gatewayStatus, string $transactionId, array $payload): void
    {
        DB::transaction(function () use ($booking, $paid, $gatewayStatus, $transactionId, $payload) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);

            if ($locked->status === 'paid') {
                return;
            }

            $locked->update([
                'status' => $paid ? 'paid' : ($gatewayStatus === 'cancelled' ? 'payment_cancelled' : 'payment_failed'),
                'gateway_status' => $gatewayStatus,
                'payment_transaction_id' => $transactionId ?: $locked->payment_transaction_id,
                'paid_at' => $paid ? now() : null,
                'payment_payload' => $payload,
            ]);
        });
    }
}
