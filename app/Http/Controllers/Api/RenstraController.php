<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Immediate;
use App\Models\Intermediate;
use App\Models\Output;
use App\Models\PohonKinerja;
use App\Models\Ultimate;
use Illuminate\Http\Request;

class RenstraController extends Controller
{
    /**
     * Mengambil data Pohon Kinerja
     * dan mengubah formatnya agar sesuai dengan frontend Renstra.
     *
     * FILTER BIDANG (baru):
     * Route ini WAJIB dilindungi middleware auth:sanctum supaya
     * $request->user() terisi.
     *   - role user = admin        -> semua Intermediate ditampilkan
     *   - role user = bidang lain  -> hanya Intermediate yang kolom
     *                                 `bidang`-nya sama dengan role
     *                                 user itu yang ditampilkan
     */
    public function index(Request $request)
    {
        try {
            $tahun = $request->query('tahun');
            $unitKerja = $request->query('unit_kerja');
            $user = $request->user();

            $query = PohonKinerja::with([
                'ultimates.intermediates' => function ($q) use ($user) {
                    if ($user && $user->role !== 'admin') {
                        $q->where('bidang', $user->role);
                    }
                },
                'ultimates.intermediates.immediates.outputs',
            ]);

            if ($tahun) {
                $query->where('tahun', $tahun);
            }

            if ($unitKerja) {
                $query->where('unit_kerja', $unitKerja);
            }

            $pohonKinerjas = $query->get();

            if ($pohonKinerjas->isEmpty()) {
                return response()->json([
                    'message' => 'Data Renstra tidak ditemukan.',
                    'archived' => false,
                    'data' => []
                ], 404);
            }

            $activeOnes = $pohonKinerjas->where('is_archived', false)->values();

            if ($activeOnes->isEmpty()) {
                return response()->json([
                    'message' => 'Data sudah diarsipkan.',
                    'archived' => true,
                    'data' => [],
                ], 200);
            }

            // Mapping data sesuai struktur frontend Renstra
            $data = $activeOnes->map(function ($pohonKinerja) use ($user) {

                return [
                    'id' => (string) $pohonKinerja->id,
                    'tahun' => (string) $pohonKinerja->tahun,
                    'unitKerja' => $pohonKinerja->unit_kerja,

                    'ultimates' => $pohonKinerja->ultimates
                        ->map(function ($ultimate) {

                            return [
                                'id' => (string) $ultimate->id,
                                'title' => $ultimate->ultimate,
                                'tujuan' => $ultimate->tujuan_ultimate,
                                'indicator' => $ultimate->indikator_ultimate,
                                'target' => $ultimate->target_satuan_ultimate,

                                'intermediates' => $ultimate->intermediates
                                    ->map(function ($intermediate) {

                                        return [
                                            'id' => (string) $intermediate->id,
                                            'title' => $intermediate->intermediate,
                                            'sasaran' => $intermediate->sasaran,
                                            'bidang' => $intermediate->bidang,
                                            // Satu Intermediate bisa punya lebih dari satu
                                            // indikator, dan tiap indikator punya target &
                                            // satuannya sendiri (array paralel di DB).
                                            'indicators' => $this->buildIndicatorRows($intermediate),

                                            'immediates' => $intermediate->immediates
                                                ->map(function ($immediate) {

                                                    return [
                                                        'id' => (string) $immediate->id,
                                                        'title' => $immediate->immediate,
                                                        'program' => $immediate->program_immediate,
                                                        'nomenklaturSipd' =>
                                                            $immediate->nomenklatur_sipd_immediate,
                                                        'indicator' =>
                                                            $immediate->indikator_immediate,
                                                        'target' =>
                                                            $immediate->target_satuan_immediate,

                                                        'outputs' => $immediate->outputs
                                                            ->map(function ($output) {

                                                                return [
                                                                    'id' => (string) $output->id,

                                                                    'title' =>
                                                                        $output->output,

                                                                    'kegiatan' =>
                                                                        $output->kegiatan_output,

                                                                    'nomenklaturSipd' =>
                                                                        $output->nomenklatur_sipd_output,

                                                                    'indicator' =>
                                                                        $output->indikator_output,

                                                                    'target' =>
                                                                        $output->target_satuan_output,

                                                                    'outputInput' =>
                                                                        $output->input_output,

                                                                    'anggaran' =>
                                                                        (int) $output->anggaran,

                                                                    'subKegiatan' => [
                                                                        [
                                                                            'id' =>
                                                                                'sub-' . $output->id,

                                                                            'title' =>
                                                                                $output->sub_kegiatan_output,

                                                                            'nomenklaturSipd' =>
                                                                                $output->nomenklatur_sipd_sub_kegiatan_output,

                                                                            'indicator' =>
                                                                                $output->indikator_sub_kegiatan_output,

                                                                            'target' =>
                                                                                $output->target_satuan_sub_kegiatan_output,
                                                                        ]
                                                                    ]
                                                                ];
                                                            })
                                                            ->values()
                                                            ->toArray(),
                                                    ];
                                                })
                                                ->values()
                                                ->toArray(),
                                        ];
                                    })
                                    ->values()
                                    ->toArray(),
                            ];
                        })
                        // Kalau bukan admin, Ultimate yang jadi kosong
                        // (semua Intermediate-nya bukan bidang dia,
                        // sudah terfilter habis di query) tidak usah
                        // ikut ditampilkan.
                        ->filter(function ($ultimate) use ($user) {
                            if (!$user || $user->role === 'admin') {
                                return true;
                            }
                            return count($ultimate['intermediates']) > 0;
                        })
                        ->values()
                        ->toArray(),
                ];
            });

            return response()->json([
                'message' => 'Data Renstra berhasil diambil.',
                'archived' => false,
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Gagal mengambil data Renstra.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/renstra/nodes/{level}/{id}
     *
     * Renstra TIDAK BOLEH mengubah nama/title node atau bidang
     * (itu milik Pohon Kinerja). Renstra hanya mengisi/mengubah
     * kolom "isi" tiap level:
     *   - ultimate      : tujuan, indicator, target
     *   - intermediate  : sasaran, indicator, target
     *   - immediate     : program, nomenklaturSipd, indicator, target
     *   - output        : kegiatan, nomenklaturSipd, indicator, target,
     *                      outputInput, anggaran
     *   - sub-kegiatan  : title, nomenklaturSipd, indicator, target
     *                     (disimpan di kolom sub_kegiatan_* milik Output
     *                      yang sama, $id di sini = id Output-nya)
     */
    public function updateNode(Request $request, string $level, int $id)
    {
        $level = strtoupper(str_replace('-', ' ', $level));

        $validated = $request->validate([
            'title' => ['nullable', 'string'],
            'tujuan' => ['nullable', 'string'],
            'sasaran' => ['nullable', 'string'],
            'program' => ['nullable', 'string'],
            'kegiatan' => ['nullable', 'string'],
            'nomenklaturSipd' => ['nullable', 'string'],
            'outputInput' => ['nullable', 'string'],
            'anggaran' => ['nullable', 'numeric', 'min:0'],
            'indicator' => ['nullable', 'string'],
            'target' => ['nullable', 'string'],

            // Khusus Intermediate: daftar indikator berpasangan target & satuan.
            'indicators' => ['nullable', 'array'],
            'indicators.*.indicator' => ['required', 'string'],
            'indicators.*.target' => ['nullable', 'string'],
            'indicators.*.satuan' => ['nullable', 'string'],
        ]);

        [$model, $fields] = match ($level) {
            'ULTIMATE' => [
                Ultimate::find($id),
                [
                    'tujuan_ultimate' => $validated['tujuan'] ?? '',
                    'indikator_ultimate' => $validated['indicator'] ?? '',
                    'target_satuan_ultimate' => $validated['target'] ?? '',
                ],
            ],

            'INTERMEDIATE' => [
                Intermediate::find($id),
                $this->buildIntermediateFields($validated),
            ],

            'IMMEDIATE' => [
                Immediate::find($id),
                [
                    'program_immediate' => $validated['program'] ?? '',
                    'nomenklatur_sipd_immediate' => $validated['nomenklaturSipd'] ?? '',
                    'indikator_immediate' => $validated['indicator'] ?? '',
                    'target_satuan_immediate' => $validated['target'] ?? '',
                ],
            ],

            'OUTPUT' => [
                Output::find($id),
                [
                    'kegiatan_output' => $validated['kegiatan'] ?? '',
                    'nomenklatur_sipd_output' => $validated['nomenklaturSipd'] ?? '',
                    'indikator_output' => $validated['indicator'] ?? '',
                    'target_satuan_output' => $validated['target'] ?? '',
                    'input_output' => $validated['outputInput'] ?? '',
                    'anggaran' => $validated['anggaran'] ?? 0,
                ],
            ],

            'SUB KEGIATAN' => [
                Output::find($id),
                [
                    'sub_kegiatan_output' => $validated['title'] ?? '',
                    'nomenklatur_sipd_sub_kegiatan_output' => $validated['nomenklaturSipd'] ?? '',
                    'indikator_sub_kegiatan_output' => $validated['indicator'] ?? '',
                    'target_satuan_sub_kegiatan_output' => $validated['target'] ?? '',
                ],
            ],

            default => [null, null],
        };

        if (!$fields) {
            return response()->json(['message' => 'Level node tidak valid.'], 422);
        }

        if (!$model) {
            return response()->json(['message' => 'Node tidak ditemukan.'], 404);
        }

        $model->update($fields);

        return response()->json([
            'message' => 'Perubahan berhasil disimpan.',
            'data' => $model->fresh(),
        ]);
    }

    /**
     * DELETE /api/renstra/nodes/{level}/{id}
     *
     * Hapus Intermediate/Immediate/Output otomatis ikut menghapus seluruh
     * turunannya karena foreign key di migration sudah onDelete('cascade').
     * Ultimate tidak boleh dihapus dari Renstra.
     * Sub Kegiatan bukan row terpisah, jadi "hapus" = mengosongkan kolom
     * sub_kegiatan_* pada Output terkait, Output-nya sendiri tidak terhapus.
     *
     * (Catatan: tombol Hapus sudah dilepas dari UI Renstra, tapi endpoint
     * ini tetap dipertahankan seperti versi asli kamu, tidak dihapus.)
     */
    public function deleteNode(string $level, int $id)
    {
        $level = strtoupper(str_replace('-', ' ', $level));

        if ($level === 'ULTIMATE') {
            return response()->json([
                'message' => 'Ultimate Outcome tidak dapat dihapus dari Renstra.',
            ], 422);
        }

        if ($level === 'SUB KEGIATAN') {
            $output = Output::find($id);

            if (!$output) {
                return response()->json(['message' => 'Node tidak ditemukan.'], 404);
            }

            $output->update([
                'sub_kegiatan_output' => '',
                'nomenklatur_sipd_sub_kegiatan_output' => '',
                'indikator_sub_kegiatan_output' => '',
                'target_satuan_sub_kegiatan_output' => '',
            ]);

            return response()->json([
                'message' => 'Isi Sub Kegiatan berhasil dikosongkan.',
            ]);
        }

        $model = match ($level) {
            'INTERMEDIATE' => Intermediate::find($id),
            'IMMEDIATE' => Immediate::find($id),
            'OUTPUT' => Output::find($id),
            default => null,
        };

        if ($model === null && !in_array($level, ['INTERMEDIATE', 'IMMEDIATE', 'OUTPUT'], true)) {
            return response()->json(['message' => 'Level node tidak valid.'], 422);
        }

        if (!$model) {
            return response()->json(['message' => 'Node tidak ditemukan.'], 404);
        }

        $model->delete();

        return response()->json([
            'message' => 'Data beserta seluruh turunannya berhasil dihapus.',
        ]);
    }

    /**
     * Gabungkan array paralel indikator[] / target[] / satuan[] milik
     * Intermediate jadi daftar baris: [{indicator, target, satuan}, ...].
     * Jumlah baris mengikuti yang terpanjang, jadi kalau target/satuan
     * belum diisi untuk suatu indikator, barisnya tetap muncul (kosong).
     */
    private function buildIndicatorRows(Intermediate $intermediate): array
    {
        $indikator = $intermediate->indikator;
        $target = $intermediate->target;
        $satuan = $intermediate->satuan;

        $count = max(count($indikator), count($target), count($satuan));
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'indicator' => (string) ($indikator[$i] ?? ''),
                'target' => (string) ($target[$i] ?? ''),
                'satuan' => (string) ($satuan[$i] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Susun kolom Intermediate yang akan disimpan dari request Renstra.
     *
     * - indikator/target/satuan disimpan sebagai array paralel (urutannya
     *   dijaga, tidak ada yang dibuang walau target/satuan kosong).
     * - `target_satuan_intermediate` (kolom lama) tetap diisi dari baris
     *   pertama, karena CapaianController masih membaca kolom itu untuk
     *   menghitung persentase capaian.
     * - Kalau request tidak membawa `indicators`, indikator/target yang
     *   sudah tersimpan tidak disentuh.
     */
    private function buildIntermediateFields(array $validated): array
    {
        $fields = [
            'sasaran' => $validated['sasaran'] ?? '',
        ];

        if (!array_key_exists('indicators', $validated)) {
            return $fields;
        }

        $rows = array_values($validated['indicators'] ?? []);

        $indikator = array_map(fn ($r) => trim((string) ($r['indicator'] ?? '')), $rows);
        $target = array_map(fn ($r) => trim((string) ($r['target'] ?? '')), $rows);
        $satuan = array_map(fn ($r) => trim((string) ($r['satuan'] ?? '')), $rows);

        $fields['indikator'] = $indikator;
        $fields['target'] = $target;
        $fields['satuan'] = $satuan;
        $fields['target_satuan_intermediate'] = trim(($target[0] ?? '') . ' ' . ($satuan[0] ?? ''));

        return $fields;
    }

    public function getYears()
    {
        try {
            $years = PohonKinerja::distinct()
                ->orderBy('tahun', 'desc')
                ->pluck('tahun')
                ->toArray();

            return response()->json([
                'message' => 'Tahun berhasil diambil.',
                'data' => $years,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil tahun.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}