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
        Schema::table('pohon_kinerjas', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('tahun');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->string('unit_kerja')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pohon_kinerjas', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'archived_at', 'unit_kerja']);
        });
    }
};
