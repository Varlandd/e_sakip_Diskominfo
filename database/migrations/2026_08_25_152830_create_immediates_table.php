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
        Schema::create('immediates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intermediate_id')->constrained('intermediates')->onDelete('cascade');
            $table->string('immediate');
            $table->string('program_immediate');
            $table->string('nomenklatur_sipd_immediate');
            $table->string('indikator_immediate');
            $table->string('target_satuan_immediate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immediates');
    }
};
