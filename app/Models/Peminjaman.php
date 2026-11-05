<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Peminjaman extends Model
{
    protected $table = 'loans';

    protected $primaryKey = 'id_peminjaman';

    protected $fillable = [
        'id_user', 'user_id', 'tanggal_pengajuan', 'tanggal_pinjam', 'tanggal_kembali_rencana',
        'start_date', 'end_date', 'keperluan', 'status', 'catatan_admin', 'booking_code',
        'operational_notes', 'overdue_flag', 'reminder_flag',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_pinjam' => 'date',
        'tanggal_kembali_rencana' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'overdue_flag' => 'boolean',
        'reminder_flag' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $loan): void {
            $loan->id_user = $loan->isDirty('user_id') && ! $loan->isDirty('id_user')
                ? $loan->user_id
                : $loan->id_user;
            $loan->user_id = $loan->id_user;
            $loan->tanggal_pinjam = $loan->isDirty('start_date') && ! $loan->isDirty('tanggal_pinjam')
                ? $loan->start_date
                : $loan->tanggal_pinjam;
            $loan->start_date = $loan->tanggal_pinjam;
            $loan->tanggal_kembali_rencana = $loan->isDirty('end_date') && ! $loan->isDirty('tanggal_kembali_rencana')
                ? $loan->end_date
                : $loan->tanggal_kembali_rencana;
            $loan->end_date = $loan->tanggal_kembali_rencana;
        });

        static::created(function (self $loan): void {
            if (! $loan->booking_code) {
                $loan->forceFill([
                    'booking_code' => 'PJM-'.str_pad((string) $loan->id_peminjaman, 8, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'Pending' => 'Menunggu Konfirmasi',
            'Approved' => 'Disetujui',
            'Rejected' => 'Ditolak',
            'Cancelled' => 'Dibatalkan',
            'Selesai' => 'Selesai',
        ][$this->status] ?? ($this->status ?? '-');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPeminjaman::class, 'id_peminjaman', 'id_peminjaman');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'Approved'
            && $this->tanggal_kembali_rencana !== null
            && $this->tanggal_kembali_rencana->lt(today());
    }

    public function pengembalian(): HasOne
    {
        return $this->hasOne(Pengembalian::class, 'id_peminjaman', 'id_peminjaman');
    }

    public function detailPengembalian(): HasManyThrough
    {
        return $this->hasManyThrough(
            DetailPengembalian::class,
            DetailPeminjaman::class,
            'id_peminjaman',
            'id_detail',
            'id_peminjaman',
            'id_detail'
        );
    }
}
