<?php

namespace App\Http\Controllers;

use App\Models\DetailPenyerahan;
use App\Models\ItemUnit;
use App\Models\Peminjaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    public function handovers(Request $request): View
    {
        $term = $request->string('q')->trim()->toString();
        Peminjaman::where('status', 'Approved')
            ->whereDate('tanggal_kembali_rencana', '<', today())
            ->update(['overdue_flag' => true, 'reminder_flag' => true]);

        $loans = Peminjaman::with(['user', 'detail.alat.itemUnits', 'detail.itemUnits', 'detail.penyerahanDetail'])
            ->where('status', 'Approved')
            ->when($term !== '', fn ($query) => $query->where(function ($builder) use ($term): void {
                $builder->where('booking_code', $term)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('nis_nip', $term));
            }))
            ->latest('tanggal_pengajuan')
            ->paginate(12);

        return view('technician.handovers', compact('loans'));
    }

    public function storeHandover(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        $data = $request->validate([
            'id_detail' => ['required', 'integer', 'exists:loan_items,id_detail'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:10000'],
            'asset_ids' => ['required', 'string', 'max:8000'],
            'kondisi_alat' => ['required', 'in:Baik,Rusak'],
            'foto_bukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['jumlah'] = (int) $data['jumlah'];
        $assetIds = collect(preg_split('/[\r\n,]+/', $data['asset_ids']))
            ->map(fn ($assetId) => trim($assetId))
            ->filter()
            ->values();
        if ($assetIds->count() !== $data['jumlah'] || $assetIds->unique()->count() !== $assetIds->count()) {
            throw ValidationException::withMessages([
                'asset_ids' => 'Masukkan satu Asset ID yang berbeda untuk setiap unit yang diserahkan.',
            ]);
        }
        if ($request->hasFile('foto_bukti')) {
            $data['foto_bukti'] = $request->file('foto_bukti')->store('bukti/penyerahan', 'public');
        }

        DB::transaction(function () use ($request, $peminjaman, $data, $assetIds): void {
            $loan = Peminjaman::whereKey($peminjaman->id_peminjaman)->lockForUpdate()->firstOrFail();
            if ($loan->status !== 'Approved') {
                throw ValidationException::withMessages(['id_detail' => 'Hanya transaksi yang sudah disetujui yang dapat diserahkan.']);
            }
            $detail = $loan->detail()->whereKey($data['id_detail'])->lockForUpdate()->firstOrFail();
            $handedOver = $detail->penyerahanDetail()->sum('jumlah');
            if ($handedOver + $data['jumlah'] > $detail->jumlah) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah penyerahan melebihi jumlah pengajuan.']);
            }

            $alreadyHandedAssets = $detail->penyerahanDetail()->whereNotNull('asset_id')->pluck('asset_id');
            if ($assetIds->intersect($alreadyHandedAssets)->isNotEmpty()) {
                throw ValidationException::withMessages(['asset_ids' => 'Asset ID tersebut sudah tercatat pada penyerahan ini.']);
            }

            foreach ($assetIds as $assetId) {
                $hasTrackedUnits = $detail->alat->itemUnits()->exists();
                $itemUnit = null;
                $assetAlreadyInUse = DetailPenyerahan::where('asset_id', $assetId)
                    ->where('id_detail', '!=', $detail->id_detail)
                    ->whereHas('detail.peminjaman', fn ($query) => $query->where('status', 'Approved'))
                    ->exists();
                if ($assetAlreadyInUse) {
                    throw ValidationException::withMessages([
                        'asset_ids' => "Asset ID {$assetId} sedang digunakan pada transaksi lain.",
                    ]);
                }
                if ($hasTrackedUnits) {
                    $itemUnit = ItemUnit::where('alat_id', $detail->id_alat)
                        ->where('asset_id', $assetId)
                        ->where('condition', 'Baik')
                        ->lockForUpdate()
                        ->first();
                    if ($itemUnit?->status === 'Tersedia') {
                        $itemUnit->update(['status' => 'Dipinjam']);
                    } elseif (! $itemUnit || $itemUnit->status !== 'Dipinjam'
                        || ! $itemUnit->details()->where('loan_items.id_detail', $detail->id_detail)->exists()) {
                        throw ValidationException::withMessages([
                            'asset_ids' => "Asset ID {$assetId} tidak tersedia atau bukan unit dengan kondisi Baik.",
                        ]);
                    }
                    if (! $detail->itemUnits()->where('item_units.item_unit_id', $itemUnit->item_unit_id)->exists()) {
                        $detail->itemUnits()->attach($itemUnit->item_unit_id, [
                            'assigned_by' => $request->user()->id_user,
                        ]);
                    }
                }

                DetailPenyerahan::create([
                    'id_detail' => $detail->id_detail,
                    'item_unit_id' => $itemUnit?->item_unit_id,
                    'asset_id' => $assetId,
                    'id_teknisi' => $request->user()->id_user,
                    'jumlah' => 1,
                    'tanggal_penyerahan' => now(),
                    'kondisi_alat' => $data['kondisi_alat'],
                    'foto_bukti' => $data['foto_bukti'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                ]);
            }

            $detail->update([
                'asset_ids' => $detail->penyerahanDetail()->whereNotNull('asset_id')->pluck('asset_id')->all(),
            ]);
        });

        return back()->with('success', 'Penyerahan alat berhasil dicatat.');
    }
}
