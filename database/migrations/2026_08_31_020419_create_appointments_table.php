<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained()->cascadeOnDelete();
            $table->string('lookup_code');
            $table->string('kind')->default('cita')->index();
            $table->string('status')->default('confirmed')->index();

            $table->string('customer_name')->nullable();
            $table->text('customer_phone')->nullable();
            $table->text('customer_email')->nullable();

            $table->unsignedBigInteger('service_external_id')->nullable();
            $table->string('service_name')->nullable();
            $table->string('service_slug')->nullable();

            $table->string('event_name')->nullable();
            $table->string('event_type')->nullable();
            $table->string('responsable_email')->nullable();

            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['origin_id', 'lookup_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
