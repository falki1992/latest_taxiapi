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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname', 100)->nullable();
            $table->string('lastname', 100)->nullable();
            $table->text('avatar')->nullable();
            $table->string('mobile_no', 20)->unique();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('otp')->nullable();
            $table->dateTime('otp_expiry')->nullable();
            $table->text('fcm')->nullable();
            $table->string('password')->nullable();
            $table->float('lat', 8, 2)->nullable();
            $table->float('lng', 8, 2)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->integer('user_type')->nullable();
            $table->float('wallet', 8, 2)->default(0);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
