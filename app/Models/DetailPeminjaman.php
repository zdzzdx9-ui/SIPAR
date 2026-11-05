<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DetailPeminjaman extends Model
{
    protected $table = 'loan_items';

    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_peminjaman', 'id_alat', 'jumlah', 'asset_ids', 'item_conditions',
        'alat_snapshot_kode', 'alat_snapshot_nama', 'alat_snapshot_kategori',
    ];

    protected $casts = ['jumlah' => 'integer', 'asset_ids' => 'array', 'item_conditions' => 'array'];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class, 'id_peminjaman', 'id_peminjaman');
    }

    public function alat(): BelongsTo
    {
        return $this->belongsTo(Alat::class, 'id_alat', 'id_alat');
    }

    public function getNamaAlatAttribute(): string
    {
        return $this->alat?->nama_alat ?? $this->alat_snapshot_nama ?? 'Alat arsip';
    }

    public function getKodeAlatAttribute(): ?string
    {
        return $this->alat?->kode_alat ?? $this->alat_snapshot_kode;
    }

    public function getKategoriAlatAttribute(): ?string
    {
        return $this->alat?->kategori?->nama_kategori ?? $this->alat_snapshot_kategori;
    }

    public function pengembalianDetail(): HasMany
    {
        return $this->hasMany(DetailPengembalian::class, 'id_detail', 'id_detail');
    }

    public function penyerahanDetail(): HasMany
    {
        return $this->hasMany(DetailPenyerahan::class, 'id_detail', 'id_detail');
    }

    public function itemUnits(): BelongsToMany
    {
        return $this->belongsToMany(ItemUnit::class, 'detail_peminjaman_item_unit', 'id_detail', 'item_unit_id')
            ->withPivot(['assigned_by'])
            ->withTimestamps();
    }
}
