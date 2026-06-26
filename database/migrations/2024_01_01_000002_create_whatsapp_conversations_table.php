<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Customer's WhatsApp number e.g. "919876543210"
            $table->string('mobile', 20);

            // Tracks where this customer is in a multi-step flow.
            // Matches whatsapp_messages.step values.
            // null = no active flow; start fresh on next message.
            $table->string('current_step')->nullable();

            // Freeform key-value store built up during multi-step collection.
            // Any whatsapp_messages.collect_as key gets saved here.
            // Examples:
            //   {"name":"Rahul","address":"MG Road","qty":"2"}       (product order)
            //   {"name":"Priya","service":"Haircut","date":"Mon 3pm"} (salon booking)
            //   {"name":"Amit","issue":"AC not cooling","flat":"B4"}  (service request)
            $table->json('collected_data')->nullable();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'mobile']);
            $table->index(['business_id', 'mobile', 'current_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
