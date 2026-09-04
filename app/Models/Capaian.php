<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capaian extends Model
{
    protected $fillable = [
        'intermediate_id',
        'bulan',
        'tahun',
        'target',
        'realisasi',
        'satuan',
        'keterangan',
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'target' => 'decimal:2',
        'realisasi' => 'decimal:2',
    ];

    /**
     * Get the intermediate that this capaian belongs to.
     */
    public function intermediate()
    {
        return $this->belongsTo(Intermediate::class);
    }

    /**
     * Calculate the percentage of achievement.
     */
    public function getPersentaseAttribute(): float
    {
        if ($this->target <= 0) {
            return 0;
        }

        return round(($this->realisasi / $this->target) * 100, 2);
    }
}
