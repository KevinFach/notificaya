<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger')->index();
            $table->unsignedInteger('offset_value')->nullable();
            $table->string('offset_unit')->nullable();
            $table->text('template');
            $table->json('service_ids')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
