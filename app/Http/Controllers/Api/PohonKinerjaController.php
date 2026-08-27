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

    public function updateNode(Request $request, string $level, int $id)
    {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'indicator' => ['nullable', 'string'],
        ]);

        $fields = match (strtoupper($level)) {
            'ULTIMATE' => ['ultimate' => $validated['title'], 'indikator_ultimate' => $validated['indicator'] ?? null],
            'INTERMEDIATE' => ['sasaran' => $validated['title'], 'indikator_sasaran' => $validated['indicator'] ?? null],
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
}