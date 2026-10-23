<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspiration_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('author')->nullable();
            $table->string('scripture_reference')->nullable();
            $table->string('scripture_translation', 30)->nullable();
            $table->string('audience', 20)->default('public');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'audience', 'publish_at']);
            $table->index(['ministry_id', 'type']);
        });

        Schema::create('inspiration_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspiration_content_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('external_url')->nullable();
            $table->timestamps();
            $table->unique(['inspiration_content_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspiration_media');
        Schema::dropIfExists('inspiration_contents');
    }
};
