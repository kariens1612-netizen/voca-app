<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('live_stream_settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('Rumble');
            $table->string('status')->default('Stand by');
            $table->string('schedule')->default('Sunday 09H30');
            $table->text('embed_url')->nullable();
            $table->text('watch_url')->nullable();
            $table->string('stream_title')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_stream_settings');
    }
};
