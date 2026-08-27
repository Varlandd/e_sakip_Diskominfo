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
        Schema::create('ultimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pohon_kinerja_id')->constrained('pohon_kinerjas')->onDelete('cascade');
            $table->string('ultimate');
            $table->text('tujuan_ultimate');
            $table->string('indikator_ultimate');
            $table->string('target_satuan_ultimate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ultimates');
    }
};
