<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('tx_ref', 100);
            $table->string('chapa_reference', 150)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('ETB');
            $table->enum('status', ['initiated', 'success', 'failed'])->default('initiated');
            $table->string('channel', 50)->nullable()->comment('e.g. telebirr, cbebirr, card');
            $table->json('webhook_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
