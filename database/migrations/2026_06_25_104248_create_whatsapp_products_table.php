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
        Schema::create('whatsapp_products', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id');

            $table->integer('serial');
    
            $table->string('title');
    
            $table->decimal('price',10,2);
    
            $table->text('ingredients')->nullable();
    
            $table->text('description')->nullable();
    
             $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_products');
    }
};
