<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movement_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
        });
        Schema::create('movement_delegates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained('movement_rosters')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('group', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('reference', 50)->unique();
            $table->timestamps();
            $table->unique(['roster_id', 'email']);
        });
        Schema::create('movement_journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained('movement_rosters')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->string('location', 120);
            $table->json('expected_ids');
            $table->timestamps();
        });
        Schema::create('movement_scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('movement_delegates')->cascadeOnDelete();
            $table->foreignId('journey_id')->constrained('movement_journeys')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('action', 20);
            $table->string('stop', 120);
            $table->uuid('base_event_id')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->index(['delegate_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['movement_scans', 'movement_journeys', 'movement_delegates', 'movement_rosters'] as $table) Schema::dropIfExists($table);
    }
};
