<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ── Matching ─────────────────────────────────────────────────
            // Keywords that trigger this message (JSON array).
            // e.g. ["hi","hello","menu"] or ["book","appointment","slot"]
            // match_mode:
            //   exact    → full text equals one of these
            //   contains → input contains any of these
            //   starts   → input starts with any of these
            // null = not keyword-triggered
            $table->json('triggers')->nullable();
            $table->enum('match_mode', ['exact', 'contains', 'starts'])->default('exact');

            // Higher number = checked first within keyword matches.
            // Active step always beats any keyword.
            $table->unsignedSmallInteger('priority')->default(0);

            // ── Step flow ────────────────────────────────────────────────
            // Matches current_step in whatsapp_conversations.
            // null = not step-triggered.
            $table->string('step')->nullable()->index();

            // After sending, set conversation current_step to this value.
            // null = reset conversation (no active step).
            $table->string('next_step')->nullable();

            // ── Fallback ─────────────────────────────────────────────────
            // Sent when nothing else matches. One per business.
            $table->boolean('is_fallback')->default(false);

            // ── Message type ─────────────────────────────────────────────
            $table->enum('type', ['text', 'template'])->default('text');

            // For type=text. Supports placeholders:
            //   {business_name}, {business_contact}
            //   Any key in collected_data: {name}, {date}, {service}, etc.
            $table->text('body')->nullable();

            // For type=template.
            $table->string('template_name')->nullable();

            // JSON array of collected_data keys → maps to {{1}}, {{2}} …
            // e.g. ["name", "service", "date"]
            $table->json('template_params')->nullable();

            // ── Data collection ──────────────────────────────────────────
            // When this step is active, store the user's raw reply into
            // collected_data under this key name before sending next message.
            // e.g. "name", "phone", "address", "preferred_date"
            // null = just respond, don't save anything.
            $table->string('collect_as')->nullable();

            // Human-readable label for admin / seeders.
            $table->string('label')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'step']);
            $table->index(['business_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
