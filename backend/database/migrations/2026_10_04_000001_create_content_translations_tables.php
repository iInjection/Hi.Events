<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_translation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('source_locale', 10);
            $table->string('fallback_locale', 10)->nullable();
            $table->jsonb('locales')->nullable();
            $table->timestamps();
        });

        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('translatable_type', 32);
            $table->unsignedBigInteger('translatable_id');
            $table->string('field', 64);
            $table->string('locale', 10);
            $table->text('value');
            $table->string('source_hash', 40)->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'field', 'locale'], 'content_translations_unique');
            $table->index(['event_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
        Schema::dropIfExists('event_translation_settings');
    }
};
