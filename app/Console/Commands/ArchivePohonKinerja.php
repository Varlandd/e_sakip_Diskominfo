<?php

namespace App\Console\Commands;

use App\Models\PohonKinerja;
use Illuminate\Console\Command;

class ArchivePohonKinerja extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pohon-kinerja:archive {--tahun=} {--all}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Archive pohon kinerja dari tahun tertentu atau semua tahun sebelumnya';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tahun = $this->option('tahun');
        $archiveAll = $this->option('all');

        try {
            if ($archiveAll) {
                // Archive semua tahun kecuali tahun saat ini
                $currentYear = now()->year;
                $pohonKinerjas = PohonKinerja::active()
                    ->where('tahun', '<', $currentYear)
                    ->get();

                foreach ($pohonKinerjas as $pk) {
                    $pk->archive();
                    $this->info("✓ Pohon Kinerja tahun {$pk->tahun} berhasil diarsipkan.");
                }

                $count = $pohonKinerjas->count();
                $this->info("Total {$count} Pohon Kinerja berhasil diarsipkan.");
            } elseif ($tahun) {
                // Archive tahun spesifik
                $pohonKinerja = PohonKinerja::active()
                    ->where('tahun', $tahun)
                    ->first();

                if (!$pohonKinerja) {
                    $this->error("Pohon Kinerja tahun {$tahun} tidak ditemukan atau sudah diarsipkan.");
                    return 1;
                }

                $pohonKinerja->archive();
                $this->info("✓ Pohon Kinerja tahun {$tahun} berhasil diarsipkan.");
            } else {
                // Archive tahun sebelumnya (otomatis)
                $lastYear = now()->subYear()->year;
                $pohonKinerja = PohonKinerja::active()
                    ->where('tahun', $lastYear)
                    ->first();

                if (!$pohonKinerja) {
                    $this->info("Tidak ada Pohon Kinerja tahun {$lastYear} untuk diarsipkan.");
                    return 0;
                }

                $pohonKinerja->archive();
                $this->info("✓ Pohon Kinerja tahun {$lastYear} berhasil diarsipkan.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
