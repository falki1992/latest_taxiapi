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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id',20)->nullable();
            $table->float('from_lat', 8, 2)->nullable();
            $table->float('from_lng', 8, 2)->nullable();
            $table->float('to_lat', 8, 2)->nullable();
            $table->float('to_lng', 8, 2)->nullable();

            $table->float('amount', 8, 2)->nullable();
            $table->integer('passengers')->nullable();
            $table->string('comments')->nullable();
            $table->integer('car_type')->nullable();
            $table->integer('user_id')->nullable();
            $table->enum('status', ['active', 'in-process', 'complete'])->default('active');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
