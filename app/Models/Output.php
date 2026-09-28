<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Output extends Model
{
    protected $fillable = [
        'immediate_id',
        'output',
        'kegiatan_output',
        'nomenklatur_sipd_output',
        'indikator_output',
        'target_satuan_output',
        'input_output',
        'sub_kegiatan_output',
        'nomenklatur_sipd_sub_kegiatan_output',
        'indikator_sub_kegiatan_output',
        'target_satuan_sub_kegiatan_output',
        'anggaran',
    ];

    public function immediate(): BelongsTo
    {
        return $this->belongsTo(Immediate::class);
    }
}
