<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();

            $table->string('reservaya_base_url')->nullable();
            $table->text('reservaya_token')->nullable();
            $table->boolean('verify_with_api')->default(false);
            $table->text('webhook_secret');

            $table->string('fastsms_base_url')->nullable();
            $table->text('fastsms_token')->nullable();

            $table->string('timezone')->default('America/Mexico_City');
            $table->string('fastsms_timezone')->nullable();
            $table->string('phone_prefix', 8)->default('+52');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('origins');
    }
};
