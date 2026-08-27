<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pohon_kinerjas', 'unit_kerja')) {
            Schema::table('pohon_kinerjas', function (Blueprint $table) {
                $table->string('unit_kerja')->after('tahun');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pohon_kinerjas', 'unit_kerja')) {
            Schema::table('pohon_kinerjas', function (Blueprint $table) {
                $table->dropColumn('unit_kerja');
            });
        }
    }
};
