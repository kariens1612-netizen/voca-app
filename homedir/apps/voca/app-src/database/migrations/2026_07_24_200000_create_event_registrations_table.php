<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('occurrence_id');
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->string('event_title');
            $table->date('event_date');
            $table->string('event_start_time', 8);
            $table->string('event_location')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->string('reference', 16)->unique();
            $table->string('ticket_token_hash', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['occurrence_id', 'email']);
            $table->index(['event_date', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
