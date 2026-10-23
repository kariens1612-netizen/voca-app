<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('supplier')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('ZAR');
            $table->date('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
            $table->index(['ministry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_slips');
    }
};
