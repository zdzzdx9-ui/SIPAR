<?php

namespace App\Http\Controllers;

use App\Models\Alat;
use App\Models\Peminjaman;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role;
        $isAdmin = $role === 'admin';
        $isTeknisi = $role === 'teknisi';
        $loans = Peminjaman::with(['user', 'detail.alat'])->latest('tanggal_pengajuan');

        if (! $isAdmin && ! $isTeknisi) {
            $loans->where('id_user', $request->user()->id_user);
        }
        $myLoans = Peminjaman::where('id_user', $request->user()->id_user);
        $handoverCount = $isTeknisi
            ? Peminjaman::where('status', 'Approved')
                ->whereHas('detail', fn ($query) => $query->whereRaw('(
                    SELECT COALESCE(SUM(detail_penyerahan.jumlah), 0)
                    FROM detail_penyerahan
                    WHERE detail_penyerahan.id_detail = loan_items.id_detail
                ) < loan_items.jumlah'))
                ->count()
            : 0;
        $activeReturnCount = $isTeknisi
            ? Peminjaman::where('status', 'Approved')
                ->whereHas('detail', fn ($query) => $query->whereRaw('(
                    SELECT COALESCE(SUM(detail_penyerahan.jumlah), 0)
                    FROM detail_penyerahan
                    WHERE detail_penyerahan.id_detail = loan_items.id_detail
                ) > (
                    SELECT COALESCE(SUM(detail_pengembalian.jumlah), 0)
                    FROM detail_pengembalian
                    WHERE detail_pengembalian.id_detail = loan_items.id_detail
                )'))
                ->count()
            : 0;

        return view('dashboard', [
            'isAdmin' => $isAdmin,
            'isTeknisi' => $isTeknisi,
            'totalAlat' => Alat::count(),
            'tersedia' => Alat::where('status_alat', 'Tersedia')->sum('stok_tersedia'),
            'pending' => Peminjaman::where('status', 'Pending')->count(),
            'dipinjam' => Peminjaman::where('status', 'Approved')->count(),
            'myLoanCount' => (clone $myLoans)->count(),
            'myPendingCount' => (clone $myLoans)->where('status', 'Pending')->count(),
            'myCompletedCount' => (clone $myLoans)->where('status', 'Selesai')->count(),
            'recentLoans' => $loans->take(6)->get(),
            'topAlat' => Alat::with('kategori')->orderByDesc('stok_total')->take(5)->get(),
            'handoverCount' => $handoverCount,
            'activeReturnCount' => $activeReturnCount,
        ]);
    }
}
