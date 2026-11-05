<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\DetailPengembalian;
use App\Models\ItemUnit;
use App\Models\Peminjaman;
use App\Models\Pengembalian;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $term = $request->string('q')->trim()->toString();
        Peminjaman::where('status', 'Approved')
            ->whereDate('tanggal_kembali_rencana', '<', today())
            ->update(['overdue_flag' => true, 'reminder_flag' => true]);
        $activeLoans = Peminjaman::with([
            'user',
            'detail.alat',
            'detail.pengembalianDetail',
            'detail.penyerahanDetail',
            'detail.itemUnits',
        ])->where('status', 'Approved')
            ->when($term !== '', fn ($query) => $query->where(function ($builder) use ($term): void {
                $builder->where('booking_code', $term)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('nis_nip', $term));
            }))
            ->latest('tanggal_pengajuan')->paginate(12)->withQueryString();

        return view('technician.returns', compact('activeLoans'));
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        if ($term === '') {
            return response()->json(['message' => 'Masukkan Kode Booking atau NIS siswa.'], 422);
        }
        $loans = Peminjaman::with(['user', 'detail.alat'])
            ->where('status', 'Approved')
            ->where(function ($query) use ($term): void {
                $query->where('booking_code', $term)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('nis_nip', $term));
            })
            ->get();
        if ($loans->isEmpty()) {
            return response()->json(['message' => 'Transaksi aktif tidak ditemukan. Periksa Kode Booking atau NIS siswa.'], 404);
        }

        return response()->json($loans->map(fn (Peminjaman $loan) => [
            'booking_code' => $loan->booking_code,
            'peminjam' => $loan->user->nama,
            'nis' => $loan->user->nis_nip,
            'items' => $loan->detail->map(fn ($detail) => [
                'nama' => $detail->alat->nama_alat,
                'kode' => $detail->alat->kode_alat,
                'jumlah' => $detail->jumlah,
            ]),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_peminjaman' => ['required', 'integer', 'exists:loans,id_peminjaman'],
            'booking_code' => ['nullable', 'string', 'max:32'],
            'id_detail' => ['nullable', 'integer', 'exists:loan_items,id_detail'],
            'item_unit_id' => ['nullable', 'integer', 'exists:item_units,item_unit_id'],
            'asset_id' => ['nullable', 'string', 'max:80'],
            'idempotency_key' => ['required', 'uuid'],
            'jumlah' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'kode_alat' => ['required', 'string', 'exists:alat,kode_alat'],
            'tanggal_kembali_aktual' => ['nullable', 'date'],
            'kondisi_alat' => ['required', 'in:Baik,Maintenance,Rusak,Hilang'],
            'foto_bukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['jumlah'])) {
            $data['jumlah'] = (int) $data['jumlah'];
        }
        if ($request->hasFile('foto_bukti')) {
            $data['foto_bukti'] = $request->file('foto_bukti')->store('bukti/pengembalian', 'public');
        }
        $data['id_teknisi'] = $request->user()->id_user;
        $equipment = Alat::where('kode_alat', $data['kode_alat'])->firstOrFail();

        $fine = 0;
        $completed = false;
        $duplicate = false;
        try {
            DB::transaction(function () use ($data, &$fine, &$completed, &$duplicate) {
                if (DetailPengembalian::where('idempotency_key', $data['idempotency_key'])->exists()) {
                    $duplicate = true;

                    return;
                }

                $loan = Peminjaman::whereKey($data['id_peminjaman'])->lockForUpdate()->firstOrFail();
                if (DetailPengembalian::where('idempotency_key', $data['idempotency_key'])->exists()) {
                    $duplicate = true;

                    return;
                }
                if ($loan->status !== 'Approved' || $loan->pengembalian()->exists()) {
                    throw ValidationException::withMessages(['id_peminjaman' => 'Transaksi ini sudah selesai atau tidak sedang dipinjam.']);
                }
                if (! empty($data['booking_code']) && $loan->booking_code !== $data['booking_code']) {
                    throw ValidationException::withMessages(['booking_code' => 'Kode Booking tidak sesuai dengan transaksi.']);
                }
                $equipment = Alat::where('kode_alat', $data['kode_alat'])->lockForUpdate()->firstOrFail();
                $detailQuery = $loan->detail()->where('id_alat', $equipment->id_alat);
                if (! empty($data['id_detail'])) {
                    $detailQuery->where('id_detail', $data['id_detail']);
                }
                $detail = $detailQuery->lockForUpdate()->first();
                if (! $detail) {
                    throw ValidationException::withMessages(['kode_alat' => 'Kode alat tidak sesuai dengan item pada transaksi ini.']);
                }
                if (! empty($data['asset_id'])) {
                    $handedAsset = $detail->penyerahanDetail()
                        ->where('asset_id', $data['asset_id'])
                        ->whereDoesntHave('detail.pengembalianDetail', fn ($query) => $query->where('asset_id', $data['asset_id']))
                        ->exists();
                    if (! $handedAsset) {
                        throw ValidationException::withMessages(['asset_id' => 'Asset ID tidak tercatat diserahkan atau sudah dikembalikan.']);
                    }
                    if (($data['jumlah'] ?? 1) !== 1) {
                        throw ValidationException::withMessages(['jumlah' => 'Pengembalian Asset ID harus satu unit per proses.']);
                    }
                }
                $assignedUnits = $detail->itemUnits()->where('status', 'Dipinjam')->lockForUpdate()->get();
                $hasAssignedUnits = $assignedUnits->isNotEmpty();
                if ($hasAssignedUnits && empty($data['item_unit_id'])) {
                    throw ValidationException::withMessages(['item_unit_id' => 'Pilih unit fisik yang dikembalikan.']);
                }
                $itemUnit = null;
                if (! empty($data['item_unit_id'])) {
                    $itemUnit = ItemUnit::whereKey($data['item_unit_id'])
                        ->where('status', 'Dipinjam')
                        ->whereHas('details', fn ($query) => $query->where('loan_items.id_detail', $detail->id_detail))
                        ->lockForUpdate()
                        ->first();
                    if (! $itemUnit) {
                        throw ValidationException::withMessages(['item_unit_id' => 'Unit fisik tidak sesuai transaksi atau sudah dikembalikan.']);
                    }
                    if (($data['jumlah'] ?? 1) !== 1) {
                        throw ValidationException::withMessages(['jumlah' => 'Pengembalian unit fisik harus satu unit per proses.']);
                    }
                    if (! $detail->penyerahanDetail()->where('item_unit_id', $itemUnit->item_unit_id)->exists()) {
                        throw ValidationException::withMessages(['item_unit_id' => 'Unit ini belum tercatat dalam penyerahan.']);
                    }
                }

                $returnedQuantity = $detail->pengembalianDetail()->sum('jumlah');
                $remainingQuantity = max(0, $detail->jumlah - $returnedQuantity);
                $quantity = (int) ($data['jumlah'] ?? $remainingQuantity);
                if ($hasAssignedUnits && $quantity !== 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Pengembalian unit fisik harus satu unit per proses.']);
                }
                if ($remainingQuantity === 0 || $quantity > $remainingQuantity) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah pengembalian melebihi sisa alat pada transaksi.']);
                }
                $handedOver = $detail->penyerahanDetail()->sum('jumlah');
                $returnedBefore = $detail->pengembalianDetail()->sum('jumlah');
                if ($handedOver - $returnedBefore < $quantity) {
                    throw ValidationException::withMessages(['jumlah' => 'Alat belum tercatat diserahkan atau jumlah pengembalian melebihi penyerahan.']);
                }

                $returnedAt = now();
                $lateDays = max(0, $loan->tanggal_kembali_rencana->copy()->startOfDay()->diffInDays($returnedAt->copy()->startOfDay(), false));
                $loanDetails = $loan->detail()->with('pengembalianDetail')->get();
                $willComplete = $loanDetails->every(function ($loanDetail) use ($detail, $quantity): bool {
                    $returned = $loanDetail->pengembalianDetail->sum('jumlah');
                    if ($loanDetail->id_detail === $detail->id_detail) {
                        $returned += $quantity;
                    }

                    return $returned >= $loanDetail->jumlah;
                });
                $priorFine = DetailPengembalian::whereIn('id_detail', $loanDetails->pluck('id_detail'))->sum('denda');
                $totalFine = $lateDays * 5000;
                $fine = $willComplete
                    ? max(0, round($totalFine - $priorFine, 2))
                    : round(($totalFine * $quantity) / max(1, (int) $loanDetails->sum('jumlah')), 2);

                DetailPengembalian::create([
                    'id_detail' => $detail->id_detail,
                    'item_unit_id' => $itemUnit?->item_unit_id,
                    'asset_id' => $data['asset_id'] ?? $itemUnit?->asset_id,
                    'idempotency_key' => $data['idempotency_key'],
                    'jumlah' => $quantity,
                    'tanggal_kembali_aktual' => $returnedAt,
                    'kondisi_alat' => $data['kondisi_alat'],
                    'foto_bukti' => $data['foto_bukti'] ?? null,
                    'denda' => $fine,
                    'catatan' => $data['catatan'] ?? null,
                    'id_teknisi' => $data['id_teknisi'],
                ]);
                if (! empty($data['asset_id'])) {
                    $itemConditions = $detail->item_conditions ?? [];
                    $itemConditions[$data['asset_id']] = $data['kondisi_alat'];
                    $detail->update(['item_conditions' => $itemConditions]);
                }
                if ($itemUnit) {
                    $itemUnit->update($data['kondisi_alat'] === 'Hilang' ? [
                        'status' => 'Hilang',
                    ] : [
                        'status' => 'Tersedia',
                        'condition' => $data['kondisi_alat'],
                    ]);
                }

                $equipment->refresh();
                if ($itemUnit) {
                    $equipment->syncStockFromItemUnits();
                } elseif ($data['kondisi_alat'] === 'Baik') {
                    $equipment->stok_tersedia += $quantity;
                } elseif ($data['kondisi_alat'] === 'Maintenance') {
                    $equipment->stok_maintenance += $quantity;
                } elseif ($data['kondisi_alat'] === 'Rusak') {
                    $equipment->stok_rusak += $quantity;
                    $equipment->kondisi = 'Rusak';
                } elseif ($data['kondisi_alat'] === 'Hilang') {
                    $equipment->stok_total = max(0, $equipment->stok_total - $quantity);
                } else {
                    $equipment->stok_total = max(0, $equipment->stok_total - $quantity);
                }
                if (! $itemUnit) {
                    $equipment->syncStatusFromStock();
                }
                $equipment->save();

                $completed = $willComplete;
                if ($completed) {
                    $allReturns = DetailPengembalian::whereIn('id_detail', $loanDetails->pluck('id_detail'))->get();
                    $condition = $allReturns->contains('kondisi_alat', 'Hilang')
                        ? 'Hilang'
                        : ($allReturns->contains('kondisi_alat', 'Rusak')
                            ? 'Rusak'
                            : ($allReturns->contains('kondisi_alat', 'Maintenance') ? 'Maintenance' : 'Baik'));
                    $notes = $allReturns->pluck('catatan')->filter()->unique()->implode("\n");

                    Pengembalian::create([
                        'id_peminjaman' => $loan->id_peminjaman,
                        'tanggal_kembali_aktual' => $allReturns->max('tanggal_kembali_aktual'),
                        'kondisi_alat' => $condition,
                        'denda' => $allReturns->sum('denda'),
                        'catatan' => $notes !== '' ? $notes : null,
                    ]);
                    $loan->update(['status' => 'Selesai']);
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (DetailPengembalian::where('idempotency_key', $data['idempotency_key'])->exists()) {
                if (! empty($data['foto_bukti'])) {
                    Storage::disk('public')->delete($data['foto_bukti']);
                }

                return redirect()->route('technician.returns')->with('success', 'Pengembalian ini sudah tercatat; permintaan duplikat diabaikan.');
            }

            report($exception);

            return back()->withErrors(['id_peminjaman' => 'Pengembalian gagal disimpan. Silakan coba kembali.']);
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['id_peminjaman' => 'Pengembalian gagal disimpan. Silakan coba kembali.']);
        }

        if ($duplicate) {
            if (! empty($data['foto_bukti'])) {
                Storage::disk('public')->delete($data['foto_bukti']);
            }

            return redirect()->route('technician.returns')->with('success', 'Pengembalian ini sudah tercatat; permintaan duplikat diabaikan.');
        }

        $message = $completed
            ? 'Semua alat pada transaksi telah kembali. Denda: Rp '.number_format($fine, 0, ',', '.')
            : 'Pengembalian item dicatat; transaksi tetap aktif sampai seluruh alat kembali. Denda item ini: Rp '.number_format($fine, 0, ',', '.');

        return redirect()->route('technician.returns')->with('success', $message);
    }
}
