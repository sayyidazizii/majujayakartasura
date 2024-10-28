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
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->foreign('sales_invoice_id')->on('sales')
                ->references('id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('item_unit_id')->nullable();
            $table->unsignedBigInteger('item_category_id')->nullable();
            $table->integer('last_balance')->nullable();
            $table->foreign('item_id')->on('items')
                ->references('id');
            $table->foreign('item_unit_id')->on('units')
                ->references('id');
            $table->foreign('item_category_id')->on('categories')
                ->references('id');
            $table->integer('quantity')->nullable();
            $table->string('item_unit_price')->nullable();
            $table->string('subtotal_amount')->nullable();
            $table->string('subtotal_amount_after_discount')->nullable();
            $table->string('discount_percentage')->nullable();
            $table->string('discount_amount')->nullable();
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
        Schema::dropIfExists('sales_items');
    }
};
