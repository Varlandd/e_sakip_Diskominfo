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
        Schema::create('capaians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intermediate_id')
                  ->constrained('intermediates')
                  ->onDelete('cascade');
            $table->unsignedTinyInteger('indikator_index')->default(0); // index of indikator within intermediate
            $table->unsignedTinyInteger('bulan'); // 1-4 (triwulan)
            $table->unsignedSmallInteger('tahun');
            $table->decimal('target', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->string('satuan', 100)->default('');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['intermediate_id', 'indikator_index', 'bulan', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capaians');
    }
};
