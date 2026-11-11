<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\DetailPenyerahan;
use App\Models\DetailPeminjaman;
use App\Models\ItemUnit;
use App\Models\Peminjaman;
use App\Models\User;
use App\Services\ExpireUncollectedLoans;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function catalog(Request $request): View
    {
        $equipment = Alat::with('kategori')->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(fn ($builder) => $builder->where('nama_alat', 'like', $term)->orWhere('kode_alat', 'like', $term));
        })->orderBy('nama_alat')->get();

        $activeEquipmentStatuses = DetailPeminjaman::with('peminjaman:id_peminjaman,status')
            ->whereHas('peminjaman', fn ($query) => $query
                ->where('id_user', $request->user()->id_user)
                ->whereIn('status', ['Pending', 'Approved']))
            ->whereRaw('loan_items.jumlah > (
                SELECT COALESCE(SUM(detail_pengembalian.jumlah), 0)
                FROM detail_pengembalian
                WHERE detail_pengembalian.id_detail = loan_items.id_detail
            )')
            ->get()
            ->mapWithKeys(fn (DetailPeminjaman $detail) => [$detail->id_alat => $detail->peminjaman->status]);

        return view('catalog', compact('equipment', 'activeEquipmentStatuses'));
    }

    public function create(Request $request): RedirectResponse
    {
        if ($request->filled('id_alat') && ! $request->has('items')) {
            $request->merge(['items' => [[
                'id_alat' => $request->input('id_alat'),
                'jumlah' => $request->input('jumlah'),
            ]]]);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_alat' => ['required', 'integer', 'distinct', 'exists:alat,id_alat'],
            'items.*.jumlah' => ['required', 'integer', 'in:1'],
            'tanggal_pinjam' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_kembali_rencana' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'keperluan' => ['required', 'string', 'max:2000'],
        ]);
        if (CarbonImmutable::parse($data['tanggal_kembali_rencana'])
            ->gt(CarbonImmutable::parse($data['tanggal_pinjam'])->addDays(5))) {
            throw ValidationException::withMessages([
                'tanggal_kembali_rencana' => 'Tanggal kembali maksimal lima hari setelah tanggal pinjam.',
            ]);
        }

        try {
            DB::transaction(function () use ($request, $data) {
                User::whereKey($request->user()->id_user)->lockForUpdate()->firstOrFail();
                $requestedItems = collect($data['items'])->keyBy(fn (array $item) => (int) $item['id_alat']);
                $equipmentList = Alat::whereIn('id_alat', $requestedItems->keys())->orderBy('id_alat')->lockForUpdate()->get();

                $activeDetail = DetailPeminjaman::with('alat')
                    ->whereIn('id_alat', $requestedItems->keys())
                    ->whereHas('peminjaman', fn ($query) => $query
                        ->where('id_user', $request->user()->id_user)
                        ->whereIn('status', ['Pending', 'Approved']))
                    ->whereRaw('loan_items.jumlah > (
                        SELECT COALESCE(SUM(detail_pengembalian.jumlah), 0)
                        FROM detail_pengembalian
                        WHERE detail_pengembalian.id_detail = loan_items.id_detail
                    )')
                    ->first();
                if ($activeDetail) {
                    throw ValidationException::withMessages([
                        'items' => "Anda masih memiliki pengajuan atau peminjaman aktif untuk {$activeDetail->nama_alat}. Alat yang sama baru dapat diajukan kembali setelah dikembalikan.",
                    ]);
                }

                if ($equipmentList->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Data alat berubah saat pengajuan diproses. Muat ulang katalog dan coba kembali.',
                    ]);
                }

                foreach ($equipmentList as $equipment) {
                    $quantity = (int) $requestedItems[$equipment->id_alat]['jumlah'];
                    if ($equipment->status_alat !== 'Tersedia' || $equipment->stok_tersedia < $quantity) {
                        $message = $equipment->status_alat !== 'Tersedia'
                            ? "{$equipment->nama_alat} sedang {$equipment->status_alat} dan tidak dapat dipinjam."
                            : "Jumlah {$equipment->nama_alat} melebihi stok yang tersedia ({$equipment->stok_tersedia}); pengajuan tidak disimpan.";
                        throw ValidationException::withMessages([
                            'items' => $message,
                        ]);
                    }
                }

                $loan = Peminjaman::create([
                    'id_user' => $request->user()->id_user,
                    'tanggal_pinjam' => $data['tanggal_pinjam'],
                    'tanggal_kembali_rencana' => $data['tanggal_kembali_rencana'],
                    'keperluan' => $data['keperluan'],
                    'status' => 'Pending',
                    'status_read_at' => now(),
                ]);
                foreach ($equipmentList as $equipment) {
                    $loan->detail()->create([
                        'id_alat' => $equipment->id_alat,
                        'jumlah' => (int) $requestedItems[$equipment->id_alat]['jumlah'],
                    ]);
                }
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['jumlah' => 'Pengajuan belum dapat disimpan. Silakan coba kembali.'])->withInput();
        }

        return redirect()->route('loans.mine')->with('success', 'Pengajuan tersimpan dan menunggu verifikasi.');
    }

    public function mine(Request $request, ExpireUncollectedLoans $expireUncollectedLoans): View
    {
        $expireUncollectedLoans->run();
        Peminjaman::where('id_user', $request->user()->id_user)
            ->whereNull('status_read_at')
            ->update(['status_read_at' => now()]);

        $loans = Peminjaman::with(['detail.alat', 'detail.penyerahanDetail', 'detail.pengembalianDetail', 'pengembalian'])
            ->where('id_user', $request->user()->id_user)
            ->latest('tanggal_pengajuan')->paginate(10);

        return view('loans.mine', compact('loans'));
    }

    public function approvals(ExpireUncollectedLoans $expireUncollectedLoans): View
    {
        $expireUncollectedLoans->run();
        $loans = Peminjaman::with(['user', 'detail.alat'])
            ->whereIn('status', ['Pending', 'Approved'])
            ->latest('tanggal_pengajuan')->paginate(12);

        return view('admin.approvals', compact('loans'));
    }

    public function cancel(Request $request, Peminjaman $peminjaman, ExpireUncollectedLoans $expireUncollectedLoans): RedirectResponse
    {
        $expireUncollectedLoans->run();
        $cancelledApprovedLoan = false;
        $cancelled = DB::transaction(function () use ($request, $peminjaman, &$cancelledApprovedLoan): bool {
            $loan = Peminjaman::whereKey($peminjaman->id_peminjaman)
                ->where('user_id', $request->user()->id_user)
                ->lockForUpdate()
                ->first();

            if (! $loan || ! in_array($loan->status, ['Pending', 'Approved'], true)) {
                return false;
            }

            if ($loan->status === 'Approved') {
                $details = $loan->detail()->orderBy('id_alat')->lockForUpdate()->get();
                if ($details->isEmpty() || DetailPenyerahan::whereIn('id_detail', $details->pluck('id_detail'))->exists()) {
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

                $cancelledApprovedLoan = true;
            }

            $loan->update([
                'status' => 'Cancelled',
                'status_read_at' => null,
                'overdue_flag' => false,
                'reminder_flag' => false,
            ]);

            return true;
        });

        if (! $cancelled) {
            return back()->withErrors(['status' => 'Transaksi hanya dapat dibatalkan sebelum alat diserahkan, dan hanya selama masih menunggu persetujuan atau disetujui.']);
        }

        return back()->with('success', $cancelledApprovedLoan
            ? 'Peminjaman dibatalkan sebelum penyerahan dan stok telah dikembalikan.'
            : 'Pengajuan peminjaman berhasil dibatalkan.');
    }

    public function approve(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        try {
            DB::transaction(function () use ($peminjaman) {
                $loan = Peminjaman::whereKey($peminjaman->id_peminjaman)->lockForUpdate()->firstOrFail();
                if ($loan->status !== 'Pending') {
                    throw ValidationException::withMessages(['status' => 'Pengajuan ini sudah diproses.']);
                }

                $details = $loan->detail()->orderBy('id_alat')->lockForUpdate()->get();
                $equipmentList = Alat::whereIn('id_alat', $details->pluck('id_alat'))
                    ->orderBy('id_alat')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_alat');

                foreach ($details as $detail) {
                    $equipment = $equipmentList->get($detail->id_alat);
                    if (! $equipment) {
                        throw ValidationException::withMessages(['stok' => 'Data alat pada transaksi tidak ditemukan.']);
                    }
                    if ($equipment->status_alat !== 'Tersedia' || $equipment->stok_tersedia < $detail->jumlah) {
                        throw ValidationException::withMessages(['stok' => "Stok {$equipment->nama_alat} tidak lagi mencukupi."]);
                    }

                    if ($equipment->itemUnits()->exists()) {
                        $units = ItemUnit::where('alat_id', $equipment->id_alat)
                            ->where('condition', 'Baik')
                            ->where('status', 'Tersedia')
                            ->orderBy('asset_id')
                            ->lockForUpdate()
                            ->limit($detail->jumlah)
                            ->get();
                        if ($units->count() !== $detail->jumlah) {
                            throw ValidationException::withMessages([
                                'stok' => "Unit fisik {$equipment->nama_alat} yang berkondisi baik tidak mencukupi.",
                            ]);
                        }
                    }
                }

                foreach ($details as $detail) {
                    $equipment = $equipmentList->get($detail->id_alat);
                    $equipment->stok_tersedia -= $detail->jumlah;
                    $equipment->syncStatusFromStock();
                    $equipment->save();
                }

                $loan->update([
                    'status' => 'Approved',
                    'approved_at' => now(),
                    'status_read_at' => null,
                    'operational_notes' => 'Ambil di Lab / Ruang Inventaris pukul 12.00 - 13.00 WIB, maksimal 24 jam sejak persetujuan. Siswa dapat membatalkan peminjaman sebelum alat diserahkan.',
                    'overdue_flag' => false,
                    'reminder_flag' => false,
                ]);
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['status' => 'Persetujuan gagal disimpan. Silakan coba kembali.']);
        }

        return back()->with('success', 'Pengajuan disetujui dan stok diperbarui.');
    }

    public function reject(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        $data = $request->validate(['catatan_admin' => ['required', 'string', 'max:1000']]);
        $updated = Peminjaman::whereKey($peminjaman->id_peminjaman)
            ->where('status', 'Pending')
            ->update(['status' => 'Rejected', 'catatan_admin' => $data['catatan_admin'], 'status_read_at' => null]);

        if (! $updated) {
            return back()->withErrors(['status' => 'Hanya pengajuan Pending yang dapat ditolak.']);
        }

        return back()->with('success', 'Pengajuan ditolak dan catatan tersimpan.');
    }
}
