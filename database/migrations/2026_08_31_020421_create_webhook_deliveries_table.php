<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('lookup_code')->nullable()->index();
            $table->string('external_event_id')->nullable();
            $table->string('payload_hash', 64)->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('status')->default('recibido')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['origin_id', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
