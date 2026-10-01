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
        Schema::create('realisasi_anggarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_id')
                  ->constrained('outputs')
                  ->onDelete('cascade');
            $table->unsignedTinyInteger('periode'); // 1: TW I, 2: TW II, 3: TW III, 4: TW IV
            $table->unsignedSmallInteger('tahun');
            $table->decimal('realisasi', 18, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['output_id', 'periode', 'tahun'], 'realisasi_anggaran_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realisasi_anggarans');
    }
};
