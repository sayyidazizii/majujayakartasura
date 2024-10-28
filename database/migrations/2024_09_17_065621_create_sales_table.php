<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('sales_invoice_date')->nullable();
            $table->string('subtotal_amount')->nullable();
            $table->string('subtotal_item')->nullable();
            $table->string('paid_amount')->nullable();
            $table->string('total_amount')->nullable();
            $table->string('change_amount')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('company_id')->default(1)->nullable();
            $table->unsignedBigInteger('created_id')->nullable();
            $table->unsignedBigInteger('updated_id')->nullable();
            $table->unsignedBigInteger('deleted_id')->nullable();
            $table->smallInteger('data_state')->default(0)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
