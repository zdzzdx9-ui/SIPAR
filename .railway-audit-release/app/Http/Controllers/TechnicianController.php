<?php

namespace App\Http\Controllers;

use App\Models\DetailPenyerahan;
use App\Models\ItemUnit;
use App\Models\Peminjaman;
use App\Services\PhotoCompressor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function storeHandover(Request $request, Peminjaman $peminjaman, PhotoCompressor $photoCompressor): RedirectResponse
    {
        if (! $request->has('items') && $request->filled('id_detail')) {
            $request->merge(['items' => [[
                'id_detail' => $request->input('id_detail'),
                'jumlah' => $request->input('jumlah'),
                'asset_ids' => $request->input('asset_ids'),
                'kondisi_alat' => $request->input('kondisi_alat'),
                'catatan' => $request->input('catatan'),
            ]]]);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_detail' => ['required', 'integer', 'distinct', 'exists:loan_items,id_detail'],
            'items.*.jumlah' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.asset_ids' => ['required', 'string', 'max:8000'],
            'items.*.kondisi_alat' => ['required', 'in:Baik,Rusak'],
            'items.*.catatan' => ['nullable', 'string', 'max:2000'],
            'foto_bukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $data['foto_bukti'] = $photoCompressor->store($request->file('foto_bukti'), 'bukti/penyerahan', 'foto_bukti');

        $keepPhoto = false;
        try {
            DB::transaction(function () use ($request, $peminjaman, $data): void {
                $loan = Peminjaman::whereKey($peminjaman->id_peminjaman)->lockForUpdate()->firstOrFail();
                if ($loan->status !== 'Approved') {
                    throw ValidationException::withMessages(['items' => 'Hanya transaksi yang sudah disetujui yang dapat diserahkan.']);
                }
                $details = $loan->detail()->with('alat.itemUnits')->orderBy('id_detail')->lockForUpdate()->get();
                $pendingDetails = $details->filter(
                    fn ($detail) => $detail->penyerahanDetail()->sum('jumlah') < $detail->jumlah
                )->values();
                $submittedDetailIds = collect($data['items'])->pluck('id_detail')->map(fn ($id) => (int) $id)->sort()->values();
                $pendingDetailIds = $pendingDetails->pluck('id_detail')->map(fn ($id) => (int) $id)->sort()->values();

                if ($pendingDetails->isEmpty() || $submittedDetailIds->all() !== $pendingDetailIds->all()) {
                    throw ValidationException::withMessages([
                        'items' => 'Semua alat yang belum diserahkan dalam transaksi ini harus dicatat bersama.',
                    ]);
                }

                $submittedItems = collect($data['items'])->keyBy(fn ($item) => (int) $item['id_detail']);
                $assetIds = collect();
                foreach ($pendingDetails as $detail) {
                    $item = $submittedItems->get($detail->id_detail);
                    $item['jumlah'] = (int) $item['jumlah'];
                    $remaining = $detail->jumlah - $detail->penyerahanDetail()->sum('jumlah');
                    $itemAssetIds = collect(preg_split('/[\r\n,]+/', $item['asset_ids']))
                        ->map(fn ($assetId) => trim($assetId))
                        ->filter()
                        ->values();

                    if ($item['jumlah'] !== $remaining) {
                        throw ValidationException::withMessages([
                            'items' => "Semua unit {$detail->nama_alat} yang tersisa harus dicatat dalam transaksi yang sama.",
                        ]);
                    }

                    if ($itemAssetIds->count() !== $item['jumlah']) {
                        throw ValidationException::withMessages([
                            "items.{$detail->id_detail}.asset_ids" => 'Masukkan satu Asset ID yang berbeda untuk setiap unit.',
                        ]);
                    }
                    if ($itemAssetIds->unique()->count() !== $itemAssetIds->count()) {
                        throw ValidationException::withMessages([
                            "items.{$detail->id_detail}.asset_ids" => 'Asset ID pada alat ini tidak boleh duplikat.',
                        ]);
                    }

                    $alreadyHandedAssets = $detail->penyerahanDetail()->whereNotNull('asset_id')->pluck('asset_id');
                    if ($itemAssetIds->intersect($alreadyHandedAssets)->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            "items.{$detail->id_detail}.asset_ids" => 'Asset ID tersebut sudah tercatat pada penyerahan ini.',
                        ]);
                    }

                    $assetIds = $assetIds->concat($itemAssetIds);
                }

                if ($assetIds->unique()->count() !== $assetIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Asset ID harus berbeda untuk setiap unit dalam transaksi.',
                    ]);
                }

                foreach ($pendingDetails as $detail) {
                    $item = $submittedItems->get($detail->id_detail);
                    $itemAssetIds = collect(preg_split('/[\r\n,]+/', $item['asset_ids']))
                        ->map(fn ($assetId) => trim($assetId))
                        ->filter()
                        ->values();

                    foreach ($itemAssetIds as $assetId) {
                        $hasTrackedUnits = $detail->alat->itemUnits()->exists();
                        $itemUnit = null;
                        $assetAlreadyInUse = DetailPenyerahan::where('asset_id', $assetId)
                            ->where('id_detail', '!=', $detail->id_detail)
                            ->whereHas('detail.peminjaman', fn ($query) => $query->where('status', 'Approved'))
                            ->exists();
                        if ($assetAlreadyInUse) {
                            throw ValidationException::withMessages([
                                "items.{$detail->id_detail}.asset_ids" => "Asset ID {$assetId} sedang digunakan pada transaksi lain.",
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
                                    "items.{$detail->id_detail}.asset_ids" => "Asset ID {$assetId} tidak tersedia atau bukan unit dengan kondisi Baik.",
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
                            'kondisi_alat' => $item['kondisi_alat'],
                            'foto_bukti' => $data['foto_bukti'],
                            'catatan' => $item['catatan'] ?? null,
                        ]);
                    }

                    $detail->update([
                        'asset_ids' => $detail->penyerahanDetail()->whereNotNull('asset_id')->pluck('asset_id')->all(),
                    ]);
                }
            });
            $keepPhoto = true;
        } finally {
            if (! $keepPhoto) {
                Storage::disk('public')->delete($data['foto_bukti']);
            }
        }

        return back()->with('success', 'Semua alat dalam transaksi berhasil dicatat untuk penyerahan.');
    }
}
