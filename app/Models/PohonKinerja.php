<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PohonKinerja extends Model
{
    protected $fillable = [
        'tahun',
        'unit_kerja',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function ultimates(): HasMany
    {
        return $this->hasMany(Ultimate::class);
    }

    // Scope untuk data yang tidak diarsipkan (aktif)
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    // Scope untuk data yang sudah diarsipkan
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    // Archive pohon kinerja
    public function archive()
    {
        $this->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }

    // Restore pohon kinerja dari arsipan
    public function restore()
    {
        $this->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);
    }
}
