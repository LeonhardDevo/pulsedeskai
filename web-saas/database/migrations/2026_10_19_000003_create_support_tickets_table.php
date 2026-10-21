<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_chat_id')->index();
            $table->string('customer_name');
            $table->string('customer_username')->nullable();
            $table->string('status')->default('ai_handling'); // ai_handling, escalated, resolved
            $table->boolean('was_deflected')->default(true);
            $table->string('intent')->default('faq_question');
            $table->string('escalation_reason')->nullable();
            $table->float('avg_confidence')->default(0.85);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
