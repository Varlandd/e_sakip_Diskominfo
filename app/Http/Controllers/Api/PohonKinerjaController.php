<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Immediate;
use App\Models\Intermediate;
use App\Models\Output;
use App\Models\PohonKinerja;
use App\Models\Ultimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PohonKinerjaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'tahun' => ['required', 'integer'],
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240',
            ],
        ]);

        try {
            // Read Excel file
            $sheets = Excel::toArray(
                null,
                $request->file('file')
            );

            if (empty($sheets) || empty($sheets[0])) {
                return response()->json([
                    'message' => 'Gagal mengimport pohon kinerja.',
                    'error' => 'File Excel tidak memiliki data.'
                ], 422);
            }

            $rows = $sheets[0];

            $normalizeHeader = static fn ($value): string => preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $value)));
            $headerMap = [];
            foreach ($rows[0] as $index => $header) {
                $normalized = $normalizeHeader($header);
                if ($normalized !== '' && !array_key_exists($normalized, $headerMap)) {
                    $headerMap[$normalized] = $index;
                }
            }

            $readCell = static function (array $row, array $headers, int $fallback) use ($headerMap, $normalizeHeader): string {
                foreach ($headers as $header) {
                    $normalized = $normalizeHeader($header);
                    if (array_key_exists($normalized, $headerMap)) {
                        return trim((string) ($row[$headerMap[$normalized]] ?? ''));
                    }
                }

                return trim((string) ($row[$fallback] ?? ''));
            };

            // Skip header (row 0)
            $dataRows = array_slice($rows, 1);

            if (empty($dataRows)) {
                return response()->json([
                    'message' => 'Gagal mengimport pohon kinerja.',
                    'error' => 'File Excel tidak memiliki data.'
                ], 422);
            }

            // Process data within transaction
            $result = DB::transaction(function () use ($request, $dataRows, $readCell) {
                // Create PohonKinerja
                $pohonKinerja = PohonKinerja::create([
                    'tahun' => $request->input('tahun'),
                    'unit_kerja' => 'DINAS KOMUNIKASI DAN INFORMATIKA',
                ]);

                // Process rows with fill-down logic
                $ultimateMap = [];     // ultimate_outcome -> ultimate_id
                $intermediateMap = []; // sasaran -> intermediate_id
                $immediateMap = [];    // immediate_outcome -> immediate_id

                $totalUltimate = 0;
                $totalIntermediate = 0;
                $totalImmediate = 0;
                $totalOutput = 0;

                // Track current values for fill-down
                $currentUltimate = null;
                $currentUltimatePurpose = null;
                $currentUltimateIndicator = null;
                $currentUltimateTarget = null;
                $currentIntermediate = null;
                $currentSasaran = null;
                $currentSasaranIndicator = null;
                $currentIntermediateTarget = null;
                $currentImmediate = null;
                $currentImmediateProgram = null;
                $currentImmediateSipd = null;
                $currentImmediateIndicator = null;
                $currentImmediateTarget = null;

                foreach ($dataRows as $row) {
                    $ultimateOutcome = $readCell($row, ['ultimate', 'ultimate_outcome'], 0);
                    if (!empty($ultimateOutcome)) {
                        $currentUltimate = $ultimateOutcome;
                    }

                    $tujuanUltimate = $readCell($row, ['tujuan_ultimate', 'tujuan ultimate'], 1);
                    if (!empty($tujuanUltimate)) {
                        $currentUltimatePurpose = $tujuanUltimate;
                    }

                    $indikatorUltimate = $readCell($row, ['indikator_ultimate', 'indikator ultimate'], 2);
                    if (!empty($indikatorUltimate)) {
                        $currentUltimateIndicator = $indikatorUltimate;
                    }

                    $targetUltimate = $readCell($row, ['target_satuan_ultimate', 'target satuan ultimate'], 3);
                    if (!empty($targetUltimate)) {
                        $currentUltimateTarget = $targetUltimate;
                    }

                    $intermediate = $readCell($row, ['intermediate'], 4);
                    if (!empty($intermediate)) {
                        $currentIntermediate = $intermediate;
                    }

                    $sasaran = $readCell($row, ['sasaran'], 5);
                    if (!empty($sasaran)) {
                        $currentSasaran = $sasaran;
                    }

                    $indikatorSasaran = $readCell($row, ['indikator_sasaran', 'indikator sasaran'], 6);
                    if (!empty($indikatorSasaran)) {
                        $currentSasaranIndicator = $indikatorSasaran;
                    }

                    $targetIntermediate = $readCell($row, ['target_satuan_intermediate', 'target satuan intermediate'], 7);
                    if (!empty($targetIntermediate)) {
                        $currentIntermediateTarget = $targetIntermediate;
                    }

                    $immediateOutcome = $readCell($row, ['immediate', 'immediate_outcome'], 8);
                    if (!empty($immediateOutcome)) {
                        $currentImmediate = $immediateOutcome;
                    }

                    $immediateProgram = $readCell($row, ['program_immediate', 'program immediate'], 9);
                    if (!empty($immediateProgram)) {
                        $currentImmediateProgram = $immediateProgram;
                    }

                    $immediateSipd = $readCell($row, ['nomenklatur_sipd_immediate', 'nomenklatur sipd immediate'], 10);
                    if (!empty($immediateSipd)) {
                        $currentImmediateSipd = $immediateSipd;
                    }

                    $indikatorImmediate = $readCell($row, ['indikator_immediate', 'indikator immediate'], 11);
                    if (!empty($indikatorImmediate)) {
                        $currentImmediateIndicator = $indikatorImmediate;
                    }

                    $immediateTarget = $readCell($row, ['target_satuan_immediate', 'target satuan immediate'], 12);
                    if (!empty($immediateTarget)) {
                        $currentImmediateTarget = $immediateTarget;
                    }

                    $output = $readCell($row, ['output'], 13);
                    $kegiatanOutput = $readCell($row, ['kegiatan_output', 'kegiatan output'], 14);
                    $outputSipd = $readCell($row, ['nomenklatur_sipd_output', 'nomenklatur sipd output'], 15);
                    $indikatorOutput = $readCell($row, ['indikator_output', 'indikator output'], 16);
                    $targetOutput = $readCell($row, ['target_satuan_output', 'target satuan output'], 17);
                    $inputOutput = $readCell($row, ['input_output', 'input output'], 18);
                    $subKegiatanOutput = $readCell($row, ['sub_kegiatan_output', 'sub kegiatan output'], 19);
                    $subKegiatanSipd = $readCell($row, ['nomenklatur_sipd_sub_kegiatan_output', 'nomenklatur sipd sub kegiatan output'], 20);
                    $subKegiatanIndicator = $readCell($row, ['indikator_sub_kegiatan_output', 'indikator sub kegiatan output'], 21);
                    $subKegiatanTarget = $readCell($row, ['target_satuan_sub_kegiatan_output', 'target satuan sub kegiatan output'], 22);
                    $anggaranOutput = $readCell($row, ['anggaran', 'anggaran_output', 'pagu', 'pagu_anggaran', 'anggaran_sub_kegiatan', 'sub_kegiatan_anggaran'], 23);
                    $anggaranDigits = preg_replace('/[^0-9]/', '', (string) $anggaranOutput);
                    $anggaranVal = ($anggaranDigits !== '' && is_numeric($anggaranDigits)) ? (int) $anggaranDigits : 0;

                    $bidangCell = strtolower(trim($readCell($row, ['bidang', 'bidang_intermediate'], -1)));
                    $validBidangList = ['komunikasi', 'statistik', 'persandian', 'aplikasi', 'kesekretariatan'];
                    if (in_array($bidangCell, $validBidangList, true)) {
                        $currentBidang = $bidangCell;
                    }

                    // Create or get Ultimate
                    if (!empty($currentUltimate)) {
                        $ultimateKey = $currentUltimate;
                        if (!isset($ultimateMap[$ultimateKey])) {
                            $ultimate = Ultimate::create([
                                'pohon_kinerja_id' => $pohonKinerja->id,
                                'ultimate' => $currentUltimate,
                                'tujuan_ultimate' => $currentUltimatePurpose ?? '',
                                'indikator_ultimate' => $currentUltimateIndicator ?? '',
                                'target_satuan_ultimate' => $currentUltimateTarget ?? '',
                            ]);
                            $ultimateMap[$ultimateKey] = $ultimate->id;
                            $totalUltimate++;
                        }
                        $ultimateId = $ultimateMap[$ultimateKey];
                    }

                    // Create or get Intermediate
                    if (!empty($currentSasaran) && isset($ultimateId)) {
                        $intermediateKey = $ultimateId . '|' . $currentSasaran;
                        if (!isset($intermediateMap[$intermediateKey])) {
                            $intermediate = Intermediate::create([
                                'ultimate_id' => $ultimateId,
                                'intermediate' => $currentIntermediate ?? '',
                                'sasaran' => $currentSasaran,
                                'bidang' => $currentBidang ?? 'komunikasi',
                                'indikator_sasaran' => $currentSasaranIndicator ?? '',
                                'target_satuan_intermediate' => $currentIntermediateTarget ?? '',
                            ]);
                            $intermediateMap[$intermediateKey] = $intermediate->id;
                            $totalIntermediate++;
                        }
                        $intermediateId = $intermediateMap[$intermediateKey];
                    }

                    // Create or get Immediate
                    if (!empty($currentImmediate) && isset($intermediateId)) {
                        $immediateKey = $intermediateId . '|' . $currentImmediate;
                        if (!isset($immediateMap[$immediateKey])) {
                            $immediate = Immediate::create([
                                'intermediate_id' => $intermediateId,
                                'immediate' => $currentImmediate,
                                'program_immediate' => $currentImmediateProgram ?? '',
                                'nomenklatur_sipd_immediate' => $currentImmediateSipd ?? '',
                                'indikator_immediate' => $currentImmediateIndicator ?? '',
                                'target_satuan_immediate' => $currentImmediateTarget ?? '',
                            ]);
                            $immediateMap[$immediateKey] = $immediate->id;
                            $totalImmediate++;
                        }
                        $immediateId = $immediateMap[$immediateKey];
                    }

                    // Create Output (always create new for each output)
                    if (!empty($output) && isset($immediateId)) {
                        Output::create([
                            'immediate_id' => $immediateId,
                            'output' => $output,
                            'kegiatan_output' => $kegiatanOutput,
                            'nomenklatur_sipd_output' => $outputSipd,
                            'indikator_output' => $indikatorOutput ?? '',
                            'target_satuan_output' => $targetOutput,
                            'input_output' => $inputOutput,
                            'sub_kegiatan_output' => $subKegiatanOutput,
                            'nomenklatur_sipd_sub_kegiatan_output' => $subKegiatanSipd,
                            'indikator_sub_kegiatan_output' => $subKegiatanIndicator,
                            'target_satuan_sub_kegiatan_output' => $subKegiatanTarget,
                            'anggaran' => $anggaranVal,
                        ]);
                        $totalOutput++;
                    }
                }

                return [
                    'pohon_kinerja_id' => $pohonKinerja->id,
                    'tahun' => $pohonKinerja->tahun,
                    'unit_kerja' => $pohonKinerja->unit_kerja,
                    'total_ultimate' => $totalUltimate,
                    'total_intermediate' => $totalIntermediate,
                    'total_immediate' => $totalImmediate,
                    'total_output' => $totalOutput,
                ];
            });

            return response()->json([
                'message' => 'Pohon kinerja berhasil diimport.',
                'data' => $result,
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengimport pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTree(Request $request)
    {
        try {
            $tahun = $request->query('tahun');
            $unitKerja = $request->query('unit_kerja');

            if (!$tahun) {
                return response()->json([
                    'message' => 'Parameter tahun wajib diisi.',
                ], 422);
            }

            // Get PohonKinerja data
            $pohonKinerja = PohonKinerja::where('tahun', $tahun);

            if ($unitKerja) {
                $pohonKinerja = $pohonKinerja->where('unit_kerja', $unitKerja);
            }

            $pohonKinerja = $pohonKinerja->first();

            if (!$pohonKinerja) {
                return response()->json([
                    'message' => 'Data pohon kinerja tidak ditemukan.',
                ], 404);
            }

            // Get all ultimates with their relationships
            $ultimates = Ultimate::where('pohon_kinerja_id', $pohonKinerja->id)
                ->with([
                    'intermediates.immediates.outputs'
                ])
                ->get();

            // Build tree structure
            $branches = $ultimates->map(function ($ultimate) {
                return [
                    'id' => 'ultimate-' . $ultimate->id,
                    'title' => $ultimate->ultimate,
                    'indicator' => $ultimate->indikator_ultimate,
                    'level' => 'ULTIMATE',
                    'children' => $ultimate->intermediates->map(function ($intermediate) {
                        return [
                            'id' => 'intermediate-' . $intermediate->id,
                            'title' => $intermediate->sasaran,
                            'indicator' => $intermediate->indikator_sasaran,
                            'level' => 'INTERMEDIATE',
                            'bidang' => $intermediate->bidang ?? 'komunikasi',
                            'children' => $intermediate->immediates->map(function ($immediate) {
                                return [
                                    'id' => 'immediate-' . $immediate->id,
                                    'title' => $immediate->immediate,
                                    'indicator' => $immediate->indikator_immediate,
                                    'level' => 'IMMEDIATE',
                                    'children' => $immediate->outputs->map(function ($output) {
                                         return [
                                             'id' => 'output-' . $output->id,
                                             'title' => $output->output,
                                             'indicator' => $output->indikator_output,
                                             'level' => 'OUTPUT',
                                             'anggaran' => $output->anggaran ?? 0,
                                         ];
                                    })->values()->toArray(),
                                ];
                            })->values()->toArray(),
                        ];
                    })->toArray(),
                ];
            })->toArray();

            // Get first ultimate for main tree
            $firstUltimate = $ultimates->first();

            if (!$firstUltimate) {
                return response()->json([
                    'message' => 'Pohon kinerja belum memiliki data ultimate.',
                ], 404);
            }

            $tree = [
                'id' => 'ultimate-' . $firstUltimate->id,
                'title' => $firstUltimate->ultimate,
                'indicator' => $firstUltimate->indikator_ultimate,
                'branches' => $branches[0]['children'] ?? [],
            ];

            return response()->json([
                'message' => 'Pohon kinerja berhasil diambil.',
                'data' => [
                    'pohon_kinerja_id' => $pohonKinerja->id,
                    'tahun' => $pohonKinerja->tahun,
                    'unit_kerja' => $pohonKinerja->unit_kerja,
                    'tree' => $tree,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateNode(Request $request, string $level, string $id)
    {
        if (!ctype_digit($id)) {
            return response()->json([
                'message' => 'ID node tidak valid.',
            ], 422);
        }

        $id = (int) $id;
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'indicator' => ['nullable', 'string'],
            'bidang' => ['nullable', 'string', 'in:komunikasi,statistik,persandian,aplikasi,kesekretariatan'],
        ]);

        $fields = match (strtoupper($level)) {
            'ULTIMATE' => ['ultimate' => $validated['title'], 'indikator_ultimate' => $validated['indicator'] ?? null],
            'INTERMEDIATE' => array_filter([
                'sasaran' => $validated['title'],
                'indikator_sasaran' => $validated['indicator'] ?? '',
                'bidang' => $validated['bidang'] ?? null,
            ], fn ($v) => !is_null($v)),
            'IMMEDIATE' => ['immediate' => $validated['title'], 'indikator_immediate' => $validated['indicator'] ?? null],
            'OUTPUT' => ['output' => $validated['title'], 'indikator_output' => $validated['indicator'] ?? null],
            default => null,
        };

        if (!$fields) {
            return response()->json(['message' => 'Level node tidak valid.'], 422);
        }

        $model = match (strtoupper($level)) {
            'ULTIMATE' => Ultimate::find($id),
            'INTERMEDIATE' => Intermediate::find($id),
            'IMMEDIATE' => Immediate::find($id),
            'OUTPUT' => Output::find($id),
        };

        if (!$model) {
            return response()->json(['message' => 'Node tidak ditemukan.'], 404);
        }

        $model->update($fields);

        return response()->json([
            'message' => 'Perubahan berhasil disimpan ke database.',
            'data' => $model->fresh(),
        ]);
    }

    public function deleteNode(string $level, string $id)
    {
        if (!ctype_digit($id)) {
            return response()->json([
                'message' => 'ID node tidak valid.',
            ], 422);
        }

        $id = (int) $id;
        $level = strtoupper($level);

        if ($level === 'ULTIMATE') {
            return response()->json([
                'message' => 'Node Ultimate tidak dapat dihapus melalui fitur ini.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($level, $id) {
                switch ($level) {
                    case 'INTERMEDIATE':
                        $intermediate = Intermediate::find($id);

                        if (!$intermediate) {
                            abort(404, 'Node Intermediate tidak ditemukan.');
                        }

                        // Hapus semua output yang berada di bawah immediate
                        // milik intermediate ini terlebih dahulu.
                        $immediates = Immediate::where('intermediate_id', $intermediate->id)->get();

                        foreach ($immediates as $immediate) {
                            Output::where('immediate_id', $immediate->id)->delete();
                        }

                        // Hapus semua immediate di bawah intermediate.
                        Immediate::where('intermediate_id', $intermediate->id)->delete();

                        // Terakhir hapus intermediate.
                        $intermediate->delete();
                        break;

                    case 'IMMEDIATE':
                        $immediate = Immediate::find($id);

                        if (!$immediate) {
                            abort(404, 'Node Immediate tidak ditemukan.');
                        }

                        // Hapus semua output yang berada di bawah immediate.
                        Output::where('immediate_id', $immediate->id)->delete();
                        $immediate->delete();
                        break;

                    case 'OUTPUT':
                        $output = Output::find($id);

                        if (!$output) {
                            abort(404, 'Node Output tidak ditemukan.');
                        }

                        $output->delete();
                        break;

                    default:
                        abort(422, 'Level node tidak valid.');
                }
            });

            return response()->json([
                'message' => 'Node berhasil dihapus dari database.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menghapus node.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    public function checkYearExists(int $tahun)
    {
        try {
            $exists = PohonKinerja::where('tahun', $tahun)->exists();

            return response()->json([
                'message' => 'Status tahun berhasil diambil.',
                'data' => [
                    'tahun' => $tahun,
                    'exists' => $exists,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengecek tahun.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function archive(string $id)
    {
        try {
            $pohonKinerja = PohonKinerja::find($id);

            if (!$pohonKinerja) {
                return response()->json([
                    'message' => 'Pohon kinerja tidak ditemukan.',
                ], 404);
            }

            if ($pohonKinerja->is_archived) {
                return response()->json([
                    'message' => 'Pohon kinerja sudah diarsipkan.',
                ], 422);
            }

            $pohonKinerja->archive();

            return response()->json([
                'message' => 'Pohon kinerja tahun ' . $pohonKinerja->tahun . ' berhasil diarsipkan.',
                'data' => [
                    'id' => $pohonKinerja->id,
                    'tahun' => $pohonKinerja->tahun,
                    'unit_kerja' => $pohonKinerja->unit_kerja,
                    'is_archived' => $pohonKinerja->is_archived,
                    'archived_at' => $pohonKinerja->archived_at,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengarsipkan pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $pohonKinerja = PohonKinerja::find($id);

            if (!$pohonKinerja) {
                return response()->json([
                    'message' => 'Pohon kinerja tidak ditemukan.',
                ], 404);
            }

            if (!$pohonKinerja->is_archived) {
                return response()->json([
                    'message' => 'Pohon kinerja bukan data arsipan.',
                ], 422);
            }

            $pohonKinerja->restore();

            return response()->json([
                'message' => 'Pohon kinerja tahun ' . $pohonKinerja->tahun . ' berhasil dipulihkan dari arsipan.',
                'data' => [
                    'id' => $pohonKinerja->id,
                    'tahun' => $pohonKinerja->tahun,
                    'unit_kerja' => $pohonKinerja->unit_kerja,
                    'is_archived' => $pohonKinerja->is_archived,
                    'archived_at' => $pohonKinerja->archived_at,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memulihkan pohon kinerja dari arsipan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getArchived()
    {
        try {
            $archivedData = PohonKinerja::archived()
                ->with('ultimates.intermediates.immediates.outputs')
                ->orderBy('archived_at', 'desc')
                ->get();

            if ($archivedData->isEmpty()) {
                return response()->json([
                    'message' => 'Tidak ada data pohon kinerja yang diarsipkan.',
                    'data' => [],
                ], 200);
            }

            $formattedData = $archivedData->map(function ($pk) {
                return [
                    'id' => $pk->id,
                    'tahun' => $pk->tahun,
                    'unit_kerja' => $pk->unit_kerja,
                    'is_archived' => $pk->is_archived,
                    'archived_at' => $pk->archived_at,
                    'total_ultimate' => $pk->ultimates->count(),
                    'total_intermediate' => $pk->ultimates->sum(function ($u) {
                        return $u->intermediates->count();
                    }),
                    'total_immediate' => $pk->ultimates->sum(function ($u) {
                        return $u->intermediates->sum(function ($i) {
                            return $i->immediates->count();
                        });
                    }),
                    'total_output' => $pk->ultimates->sum(function ($u) {
                        return $u->intermediates->sum(function ($i) {
                            return $i->immediates->sum(function ($im) {
                                return $im->outputs->count();
                            });
                        });
                    }),
                ];
            });

            return response()->json([
                'message' => 'Data arsipan pohon kinerja berhasil diambil.',
                'data' => $formattedData,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil data arsipan pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function duplicate(Request $request)
    {
        $request->validate([
            'tahun_baru' => ['required', 'integer', 'min:2000', 'max:2099'],
            'unit_kerja' => ['required', 'string', 'max:255'],
        ]);

        try {
            $tahunBaru = $request->input('tahun_baru');
            $unitKerja = $request->input('unit_kerja');
            $tahunLama = $tahunBaru - 1;

            // Check if new year already exists
            $existingPohon = PohonKinerja::where('tahun', $tahunBaru)->first();
            if ($existingPohon) {
                return response()->json([
                    'message' => 'Pohon kinerja tahun ' . $tahunBaru . ' sudah ada.',
                ], 422);
            }

            // Get source pohon kinerja from previous year
            $sourcePonoKinerja = PohonKinerja::where('tahun', $tahunLama)
                ->with('ultimates.intermediates.immediates.outputs')
                ->first();

            if (!$sourcePonoKinerja) {
                return response()->json([
                    'message' => 'Pohon kinerja tahun ' . $tahunLama . ' tidak ditemukan untuk dijadikan template.',
                ], 404);
            }

            $result = DB::transaction(function () use ($tahunBaru, $unitKerja, $sourcePonoKinerja) {
                // Create new pohon kinerja
                $newPohonKinerja = PohonKinerja::create([
                    'tahun' => $tahunBaru,
                    'unit_kerja' => $unitKerja,
                    'is_archived' => false,
                    'archived_at' => null,
                ]);

                $totalUltimate = 0;
                $totalIntermediate = 0;
                $totalImmediate = 0;
                $totalOutput = 0;

                // Map old to new IDs for relationships
                $ultimateMap = []; // oldId => newId
                $intermediateMap = [];
                $immediateMap = [];

                // Duplicate ultimates
                foreach ($sourcePonoKinerja->ultimates as $oldUltimate) {
                    $newUltimate = Ultimate::create([
                        'pohon_kinerja_id' => $newPohonKinerja->id,
                        'ultimate' => $oldUltimate->ultimate,
                        'tujuan_ultimate' => $oldUltimate->tujuan_ultimate,
                        'indikator_ultimate' => $oldUltimate->indikator_ultimate,
                        'target_satuan_ultimate' => $oldUltimate->target_satuan_ultimate,
                    ]);
                    $ultimateMap[$oldUltimate->id] = $newUltimate->id;
                    $totalUltimate++;

                    // Duplicate intermediates
                    foreach ($oldUltimate->intermediates as $oldIntermediate) {
                        $newIntermediate = Intermediate::create([
                            'ultimate_id' => $newUltimate->id,
                            'intermediate' => $oldIntermediate->intermediate,
                            'sasaran' => $oldIntermediate->sasaran,
                            'bidang' => $oldIntermediate->bidang ?? 'komunikasi',
                            'indikator_sasaran' => $oldIntermediate->indikator_sasaran,
                            'target_satuan_intermediate' => $oldIntermediate->target_satuan_intermediate,
                        ]);
                        $intermediateMap[$oldIntermediate->id] = $newIntermediate->id;
                        $totalIntermediate++;

                        // Duplicate immediates
                        foreach ($oldIntermediate->immediates as $oldImmediate) {
                            $newImmediate = Immediate::create([
                                'intermediate_id' => $newIntermediate->id,
                                'immediate' => $oldImmediate->immediate,
                                'program_immediate' => $oldImmediate->program_immediate,
                                'nomenklatur_sipd_immediate' => $oldImmediate->nomenklatur_sipd_immediate,
                                'indikator_immediate' => $oldImmediate->indikator_immediate,
                                'target_satuan_immediate' => $oldImmediate->target_satuan_immediate,
                            ]);
                            $immediateMap[$oldImmediate->id] = $newImmediate->id;
                            $totalImmediate++;

                            // Duplicate outputs
                            foreach ($oldImmediate->outputs as $oldOutput) {
                                Output::create([
                                    'immediate_id' => $newImmediate->id,
                                    'output' => $oldOutput->output,
                                    'kegiatan_output' => $oldOutput->kegiatan_output,
                                    'nomenklatur_sipd_output' => $oldOutput->nomenklatur_sipd_output,
                                    'indikator_output' => $oldOutput->indikator_output,
                                    'target_satuan_output' => $oldOutput->target_satuan_output,
                                    'input_output' => $oldOutput->input_output,
                                    'sub_kegiatan_output' => $oldOutput->sub_kegiatan_output,
                                    'nomenklatur_sipd_sub_kegiatan_output' => $oldOutput->nomenklatur_sipd_sub_kegiatan_output,
                                    'indikator_sub_kegiatan_output' => $oldOutput->indikator_sub_kegiatan_output,
                                    'target_satuan_sub_kegiatan_output' => $oldOutput->target_satuan_sub_kegiatan_output,
                                    'anggaran' => $oldOutput->anggaran ?? 0,
                                ]);
                                $totalOutput++;
                            }
                        }
                    }
                }

                return [
                    'pohon_kinerja_id' => $newPohonKinerja->id,
                    'tahun' => $newPohonKinerja->tahun,
                    'unit_kerja' => $newPohonKinerja->unit_kerja,
                    'total_ultimate' => $totalUltimate,
                    'total_intermediate' => $totalIntermediate,
                    'total_immediate' => $totalImmediate,
                    'total_output' => $totalOutput,
                ];
            });

            return response()->json([
                'message' => 'Pohon kinerja tahun ' . $tahunBaru . ' berhasil dibuat dari template tahun ' . $tahunLama . '.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menduplikasi pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createNode(Request $request)
    {
        $validated = $request->validate([
            'level' => ['required', 'string', 'in:ULTIMATE,INTERMEDIATE,IMMEDIATE,OUTPUT'],
            'title' => ['required', 'string'],
            'indicator' => ['nullable', 'string'],
            'bidang' => ['nullable', 'string', 'in:komunikasi,statistik,persandian,aplikasi,kesekretariatan'],

            // ID induk
            'pohon_kinerja_id' => ['nullable', 'integer', 'exists:pohon_kinerjas,id'],
            'parent_id' => ['nullable'],
        ]);

        try {
            $level = strtoupper($validated['level']);
            $title = trim($validated['title']);
            $indicator = $validated['indicator'] ?? null;
            $rawParentId = $validated['parent_id'] ?? null;
            $parentId = is_string($rawParentId) ? preg_replace('/^[a-z]+-/', '', $rawParentId) : $rawParentId;
            $parentId = is_numeric($parentId) ? (int) $parentId : null;

            /*
            * ULTIMATE
            * Tidak mempunyai parent.
            * Membutuhkan pohon_kinerja_id.
            */
            if ($level === 'ULTIMATE') {
                if (empty($validated['pohon_kinerja_id'])) {
                    return response()->json([
                        'message' => 'Pohon Kinerja ID wajib diisi untuk node Ultimate.'
                    ], 422);
                }

                $pohonKinerja = PohonKinerja::find(
                    $validated['pohon_kinerja_id']
                );

                if (!$pohonKinerja) {
                    return response()->json([
                        'message' => 'Pohon kinerja tidak ditemukan.'
                    ], 404);
                }

                if ($pohonKinerja->is_archived) {
                    return response()->json([
                        'message' => 'Pohon kinerja yang sudah diarsipkan tidak dapat diubah.'
                    ], 422);
                }

                $node = Ultimate::create([
                    'pohon_kinerja_id' => $pohonKinerja->id,
                    'ultimate' => $title,
                    'tujuan_ultimate' => '',
                    'indikator_ultimate' => $indicator,
                    'target_satuan_ultimate' => '',
                ]);
            }

            /*
            * INTERMEDIATE
            * Parent = Ultimate
            */
            elseif ($level === 'INTERMEDIATE') {
                $ultimate = null;
                if ($parentId) {
                    $ultimate = Ultimate::find($parentId);
                }
                if (!$ultimate && !empty($validated['pohon_kinerja_id'])) {
                    $ultimate = Ultimate::where('pohon_kinerja_id', $validated['pohon_kinerja_id'])->first();
                }
                if (!$ultimate && $parentId) {
                    $ultimate = Ultimate::where('pohon_kinerja_id', $parentId)->first();
                }

                if (!$ultimate) {
                    return response()->json([
                        'message' => 'Ultimate tidak ditemukan untuk node Intermediate.'
                    ], 404);
                }

                $pohonKinerja = PohonKinerja::find(
                    $ultimate->pohon_kinerja_id
                );

                if (!$pohonKinerja || $pohonKinerja->is_archived) {
                    return response()->json([
                        'message' => 'Pohon kinerja tidak tersedia untuk diubah.'
                    ], 422);
                }

                $bidang = $validated['bidang'] ?? 'komunikasi';
                if (!in_array($bidang, ['komunikasi', 'statistik', 'persandian', 'aplikasi', 'kesekretariatan'], true)) {
                    $bidang = 'komunikasi';
                }

                $node = Intermediate::create([
                    'ultimate_id' => $ultimate->id,
                    'intermediate' => '',
                    'sasaran' => $title,
                    'bidang' => $bidang,
                    'indikator_sasaran' => $indicator,
                    'target_satuan_intermediate' => '',
                ]);
            }

            /*
            * IMMEDIATE
            * Parent = Intermediate
            */
            elseif ($level === 'IMMEDIATE') {
                if (empty($parentId)) {
                    return response()->json([
                        'message' => 'Intermediate ID wajib diisi untuk node Immediate.'
                    ], 422);
                }

                $intermediate = Intermediate::find($parentId);

                if (!$intermediate) {
                    return response()->json([
                        'message' => 'Intermediate tidak ditemukan.'
                    ], 404);
                }

                $ultimate = Ultimate::find(
                    $intermediate->ultimate_id
                );

                $pohonKinerja = $ultimate
                    ? PohonKinerja::find($ultimate->pohon_kinerja_id)
                    : null;

                if (!$pohonKinerja || $pohonKinerja->is_archived) {
                    return response()->json([
                        'message' => 'Pohon kinerja tidak tersedia untuk diubah.'
                    ], 422);
                }

                $node = Immediate::create([
                    'intermediate_id' => $intermediate->id,
                    'immediate' => $title,
                    'program_immediate' => '',
                    'nomenklatur_sipd_immediate' => '',
                    'indikator_immediate' => $indicator,
                    'target_satuan_immediate' => '',
                ]);
            }

            /*
            * OUTPUT
            * Parent = Immediate
            */
            else {
                if (empty($parentId)) {
                    return response()->json([
                        'message' => 'Immediate ID wajib diisi untuk node Output.'
                    ], 422);
                }

                $immediate = Immediate::find($parentId);

                if (!$immediate) {
                    return response()->json([
                        'message' => 'Immediate tidak ditemukan.'
                    ], 404);
                }

                $intermediate = Intermediate::find(
                    $immediate->intermediate_id
                );

                $ultimate = $intermediate
                    ? Ultimate::find($intermediate->ultimate_id)
                    : null;

                $pohonKinerja = $ultimate
                    ? PohonKinerja::find($ultimate->pohon_kinerja_id)
                    : null;

                if (!$pohonKinerja || $pohonKinerja->is_archived) {
                    return response()->json([
                        'message' => 'Pohon kinerja tidak tersedia untuk diubah.'
                    ], 422);
                }

                $node = Output::create([
                    'immediate_id' => $immediate->id,
                    'output' => $title,
                    'kegiatan_output' => '',
                    'nomenklatur_sipd_output' => '',
                    'indikator_output' => $indicator,
                    'target_satuan_output' => '',
                    'input_output' => '',
                    'sub_kegiatan_output' => '',
                    'nomenklatur_sipd_sub_kegiatan_output' => '',
                    'indikator_sub_kegiatan_output' => '',
                    'target_satuan_sub_kegiatan_output' => '',
                    'anggaran' => 0,
                ]);
            }

            return response()->json([
                'message' => 'Node berhasil ditambahkan ke database.',
                'data' => $node->fresh(),
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal menambahkan node.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

public function summary(Request $request)
    {
        try {
            $tahun = $request->query('tahun', now()->year);
 
            $pohonKinerja = PohonKinerja::where('tahun', $tahun)->first();
 
            if (!$pohonKinerja) {
                return response()->json([
                    'message' => 'Data pohon kinerja untuk tahun tersebut belum tersedia.',
                    'data' => [
                        'tahun' => (int) $tahun,
                        'ultimate' => 0,
                        'intermediate' => 0,
                        'immediate' => 0,
                        'output' => 0,
                    ],
                ], 200);
            }
 
            $ultimateIds = Ultimate::where('pohon_kinerja_id', $pohonKinerja->id)->pluck('id');
            $intermediateIds = Intermediate::whereIn('ultimate_id', $ultimateIds)->pluck('id');
            $immediateIds = Immediate::whereIn('intermediate_id', $intermediateIds)->pluck('id');
            $totalOutput = Output::whereIn('immediate_id', $immediateIds)->count();
 
            return response()->json([
                'message' => 'Ringkasan pohon kinerja berhasil diambil.',
                'data' => [
                    'pohon_kinerja_id' => $pohonKinerja->id,
                    'tahun' => $pohonKinerja->tahun,
                    'unit_kerja' => $pohonKinerja->unit_kerja,
                    'ultimate' => $ultimateIds->count(),
                    'intermediate' => $intermediateIds->count(),
                    'immediate' => $immediateIds->count(),
                    'output' => $totalOutput,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil ringkasan pohon kinerja.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
 
    /**
     * Daftar node (Ultimate/Intermediate/Immediate/Output) yang
     * paling baru diperbarui, untuk pohon kinerja tahun tertentu.
     * Dipakai oleh dashboard sebagai "Aktivitas Terbaru".
     *
     * GET /api/pohon-kinerja/recent-activity?tahun=2026&limit=5
     */
    public function recentActivity(Request $request)
    {
        try {
            $tahun = $request->query('tahun', now()->year);
            $limit = (int) $request->query('limit', 5);
 
            $pohonKinerja = PohonKinerja::where('tahun', $tahun)->first();
 
            if (!$pohonKinerja) {
                return response()->json([
                    'message' => 'Data pohon kinerja untuk tahun tersebut belum tersedia.',
                    'data' => [],
                ], 200);
            }
 
            $ultimates = Ultimate::where('pohon_kinerja_id', $pohonKinerja->id)->get();
            $intermediates = Intermediate::whereIn('ultimate_id', $ultimates->pluck('id'))->get();
            $immediates = Immediate::whereIn('intermediate_id', $intermediates->pluck('id'))->get();
            $outputs = Output::whereIn('immediate_id', $immediates->pluck('id'))->get();
 
            $activities = collect()
                ->concat($ultimates->map(fn ($item) => [
                    'level' => 'ULTIMATE',
                    'title' => $item->ultimate,
                    'updated_at' => $item->updated_at,
                ]))
                ->concat($intermediates->map(fn ($item) => [
                    'level' => 'INTERMEDIATE',
                    'title' => $item->sasaran,
                    'updated_at' => $item->updated_at,
                ]))
                ->concat($immediates->map(fn ($item) => [
                    'level' => 'IMMEDIATE',
                    'title' => $item->immediate,
                    'updated_at' => $item->updated_at,
                ]))
                ->concat($outputs->map(fn ($item) => [
                    'level' => 'OUTPUT',
                    'title' => $item->output,
                    'updated_at' => $item->updated_at,
                ]))
                ->sortByDesc('updated_at')
                ->take($limit)
                ->values();
 
            return response()->json([
                'message' => 'Aktivitas terbaru berhasil diambil.',
                'data' => $activities,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil aktivitas terbaru.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}