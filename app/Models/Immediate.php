<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Immediate extends Model
{
    protected $fillable = [
        'intermediate_id',
        'immediate',
        'program_immediate',
        'nomenklatur_sipd_immediate',
        'indikator_immediate',
        'target_satuan_immediate',
    ];

    public function intermediate(): BelongsTo
    {
        return $this->belongsTo(Intermediate::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(Output::class);
    }
}
