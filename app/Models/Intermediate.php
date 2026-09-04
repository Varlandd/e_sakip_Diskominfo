<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intermediate extends Model
{
    protected $fillable = [
        'ultimate_id',
        'intermediate',
        'sasaran',
        'indikator_sasaran',
        'target_satuan_intermediate',
    ];

    public function ultimate(): BelongsTo
    {
        return $this->belongsTo(Ultimate::class);
    }

    public function immediates(): HasMany
    {
        return $this->hasMany(Immediate::class);
    }

    public function capaians(): HasMany
    {
        return $this->hasMany(Capaian::class);
    }
}
