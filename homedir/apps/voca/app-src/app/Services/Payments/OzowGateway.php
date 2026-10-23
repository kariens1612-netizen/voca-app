<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Illuminate\Http\Request;
use RuntimeException;

class OzowGateway
{
    public function checkout(Booking $booking): array
    {
        $siteCode = (string) config('payments.ozow.site_code');
        $privateKey = (string) config('payments.ozow.private_key');

        if (! config('payments.ozow.enabled') || $siteCode === '' || $privateKey === '') {
            throw new RuntimeException('Ozow is not configured.');
        }

        $fields = [
            'SiteCode' => $siteCode,
            'CountryCode' => 'ZA',
            'CurrencyCode' => config('payments.currency', 'ZAR'),
            'Amount' => number_format((float) $booking->amount, 2, '.', ''),
            'TransactionReference' => $booking->payment_reference,
            'BankReference' => substr($booking->reference, 0, 20),
            'Optional1' => (string) $booking->id,
            'Optional2' => '',
            'Optional3' => '',
            'Optional4' => '',
            'Optional5' => '',
            'Customer' => substr($booking->email, 0, 100),
            'CancelUrl' => url('/?payment=cancelled&booking='.$booking->reference),
            'ErrorUrl' => url('/?payment=error&booking='.$booking->reference),
            'SuccessUrl' => url('/?payment=success&booking='.$booking->reference),
            'NotifyUrl' => route('payments.ozow.notify'),
            'IsTest' => (bool) config('payments.ozow.test_mode'),
        ];
        $fields['HashCheck'] = $this->hash(array_values($fields), $privateKey);

        return [
            'provider' => 'ozow',
            'url' => rtrim((string) config('payments.ozow.endpoint'), '/'),
            'method' => 'POST',
            'fields' => $fields,
        ];
    }

    public function validNotification(Request $request): bool
    {
        $fields = [
            $request->input('SiteCode'),
            $request->input('TransactionId'),
            $request->input('TransactionReference'),
            $request->input('Amount'),
            $request->input('Status'),
            $request->input('Optional1'),
            $request->input('Optional2'),
            $request->input('Optional3'),
            $request->input('Optional4'),
            $request->input('Optional5'),
            $request->input('CurrencyCode'),
            $this->booleanString($request->input('IsTest')),
            $request->input('StatusMessage'),
        ];

        return hash_equals(
            $this->hash($fields, (string) config('payments.ozow.private_key')),
            strtolower((string) ($request->input('Hash') ?: $request->input('HashCheck')))
        );
    }

    public function hash(array $values, string $privateKey): string
    {
        return hash('sha512', strtolower(implode('', array_map(
            fn ($value) => $this->booleanString($value),
            [...$values, $privateKey]
        ))));
    }

    private function booleanString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) ($value ?? '');
    }
}
