<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('telegram_bot_token')->nullable();
            $table->string('ai_provider')->default('openrouter'); // openrouter, opencode, claude, openai, mock
            $table->text('ai_api_key')->nullable();
            $table->string('ai_model')->default('openai/gpt-4o-mini');
            $table->float('confidence_threshold')->default(0.65);
            $table->text('system_prompt')->nullable();
            $table->string('plan')->default('starter'); // starter, growth, scale
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('subscription_status')->default('active');
            $table->integer('monthly_quota')->default(500);
            $table->integer('used_quota')->default(0);
            $table->float('cost_per_human_ticket')->default(4.50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
