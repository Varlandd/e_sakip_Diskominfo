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
        Schema::create('outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immediate_id')->constrained('immediates')->onDelete('cascade');
            $table->string('output');
            $table->string('kegiatan_output');
            $table->string('nomenklatur_sipd_output');
            $table->string('indikator_output');
            $table->string('target_satuan_output');
            $table->string('input_output');
            $table->string('sub_kegiatan_output');
            $table->string('nomenklatur_sipd_sub_kegiatan_output');
            $table->string('indikator_sub_kegiatan_output');
            $table->string('target_satuan_sub_kegiatan_output');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outputs');
    }
};
