<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\Payments\OzowGateway;
use App\Services\Payments\PayfastGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'PM-TEST1234',
            'name' => 'Test Person',
            'email' => 'test@example.com',
            'phone' => '0820000000',
            'ministry' => 'counselling',
            'appointment_date' => '2026-08-01',
            'appointment_time' => '10:00',
            'amount' => 250,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_ozow_checkout_is_signed_and_notification_marks_booking_paid(): void
    {
        config()->set('payments.ozow', [
            'enabled' => true,
            'endpoint' => 'https://pay.ozow.test',
            'site_code' => 'TEST-SITE',
            'private_key' => 'test-secret',
            'test_mode' => true,
        ]);
        $booking = $this->booking();

        $checkout = $this->postJson("/api/bookings/{$booking->id}/pay", ['provider' => 'ozow'])
            ->assertOk()
            ->assertJsonPath('data.provider', 'ozow')
            ->json('data');

        $this->assertNotEmpty($checkout['fields']['HashCheck']);
        $notification = [
            'SiteCode' => 'TEST-SITE',
            'TransactionId' => 'OZOW-123',
            'TransactionReference' => $booking->fresh()->payment_reference,
            'Amount' => '250.00',
            'Status' => 'Complete',
            'Optional1' => (string) $booking->id,
            'Optional2' => '',
            'Optional3' => '',
            'Optional4' => '',
            'Optional5' => '',
            'CurrencyCode' => 'ZAR',
            'IsTest' => 'true',
            'StatusMessage' => 'Paid',
        ];
        $notification['Hash'] = app(OzowGateway::class)->hash(array_values($notification), 'test-secret');

        $this->post('/api/payments/ozow/notify', $notification)->assertOk();
        $this->assertSame('paid', $booking->fresh()->status);
        $this->assertSame('OZOW-123', $booking->fresh()->payment_transaction_id);
    }

    public function test_payfast_only_marks_paid_after_signature_amount_and_server_validation_pass(): void
    {
        config()->set('payments.payfast', [
            'enabled' => true,
            'merchant_id' => '10000100',
            'merchant_key' => 'test-key',
            'passphrase' => 'test-passphrase',
            'sandbox' => true,
            'process_url' => null,
            'validate_url' => null,
        ]);
        Http::fake(['*' => Http::response('VALID')]);
        $booking = $this->booking();

        $this->postJson("/api/bookings/{$booking->id}/pay", ['provider' => 'payfast'])
            ->assertOk()
            ->assertJsonPath('data.provider', 'payfast');

        $notification = [
            'm_payment_id' => $booking->fresh()->payment_reference,
            'pf_payment_id' => 'PF-123',
            'payment_status' => 'COMPLETE',
            'item_name' => 'Appointment '.$booking->reference,
            'amount_gross' => '250.00',
        ];
        $notification['signature'] = app(PayfastGateway::class)->signature($notification);

        $this->post('/api/payments/payfast/notify', $notification)->assertOk();
        $this->assertSame('paid', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->paid_at);
        Http::assertSentCount(1);
    }

    public function test_invalid_or_wrong_amount_notification_is_rejected(): void
    {
        config()->set('payments.payfast.passphrase', 'test-passphrase');
        Http::fake(['*' => Http::response('VALID')]);
        $booking = $this->booking([
            'payment_provider' => 'payfast',
            'payment_reference' => 'payment-ref',
            'status' => 'payment_pending',
        ]);
        $notification = [
            'm_payment_id' => 'payment-ref',
            'pf_payment_id' => 'PF-BAD',
            'payment_status' => 'COMPLETE',
            'amount_gross' => '1.00',
        ];
        $notification['signature'] = app(PayfastGateway::class)->signature($notification);

        $this->post('/api/payments/payfast/notify', $notification)->assertBadRequest();
        $this->assertSame('payment_pending', $booking->fresh()->status);
    }
}
