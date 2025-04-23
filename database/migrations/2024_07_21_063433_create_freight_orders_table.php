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
        Schema::create('freight_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 20)->nullable();
            $table->float('from_lat', 8, 2)->nullable();
            $table->float('from_lng', 8, 2)->nullable();
            $table->float('to_lat', 8, 2)->nullable();
            $table->float('to_lng', 8, 2)->nullable();
            $table->float('amount', 8, 2)->nullable();
            $table->string('description')->nullable();
            $table->integer('car_type')->nullable();
            $table->integer('user_id')->nullable();
            $table->dateTime('pickup_datetime')->nullable();
            $table->string('options')->nullable();
            $table->enum('status', ['active', 'accepted', 'in-process', 'complete', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freight_orders');
    }
};
