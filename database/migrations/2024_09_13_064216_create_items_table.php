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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->nullable();
            $table->unsignedBigInteger('item_unit_id')->nullable();
            $table->unsignedBigInteger('item_category_id')->nullable();
            $table->foreign('item_unit_id')->on('units')
                ->references('id');
            $table->foreign('item_category_id')->on('categories')
                ->references('id');

            $table->string('item_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_barcode')->nullable();
            $table->smallInteger('item_status')->nullable()->default(0);
            $table->string('item_unit_price')->nullable();
            $table->string('item_unit_cost')->nullable();
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
        Schema::dropIfExists('items');
    }
};
