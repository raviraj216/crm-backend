<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // WhatsApp Business API credentials
            $table->string('phone_number_id')->unique();   // from Meta app dashboard
            $table->string('phone_number', 20);            // e.g. "919876543210"
            $table->text('access_token');                  // Meta permanent / long-lived token

            // Shown in message placeholders: {business_contact}
            $table->string('contact')->nullable();

            // Business category — purely informational, does not affect flow logic
            $table->enum('category', [
                'food',
                'salon',
                'home_service',
                'clinic',
                'retail',
                'education',
                'real_estate',
                'other',
            ])->default('other');

            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
