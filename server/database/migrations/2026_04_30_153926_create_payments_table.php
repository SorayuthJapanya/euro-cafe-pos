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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->enum('method', ['cash', 'qr_promptpay']);
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->decimal('change_amount', 10, 2)->nullable();
            $table->string('qr_reference')->nullable()->index();
            $table->string('qu_image_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
