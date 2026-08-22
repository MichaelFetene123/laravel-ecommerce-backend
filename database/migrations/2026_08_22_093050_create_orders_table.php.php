<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->string('order_number', 50)->unique();
            $table->string('tx_ref', 100)->unique();
            $table->enum('status', ['pending', 'paid', 'failed', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('billing_address_id')->nullable();
            $table->foreign('billing_address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('currency', 10)->default('ETB');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};