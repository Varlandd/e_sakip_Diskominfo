<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('intermediates', function (Blueprint $table) {
            $table->text('indikator_sasaran')->nullable()->change();
            $table->text('target_satuan_intermediate')->nullable()->change();

            if (!Schema::hasColumn('intermediates', 'indikator')) {
                $table->json('indikator')->nullable()->after('indikator_sasaran');
            }
            if (!Schema::hasColumn('intermediates', 'target')) {
                $table->json('target')->nullable()->after('target_satuan_intermediate');
            }
            if (!Schema::hasColumn('intermediates', 'satuan')) {
                $table->json('satuan')->nullable()->after('target');
            }
        });

        // Migrate existing rows to multivalue format
        $rows = DB::table('intermediates')->get();
        foreach ($rows as $row) {
            $indikator = [];
            if (!empty($row->indikator_sasaran)) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $row->indikator_sasaran))));
                $indikator = !empty($lines) ? $lines : [trim((string) $row->indikator_sasaran)];
            }

            $target = [];
            $satuan = [];
            if (!empty($row->target_satuan_intermediate)) {
                $ts = trim((string) $row->target_satuan_intermediate);
                // Try to extract number and unit e.g. "75/ indeks" or "100 %"
                if (preg_match('/^([\d]+(?:[.,][\d]+)?)\s*(?:\/|\s)\s*(.*)$/', $ts, $m)) {
                    $target[] = $m[1];
                    $satuan[] = trim($m[2]);
                } else {
                    $target[] = $ts;
                    $satuan[] = '';
                }
            }

            DB::table('intermediates')
                ->where('id', $row->id)
                ->update([
                    'indikator' => json_encode($indikator),
                    'target' => json_encode($target),
                    'satuan' => json_encode($satuan),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intermediates', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('intermediates', 'indikator')) {
                $columnsToDrop[] = 'indikator';
            }
            if (Schema::hasColumn('intermediates', 'target')) {
                $columnsToDrop[] = 'target';
            }
            if (Schema::hasColumn('intermediates', 'satuan')) {
                $columnsToDrop[] = 'satuan';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
