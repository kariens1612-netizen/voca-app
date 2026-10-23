<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayfastGateway
{
    public function checkout(Booking $booking): array
    {
        $merchantId = (string) config('payments.payfast.merchant_id');
        $merchantKey = (string) config('payments.payfast.merchant_key');

        if (! config('payments.payfast.enabled') || $merchantId === '' || $merchantKey === '') {
            throw new RuntimeException('PayFast is not configured.');
        }

        $fields = array_filter([
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,
            'return_url' => url('/?payment=success&booking='.$booking->reference),
            'cancel_url' => url('/?payment=cancelled&booking='.$booking->reference),
            'notify_url' => route('payments.payfast.notify'),
            'name_first' => $booking->name,
            'email_address' => $booking->email,
            'm_payment_id' => $booking->payment_reference,
            'amount' => number_format((float) $booking->amount, 2, '.', ''),
            'item_name' => 'Appointment '.$booking->reference,
            'custom_int1' => $booking->id,
        ], fn ($value) => $value !== null && $value !== '');
        $fields['signature'] = $this->signature($fields);

        $defaultUrl = config('payments.payfast.sandbox')
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process';

        return [
            'provider' => 'payfast',
            'url' => config('payments.payfast.process_url') ?: $defaultUrl,
            'method' => 'POST',
            'fields' => $fields,
        ];
    }

    public function validNotification(Request $request): bool
    {
        $data = $request->except('signature');
        $signature = (string) $request->input('signature');

        if ($signature === '' || ! hash_equals($this->signature($data), $signature)) {
            return false;
        }

        $defaultUrl = config('payments.payfast.sandbox')
            ? 'https://sandbox.payfast.co.za/eng/query/validate'
            : 'https://www.payfast.co.za/eng/query/validate';

        try {
            return trim(Http::asForm()
                ->timeout(15)
                ->post(config('payments.payfast.validate_url') ?: $defaultUrl, $request->all())
                ->body()) === 'VALID';
        } catch (\Throwable) {
            return false;
        }
    }

    public function signature(array $data): string
    {
        unset($data['signature']);
        $encoded = collect($data)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => $key.'='.urlencode(trim((string) $value)))
            ->implode('&');

        $passphrase = (string) config('payments.payfast.passphrase');
        if ($passphrase !== '') {
            $encoded .= '&passphrase='.urlencode(trim($passphrase));
        }

        return md5($encoded);
    }
}
