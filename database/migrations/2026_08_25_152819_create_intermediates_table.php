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
        Schema::create('intermediates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ultimate_id')->constrained('ultimates')->onDelete('cascade');
            $table->string('intermediate');
            $table->string('sasaran');
            $table->string('indikator_sasaran');
            $table->string('target_satuan_intermediate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intermediates');
    }
};
