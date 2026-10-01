<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealisasiAnggaran extends Model
{
    protected $fillable = [
        'output_id',
        'periode',
        'tahun',
        'realisasi',
        'keterangan',
    ];

    protected $casts = [
        'periode' => 'integer',
        'tahun' => 'integer',
        'realisasi' => 'decimal:2',
    ];

    /**
     * Get the output (sub kegiatan) that this realisasi anggaran belongs to.
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(Output::class);
    }
}
