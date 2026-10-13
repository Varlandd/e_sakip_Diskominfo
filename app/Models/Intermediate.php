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
     * Jaga supaya target[] & satuan[] SELALU sejajar dengan indikator[].
     *
     * Masalah yang diselesaikan: kalau indikator dihapus/diubah dari Pohon
     * Kinerja, yang tersimpan cuma array `indikator` yang baru. Array `target`
     * dan `satuan` tidak ikut berubah, sehingga pasangan per urutan bergeser
     * (target indikator 1 yang sudah dihapus malah nempel ke indikator 2).
     *
     * Hook ini hanya jalan saat `indikator` berubah DAN `target`/`satuan`
     * tidak ikut diubah secara eksplisit. Jadi simpan dari Renstra (yang
     * mengirim ketiganya sekaligus) tidak terpengaruh.
     */
    protected static function booted(): void
    {
        static::saving(function (Intermediate $intermediate) {
            if (!$intermediate->exists) {
                return;
            }

            if (!$intermediate->isDirty('indikator')) {
                return;
            }

            if ($intermediate->isDirty('target') || $intermediate->isDirty('satuan')) {
                return;
            }

            $decode = static function ($raw): array {
                if (is_array($raw)) {
                    return $raw;
                }
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                return is_array($decoded) ? $decoded : [];
            };

            $oldIndikator = $decode($intermediate->getRawOriginal('indikator'));
            $oldTarget = $decode($intermediate->getRawOriginal('target'));
            $oldSatuan = $decode($intermediate->getRawOriginal('satuan'));
            $newIndikator = $decode($intermediate->getAttributes()['indikator'] ?? null);

            // Tidak ada indikator lama sebagai acuan: tidak ada yang bisa disejajarkan.
            if (empty($oldIndikator)) {
                return;
            }

            $mapping = static::matchIndicators($oldIndikator, $newIndikator);

            $newTarget = [];
            $newSatuan = [];
            foreach ($mapping as $newIndex => $oldIndex) {
                $newTarget[$newIndex] = $oldIndex !== null ? (string) ($oldTarget[$oldIndex] ?? '') : '';
                $newSatuan[$newIndex] = $oldIndex !== null ? (string) ($oldSatuan[$oldIndex] ?? '') : '';
            }

            $intermediate->target = array_values($newTarget);
            $intermediate->satuan = array_values($newSatuan);

            // Kolom lama dipakai CapaianController dan mengikuti baris PERTAMA.
            // Hanya disentuh kalau indikator pertama berganti (misalnya yang
            // pertama dihapus), supaya format aslinya tidak diubah-ubah.
            if (($mapping[0] ?? null) !== 0) {
                $intermediate->target_satuan_intermediate = trim(
                    ($newTarget[0] ?? '') . ' ' . ($newSatuan[0] ?? '')
                );
            }
        });
    }

    /**
     * Cocokkan tiap indikator baru ke indikator lama.
     * Hasil: [indexBaru => indexLama|null]. null = indikator baru (belum punya target).
     *
     * 1) Cocok berdasarkan teks persis (menangani hapus & ganti urutan).
     * 2) Sisanya: kalau teks diubah di posisi yang sama, anggap itu hasil edit
     *    dan pakai target milik posisi itu.
     *
     * Batasan: kalau satu indikator dihapus DAN indikator lain diganti
     * namanya dalam satu kali simpan, tidak ada cara tahu mana yang mana,
     * dan targetnya bisa meleset. Hapus dan ganti nama sebaiknya terpisah.
     */
    public static function matchIndicators(array $old, array $new): array
    {
        $used = [];
        $mapping = [];

        foreach (array_values($new) as $i => $text) {
            $mapping[$i] = null;

            foreach (array_values($old) as $j => $oldText) {
                if (!isset($used[$j]) && trim((string) $oldText) === trim((string) $text)) {
                    $mapping[$i] = $j;
                    $used[$j] = true;
                    break;
                }
            }
        }

        foreach ($mapping as $i => $j) {
            if ($j === null && array_key_exists($i, $old) && !isset($used[$i])) {
                $mapping[$i] = $i;
                $used[$i] = true;
            }
        }

        return $mapping;
    }

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