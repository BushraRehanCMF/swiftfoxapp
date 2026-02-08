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
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id')->comment('WhatsApp Business Account ID');
            $table->string('phone_number_id');
            $table->string('phone_number');
            $table->enum('status', ['active', 'disconnected'])->default('active');
            $table->timestamps();

            $table->unique(['account_id']);
            $table->index('phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
