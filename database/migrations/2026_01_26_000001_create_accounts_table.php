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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('trial_ends_at')->nullable();
            $table->enum('subscription_status', ['trial', 'active', 'cancelled', 'expired'])->default('trial');
            $table->unsignedInteger('conversations_used')->default(0);
            $table->unsignedInteger('conversations_limit')->default(100);
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
