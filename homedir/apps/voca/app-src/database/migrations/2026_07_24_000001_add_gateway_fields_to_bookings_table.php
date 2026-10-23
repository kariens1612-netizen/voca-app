<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_provider', 20)->nullable()->after('payment_reference');
            $table->string('payment_transaction_id')->nullable()->after('payment_provider');
            $table->string('gateway_status')->nullable()->after('payment_transaction_id');
            $table->timestamp('paid_at')->nullable()->after('gateway_status');
            $table->json('payment_payload')->nullable()->after('paid_at');
            $table->unique(['payment_provider', 'payment_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['payment_provider', 'payment_transaction_id']);
            $table->dropColumn(['payment_provider', 'payment_transaction_id', 'gateway_status', 'paid_at', 'payment_payload']);
        });
    }
};
