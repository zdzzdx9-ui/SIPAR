<?php

namespace App\Services;

use App\Models\Alat;
use App\Models\Peminjaman;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpireUncollectedLoans
{
    public function run(): int
    {
        $loanIds = Peminjaman::query()
            ->where('status', 'Approved')
            ->where('approved_at', '<=', now()->subHours(24))
            ->whereDoesntHave('detail.penyerahanDetail')
            ->orderBy('id_peminjaman')
            ->pluck('id_peminjaman');
        $expiredCount = 0;

        foreach ($loanIds as $loanId) {
            $expired = DB::transaction(function () use ($loanId): bool {
                $loan = Peminjaman::whereKey($loanId)->lockForUpdate()->first();
                if (! $loan
                    || $loan->status !== 'Approved'
                    || ! $loan->approved_at
                    || $loan->approved_at->gt(now()->subHours(24))) {
                    return false;
                }

                $details = $loan->detail()->orderBy('id_alat')->lockForUpdate()->get();
                if ($details->isEmpty() || $details->contains(fn ($detail) => $detail->penyerahanDetail()->exists())) {
                    return false;
                }

                $equipment = Alat::whereIn('id_alat', $details->pluck('id_alat'))
                    ->orderBy('id_alat')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_alat');

                foreach ($details as $detail) {
                    $item = $equipment->get($detail->id_alat);
                    if (! $item || $item->stok_tersedia + $detail->jumlah > $item->stok_total) {
                        throw ValidationException::withMessages([
                            'status' => "Stok {$detail->nama_alat} tidak konsisten; transaksi tidak diubah.",
                        ]);
                    }
                }

                foreach ($details as $detail) {
                    $item = $equipment->get($detail->id_alat);
                    $item->stok_tersedia += $detail->jumlah;
                    $item->syncStatusFromStock();
                    $item->save();
                }

                $loan->update([
                    'status' => 'Expired',
                    'operational_notes' => 'Transaksi kedaluwarsa otomatis setelah 24 jam sejak persetujuan karena belum diambil. Stok telah dikembalikan.',
                    'status_read_at' => null,
                    'overdue_flag' => false,
                    'reminder_flag' => false,
                ]);

                return true;
            });

            $expiredCount += (int) $expired;
        }

        return $expiredCount;
    }
}
