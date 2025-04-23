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
        Schema::create('driver_proofs', function (Blueprint $table) {
            $table->id();
            $table->string('driver_id');

            $table->text('selfie');
            $table->text('selfie_with_nic_licence');
            $table->text('nic_front');
            $table->text('nic_back');
            $table->text('licence');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_proofs');
    }
};
