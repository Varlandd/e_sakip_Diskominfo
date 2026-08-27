<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ultimate extends Model
{
    protected $fillable = [
        'pohon_kinerja_id',
        'ultimate',
        'tujuan_ultimate',
        'indikator_ultimate',
        'target_satuan_ultimate',
    ];

    public function pohonKinerja(): BelongsTo
    {
        return $this->belongsTo(PohonKinerja::class);
    }

    public function intermediates(): HasMany
    {
        return $this->hasMany(Intermediate::class);
    }
}
