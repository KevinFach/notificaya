<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_rule_id')->nullable()->constrained()->nullOnDelete();

            $table->string('trigger')->index();
            $table->string('destinatario')->nullable();
            $table->text('numero');
            $table->text('cuerpo');

            $table->timestamp('scheduled_for')->nullable()->index();
            $table->string('status')->default('pendiente')->index();

            $table->string('fastsms_msg_id')->nullable()->index();
            $table->string('fastsms_status')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'notification_rule_id'], 'outbound_messages_appointment_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
