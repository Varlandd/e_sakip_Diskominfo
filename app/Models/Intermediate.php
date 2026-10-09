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
        'bidang',
        'indikator_sasaran',
        'target_satuan_intermediate',
        'indikator',
        'target',
        'satuan',
    ];

    protected $casts = [
        'indikator' => 'array',
        'target' => 'array',
        'satuan' => 'array',
    ];

    /**
     * Get the indicators as an array.
     */
    public function getIndikatorAttribute($value): array
    {
        if (!is_null($value)) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (!empty($this->attributes['indikator_sasaran'])) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $this->attributes['indikator_sasaran']))));
            return !empty($lines) ? $lines : [$this->attributes['indikator_sasaran']];
        }
        return [];
    }

    /**
     * Set the indicators. Accepts array or string.
     */
    public function setIndikatorAttribute($value): void
    {
        if (is_null($value)) {
            $this->attributes['indikator'] = json_encode([]);
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_values(array_filter(array_map('trim', explode("\n", $value))));
            }
        }

        $arrayValue = is_array($value)
            ? array_values(array_filter($value, fn ($v) => !is_null($v) && trim((string) $v) !== ''))
            : [];

        $this->attributes['indikator'] = json_encode($arrayValue);
        // Automatically sync to indikator_sasaran for backward compatibility
        $this->attributes['indikator_sasaran'] = implode("\n", $arrayValue);
    }

    /**
     * Get the target as an array.
     */
    public function getTargetAttribute($value): array
    {
        if (!is_null($value)) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /**
     * Set the target. Accepts array or string.
     */
    public function setTargetAttribute($value): void
    {
        if (is_null($value)) {
            $this->attributes['target'] = json_encode([]);
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_values(array_filter(array_map('trim', explode("\n", $value))));
            }
        }

        $arrayValue = is_array($value) ? array_values($value) : [];
        $this->attributes['target'] = json_encode($arrayValue);
    }

    /**
     * Get the satuan as an array.
     */
    public function getSatuanAttribute($value): array
    {
        if (!is_null($value)) {
            $decoded = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    /**
     * Set the satuan. Accepts array or string.
     */
    public function setSatuanAttribute($value): void
    {
        if (is_null($value)) {
            $this->attributes['satuan'] = json_encode([]);
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_values(array_filter(array_map('trim', explode("\n", $value))));
            }
        }

        $arrayValue = is_array($value) ? array_values($value) : [];
        $this->attributes['satuan'] = json_encode($arrayValue);
    }

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
