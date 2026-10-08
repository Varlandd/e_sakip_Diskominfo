<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Output;
use App\Models\PohonKinerja;
use App\Models\RealisasiAnggaran;
use Illuminate\Http\Request;

class RealisasiAnggaranController extends Controller
{
    public const DAFTAR_PERIODE = [
        1 => ['nama' => '3 Bulan Pertama', 'singkatan' => 'TW I', 'rentang' => 'Jan - Mar'],
        2 => ['nama' => '3 Bulan Kedua', 'singkatan' => 'TW II', 'rentang' => 'Apr - Jun'],
        3 => ['nama' => '3 Bulan Ketiga', 'singkatan' => 'TW III', 'rentang' => 'Jul - Sep'],
        4 => ['nama' => '3 Bulan Keempat', 'singkatan' => 'TW IV', 'rentang' => 'Okt - Des'],
    ];

    /**
     * Get all outputs (sub kegiatan) with their quarterly budget realization.
     */
    public function index(Request $request)
    {
        $tahun = (int) $request->query('tahun', date('Y'));
        $user = $request->user();

        // Find non-archived PohonKinerja for this year
        $pohonKinerja = PohonKinerja::where('tahun', $tahun)
            ->where('is_archived', false)
            ->first();

        if (!$pohonKinerja) {
            return response()->json([
                'message' => 'Data pohon kinerja untuk tahun tersebut tidak ditemukan.',
                'data' => [
                    'tahun' => $tahun,
                    'unit_kerja' => null,
                    'summary' => [
                        'total_pagu' => 0,
                        'total_realisasi' => 0,
                        'total_sisa' => 0,
                        'persentase_serapan' => 0,
                    ],
                    'outputs' => [],
                ],
            ], 200);
        }

        // Get outputs through the tree: PohonKinerja -> Ultimate -> Intermediate -> Immediate -> Output
        $outputsQuery = Output::whereHas('immediate.intermediate.ultimate', function ($query) use ($pohonKinerja) {
            $query->where('pohon_kinerja_id', $pohonKinerja->id);
        });

        // Filter by bidang if user is not admin
        if ($user && $user->role !== 'admin') {
            $outputsQuery->whereHas('immediate.intermediate', function ($query) use ($user) {
                $query->where('bidang', $user->role);
            });
        }

        $outputs = $outputsQuery
        ->with([
            'realisasiAnggarans' => function ($query) use ($tahun) {
                $query->where('tahun', $tahun)->orderBy('periode');
            },
            'immediate.intermediate',
        ])
        ->get();

        $overallPagu = 0;
        $overallRealisasi = 0;

        $result = $outputs->map(function ($output) use (&$overallPagu, &$overallRealisasi) {
            $pagu = (float) $output->anggaran;
            $overallPagu += $pagu;

            // Map quarterly realizations
            $realisasiMap = $output->realisasiAnggarans->keyBy('periode');
            $realisasiTriwulan = [];
            $totalRealisasi = 0;

            for ($p = 1; $p <= 4; $p++) {
                $meta = self::DAFTAR_PERIODE[$p];
                if ($realisasiMap->has($p)) {
                    $r = $realisasiMap[$p];
                    $val = (float) $r->realisasi;
                    $persen = $pagu > 0 ? round(($val / $pagu) * 100, 2) : 0;
                    $totalRealisasi += $val;

                    $realisasiTriwulan[] = [
                        'periode' => $p,
                        'nama' => $meta['nama'],
                        'singkatan' => $meta['singkatan'],
                        'rentang' => $meta['rentang'],
                        'realisasi' => $val,
                        'persentase' => $persen,
                        'keterangan' => $r->keterangan,
                    ];
                } else {
                    $realisasiTriwulan[] = [
                        'periode' => $p,
                        'nama' => $meta['nama'],
                        'singkatan' => $meta['singkatan'],
                        'rentang' => $meta['rentang'],
                        'realisasi' => null,
                        'persentase' => null,
                        'keterangan' => null,
                    ];
                }
            }

            $overallRealisasi += $totalRealisasi;

            $sisaAnggaran = max(0, $pagu - $totalRealisasi);
            $persentaseSerapan = $pagu > 0 ? round(($totalRealisasi / $pagu) * 100, 2) : 0;

            // Get parent info
            $intermediate = $output->immediate->intermediate ?? null;

            return [
                'id' => $output->id,
                'sub_kegiatan' => $output->sub_kegiatan_output,
                'kegiatan' => $output->kegiatan_output,
                'nomenklatur_sipd' => $output->nomenklatur_sipd_sub_kegiatan_output,
                'indikator' => $output->indikator_sub_kegiatan_output,
                'target_satuan' => $output->target_satuan_sub_kegiatan_output,
                'sasaran' => $intermediate->sasaran ?? '-',
                'bidang' => $intermediate->bidang ?? '-',
                'pagu' => $pagu,
                'realisasi_triwulan' => $realisasiTriwulan,
                'total_realisasi' => round($totalRealisasi, 2),
                'sisa_anggaran' => round($sisaAnggaran, 2),
                'persentase_serapan' => $persentaseSerapan,
            ];
        });

        $overallSisa = max(0, $overallPagu - $overallRealisasi);
        $overallPersen = $overallPagu > 0 ? round(($overallRealisasi / $overallPagu) * 100, 2) : 0;

        return response()->json([
            'message' => 'Data realisasi anggaran berhasil diambil.',
            'data' => [
                'tahun' => $tahun,
                'unit_kerja' => $pohonKinerja->unit_kerja,
                'summary' => [
                    'total_pagu' => round($overallPagu, 2),
                    'total_realisasi' => round($overallRealisasi, 2),
                    'total_sisa' => round($overallSisa, 2),
                    'persentase_serapan' => $overallPersen,
                ],
                'outputs' => $result,
            ],
        ], 200);
    }

    /**
     * Store or update quarterly budget realization for an output (sub kegiatan).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'output_id' => ['required', 'integer', 'exists:outputs,id'],
            'periode' => ['required', 'integer', 'min:1', 'max:4'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'realisasi' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $output = Output::findOrFail($validated['output_id']);

        $realisasi = RealisasiAnggaran::updateOrCreate(
            [
                'output_id' => $validated['output_id'],
                'periode' => $validated['periode'],
                'tahun' => $validated['tahun'],
            ],
            [
                'realisasi' => $validated['realisasi'],
                'keterangan' => $validated['keterangan'] ?? null,
            ]
        );

        $pagu = (float) $output->anggaran;
        $persen = $pagu > 0 ? round(($realisasi->realisasi / $pagu) * 100, 2) : 0;

        return response()->json([
            'message' => 'Realisasi anggaran berhasil disimpan.',
            'data' => [
                'id' => $realisasi->id,
                'output_id' => $realisasi->output_id,
                'periode' => $realisasi->periode,
                'tahun' => $realisasi->tahun,
                'pagu' => $pagu,
                'realisasi' => (float) $realisasi->realisasi,
                'persentase' => $persen,
                'keterangan' => $realisasi->keterangan,
            ],
        ], 200);
    }

    /**
     * Get summary statistics for Realisasi Anggaran in a given year.
     */
    public function summary(Request $request)
    {
        $tahun = (int) $request->query('tahun', date('Y'));
        $user = $request->user();

        $pohonKinerja = PohonKinerja::where('tahun', $tahun)
            ->where('is_archived', false)
            ->first();

        if (!$pohonKinerja) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'data' => [
                    'tahun' => $tahun,
                    'total_pagu' => 0,
                    'total_realisasi' => 0,
                    'total_sisa' => 0,
                    'persentase_serapan' => 0,
                ],
            ], 200);
        }

        $outputsQuery = Output::whereHas('immediate.intermediate.ultimate', function ($query) use ($pohonKinerja) {
            $query->where('pohon_kinerja_id', $pohonKinerja->id);
        });

        // Filter by bidang if user is not admin
        if ($user && $user->role !== 'admin') {
            $outputsQuery->whereHas('immediate.intermediate', function ($query) use ($user) {
                $query->where('bidang', $user->role);
            });
        }

        $outputs = $outputsQuery
        ->with([
            'realisasiAnggarans' => function ($query) use ($tahun) {
                $query->where('tahun', $tahun);
            },
        ])
        ->get();

        $totalPagu = 0;
        $totalRealisasi = 0;

        foreach ($outputs as $output) {
            $totalPagu += (float) $output->anggaran;
            $totalRealisasi += (float) $output->realisasiAnggarans->sum('realisasi');
        }

        $sisa = max(0, $totalPagu - $totalRealisasi);
        $persen = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0;

        return response()->json([
            'message' => 'Ringkasan realisasi anggaran berhasil diambil.',
            'data' => [
                'tahun' => $tahun,
                'total_pagu' => round($totalPagu, 2),
                'total_realisasi' => round($totalRealisasi, 2),
                'total_sisa' => round($sisa, 2),
                'persentase_serapan' => $persen,
            ],
        ], 200);
    }
}
