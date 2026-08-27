<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PohonKinerja extends Model
{
    protected $fillable = [
        'tahun',
    ];

    public function ultimates(): HasMany
    {
        return $this->hasMany(Ultimate::class);
    }
}
