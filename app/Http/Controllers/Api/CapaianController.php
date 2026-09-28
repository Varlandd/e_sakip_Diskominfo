<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Capaian;
use App\Models\Intermediate;
use App\Models\PohonKinerja;
use Illuminate\Http\Request;

class CapaianController extends Controller
{
    /**
     * Parse target_satuan string into numeric target and satuan.
     * Examples: "100 %" => [100, "%"], "3.8 Indeks" => [3.8, "Indeks"], "A" => [0, "A"]
     */
    private function parseTargetSatuan(?string $targetSatuan): array
    {
        if (!$targetSatuan || trim($targetSatuan) === '') {
            return [0, ''];
        }

        $targetSatuan = trim($targetSatuan);

        // Try to extract leading number (int or decimal)
        if (preg_match('/^([\d]+(?:[.,][\d]+)?)\s*(.*)$/', $targetSatuan, $matches)) {
            $number = (float) str_replace(',', '.', $matches[1]);
            $unit = trim($matches[2]) ?: '';
            return [$number, $unit];
        }

        // No leading number found, return 0 with the full string as satuan
        return [0, $targetSatuan];
    }

    public const DAFTAR_PERIODE = [
        1 => ['nama' => '3 Bulan Pertama', 'singkatan' => 'TW I', 'rentang' => 'Jan - Mar'],
        2 => ['nama' => '3 Bulan Kedua', 'singkatan' => 'TW II', 'rentang' => 'Apr - Jun'],
        3 => ['nama' => '3 Bulan Ketiga', 'singkatan' => 'TW III', 'rentang' => 'Jul - Sep'],
        4 => ['nama' => '3 Bulan Keempat', 'singkatan' => 'TW IV', 'rentang' => 'Okt - Des'],
    ];

    /**
     * Get all intermediates with their quarterly (per 3 bulan) capaian data for a given year.
     */
    public function index(Request $request)
    {
        $tahun = $request->query('tahun', date('Y'));

        // Find the PohonKinerja for this year
        $pohonKinerja = PohonKinerja::where('tahun', $tahun)
            ->where('is_archived', false)
            ->first();

        if (!$pohonKinerja) {
            return response()->json([
                'message' => 'Data pohon kinerja untuk tahun tersebut tidak ditemukan.',
                'data' => [
                    'tahun' => (int) $tahun,
                    'unit_kerja' => null,
                    'intermediates' => [],
                ],
            ], 200);
        }

        // Get intermediates through ultimate relationship
        $intermediates = Intermediate::whereHas('ultimate', function ($query) use ($pohonKinerja) {
            $query->where('pohon_kinerja_id', $pohonKinerja->id);
        })->with(['capaians' => function ($query) use ($tahun) {
            $query->where('tahun', $tahun)->orderBy('bulan');
        }])->get();

        $result = $intermediates->map(function ($intermediate) use ($tahun) {
            [$target, $satuan] = $this->parseTargetSatuan($intermediate->target_satuan_intermediate);

            // Build data 4 periode (per 3 bulan)
            $capaianMap = $intermediate->capaians->keyBy('bulan');
            $capaianTriwulan = [];
            $totalRealisasi = 0;
            $filledPeriods = 0;

            for ($p = 1; $p <= 4; $p++) {
                $meta = self::DAFTAR_PERIODE[$p];
                if ($capaianMap->has($p)) {
                    $c = $capaianMap[$p];
                    $realisasi = (float) $c->realisasi;
                    $persentase = $target > 0 ? round(($realisasi / $target) * 100, 2) : 0;
                    $totalRealisasi += $realisasi;
                    $filledPeriods++;

                    $capaianTriwulan[] = [
                        'periode' => $p,
                        'bulan' => $p,
                        'nama' => $meta['nama'],
                        'singkatan' => $meta['singkatan'],
                        'rentang' => $meta['rentang'],
                        'realisasi' => $realisasi,
                        'persentase' => $persentase,
                        'keterangan' => $c->keterangan,
                    ];
                } else {
                    $capaianTriwulan[] = [
                        'periode' => $p,
                        'bulan' => $p,
                        'nama' => $meta['nama'],
                        'singkatan' => $meta['singkatan'],
                        'rentang' => $meta['rentang'],
                        'realisasi' => null,
                        'persentase' => null,
                        'keterangan' => null,
                    ];
                }
            }

            $rataRata = $filledPeriods > 0 && $target > 0
                ? round(($totalRealisasi / $target) * 100, 2)
                : 0;

            return [
                'id' => $intermediate->id,
                'sasaran' => $intermediate->sasaran,
                'indikator' => $intermediate->indikator_sasaran,
                'target' => $target,
                'satuan' => $satuan,
                'target_satuan_raw' => $intermediate->target_satuan_intermediate,
                'capaian_triwulan' => $capaianTriwulan,
                'capaian_bulanan' => $capaianTriwulan,
                'total_realisasi' => round($totalRealisasi, 2),
                'rata_rata_persentase' => $rataRata,
            ];
        });

        return response()->json([
            'message' => 'Data capaian berhasil diambil.',
            'data' => [
                'tahun' => (int) $tahun,
                'unit_kerja' => $pohonKinerja->unit_kerja,
                'intermediates' => $result,
            ],
        ], 200);
    }

    /**
     * Store or update a quarterly (per 3 bulan) capaian record (upsert).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intermediate_id' => ['required', 'integer', 'exists:intermediates,id'],
            'periode' => ['nullable', 'integer', 'min:1', 'max:4'],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:4'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'realisasi' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $periode = $validated['periode'] ?? $validated['bulan'] ?? 1;

        // Parse target and satuan from intermediate
        $intermediate = Intermediate::findOrFail($validated['intermediate_id']);
        [$target, $satuan] = $this->parseTargetSatuan($intermediate->target_satuan_intermediate);

        $capaian = Capaian::updateOrCreate(
            [
                'intermediate_id' => $validated['intermediate_id'],
                'bulan' => $periode,
                'tahun' => $validated['tahun'],
            ],
            [
                'target' => $target,
                'realisasi' => $validated['realisasi'],
                'satuan' => $satuan,
                'keterangan' => $validated['keterangan'] ?? null,
            ]
        );

        $persentase = $target > 0 ? round(($capaian->realisasi / $target) * 100, 2) : 0;

        return response()->json([
            'message' => 'Capaian berhasil disimpan.',
            'data' => [
                'id' => $capaian->id,
                'intermediate_id' => $capaian->intermediate_id,
                'periode' => $capaian->bulan,
                'bulan' => $capaian->bulan,
                'tahun' => $capaian->tahun,
                'target' => (float) $capaian->target,
                'realisasi' => (float) $capaian->realisasi,
                'satuan' => $capaian->satuan,
                'persentase' => $persentase,
                'keterangan' => $capaian->keterangan,
            ],
        ], 200);
    }

    /**
     * Get summary statistics for capaian in a given year.
     */
    public function summary(Request $request)
    {
        $tahun = $request->query('tahun', date('Y'));

        $pohonKinerja = PohonKinerja::where('tahun', $tahun)
            ->where('is_archived', false)
            ->first();

        if (!$pohonKinerja) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'data' => [
                    'tahun' => (int) $tahun,
                    'total_intermediate' => 0,
                    'rata_rata_capaian' => 0,
                    'capaian_tertinggi' => 0,
                    'capaian_terendah' => 0,
                ],
            ], 200);
        }

        $intermediates = Intermediate::whereHas('ultimate', function ($query) use ($pohonKinerja) {
            $query->where('pohon_kinerja_id', $pohonKinerja->id);
        })->with(['capaians' => function ($query) use ($tahun) {
            $query->where('tahun', $tahun);
        }])->get();

        $percentages = [];

        foreach ($intermediates as $intermediate) {
            [$target, ] = $this->parseTargetSatuan($intermediate->target_satuan_intermediate);
            if ($target <= 0) continue;

            $totalRealisasi = $intermediate->capaians->sum('realisasi');
            $persen = round(($totalRealisasi / $target) * 100, 2);
            $percentages[] = $persen;
        }

        $totalIntermediate = $intermediates->count();
        $rataRata = count($percentages) > 0 ? round(array_sum($percentages) / count($percentages), 2) : 0;
        $tertinggi = count($percentages) > 0 ? max($percentages) : 0;
        $terendah = count($percentages) > 0 ? min($percentages) : 0;

        return response()->json([
            'message' => 'Ringkasan capaian berhasil diambil.',
            'data' => [
                'tahun' => (int) $tahun,
                'total_intermediate' => $totalIntermediate,
                'rata_rata_capaian' => $rataRata,
                'capaian_tertinggi' => $tertinggi,
                'capaian_terendah' => $terendah,
            ],
        ], 200);
    }

    public function quarterlySummary(Request $request)
    {
        $tahun = $request->query('tahun', date('Y'));
 
        $pohonKinerja = PohonKinerja::where('tahun', $tahun)
            ->where('is_archived', false)
            ->first();
 
        if (!$pohonKinerja) {
            $emptyQuarters = collect(self::DAFTAR_PERIODE)->map(function ($meta, $periode) {
                return [
                    'periode' => $periode,
                    'nama' => $meta['nama'],
                    'singkatan' => $meta['singkatan'],
                    'rentang' => $meta['rentang'],
                    'rata_rata_persentase' => null,
                    'jumlah_intermediate_terisi' => 0,
                ];
            })->values();
 
            return response()->json([
                'message' => 'Data pohon kinerja untuk tahun tersebut tidak ditemukan.',
                'data' => [
                    'tahun' => (int) $tahun,
                    'quarters' => $emptyQuarters,
                ],
            ], 200);
        }
 
        $intermediates = Intermediate::whereHas('ultimate', function ($query) use ($pohonKinerja) {
            $query->where('pohon_kinerja_id', $pohonKinerja->id);
        })->with(['capaians' => function ($query) use ($tahun) {
            $query->where('tahun', $tahun);
        }])->get();
 
        $quarters = [];
 
        for ($periode = 1; $periode <= 4; $periode++) {
            $meta = self::DAFTAR_PERIODE[$periode];
            $percentages = [];
 
            foreach ($intermediates as $intermediate) {
                [$target, ] = $this->parseTargetSatuan($intermediate->target_satuan_intermediate);
 
                if ($target <= 0) {
                    continue;
                }
 
                $capaian = $intermediate->capaians->firstWhere('bulan', $periode);
 
                // Triwulan ini belum diisi realisasinya untuk intermediate ini,
                // jadi tidak ikut dihitung ke rata-rata (bukan dianggap 0%).
                if (!$capaian) {
                    continue;
                }
 
                $percentages[] = round(($capaian->realisasi / $target) * 100, 2);
            }
 
            $quarters[] = [
                'periode' => $periode,
                'nama' => $meta['nama'],
                'singkatan' => $meta['singkatan'],
                'rentang' => $meta['rentang'],
                'rata_rata_persentase' => count($percentages) > 0
                    ? round(array_sum($percentages) / count($percentages), 2)
                    : null,
                'jumlah_intermediate_terisi' => count($percentages),
            ];
        }
 
        return response()->json([
            'message' => 'Ringkasan capaian per triwulan berhasil diambil.',
            'data' => [
                'tahun' => (int) $tahun,
                'unit_kerja' => $pohonKinerja->unit_kerja,
                'total_intermediate' => $intermediates->count(),
                'quarters' => $quarters,
            ],
        ], 200);
    }
}
