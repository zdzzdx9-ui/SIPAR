@extends('layouts.app')
@section('title','Riwayat Peminjaman')
@push('head')
<style>
    .loan-equipment-list{display:flex;flex-direction:column;gap:8px;min-width:0}
    .loan-equipment-entry{display:flex;align-items:center;gap:9px;min-width:0;max-width:100%}
    .loan-equipment-image{display:grid;place-items:center;width:56px;height:56px;flex:0 0 56px;overflow:hidden;border:1px solid #e8edf1;background:#f8fafc}
    .loan-equipment-image>.equipment-photo{display:block;width:100%;height:100%;max-width:100%;object-fit:contain;padding:4px}
    .loan-equipment-image>.equipment-photo-placeholder:not([hidden]){display:grid}
    .loan-equipment-name{min-width:0;overflow-wrap:anywhere;line-height:1.45}
    @media(max-width:767.98px){
        .student-shell .loan-equipment-list{gap:10px}
        .student-shell .loan-equipment-entry{align-items:flex-start}
        .student-shell .loan-equipment-image{width:48px;height:48px;flex-basis:48px}
    }
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Riwayat Peminjaman</h1><p>Pantau status permohonan dan pengembalian alat Anda.</p></div><a class="btn btn-primary" href="{{ route('catalog') }}"><i class="bi bi-plus-lg me-1"></i>Pengajuan baru</a></div>
<section class="surface loan-history">
    <div class="table-responsive table-card-view" role="region" aria-label="Riwayat peminjaman">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Transaksi</th>
                    <th scope="col">Alat</th>
                    <th scope="col">Tanggal Pinjam</th>
                    <th scope="col">Rencana Kembali</th>
                    <th scope="col">Status</th>
                    <th scope="col">Catatan</th>
                    <th scope="col" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    @php
                        $fine = $loan->detail->flatMap(fn ($detail) => $detail->pengembalianDetail)->sum('denda');
                        $pickupNote = in_array($loan->status, ['Approved', 'Selesai'], true);
                        $note = match ($loan->status) {
                            'Approved', 'Selesai' => $loan->operational_notes
                                ? 'Lokasi pengambilan: Lab / Ruang Inventaris. Jam operasional teknisi: 12.00 - 13.00 WIB.'
                                : ($fine > 0 ? 'Denda tercatat Rp '.number_format($fine, 0, ',', '.') : 'Lokasi pengambilan: Lab / Ruang Inventaris. Jam operasional teknisi: 12.00 - 13.00 WIB.'),
                            'Rejected' => $loan->catatan_admin,
                            'Expired' => $loan->operational_notes,
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td data-label="Transaksi" data-card-wide>
                            <div><strong>{{ $loan->booking_code }}</strong>
                            <small class="d-block text-muted">{{ $loan->tanggal_pengajuan->format('d M Y') }}</small></div>
                        </td>
                        <td data-label="Alat" data-card-wide>
                            <div class="loan-equipment-list">
                                @foreach($loan->detail as $detail)
                                    <div class="loan-equipment-entry">
                                        <span class="loan-equipment-image rounded-3 bg-light"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->nama_alat }}"></x-equipment-image></span>
                                        <span class="loan-equipment-name">{{ $detail->nama_alat }} · kembali {{ $detail->pengembalianDetail->sum('jumlah') }}/{{ $detail->jumlah }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                        <td data-label="Tanggal pinjam">{{ $loan->tanggal_pinjam->format('d M Y') }}</td>
                        <td data-label="Rencana kembali">{{ $loan->tanggal_kembali_rencana->format('d M Y') }}</td>
                        <td data-label="Status"><span class="badge-status status-{{ strtolower($loan->status) }}">{{ $loan->status_label }}</span></td>
                        <td class="align-middle" data-label="Catatan" data-card-wide>
                            <div>
                                @if($note)<span class="d-block">{{ $note }}</span>@endif
                                @if($pickupNote)
                                    <small class="d-block text-muted">Harap ambil alat dalam 1×24 jam / pada jam operasional berikutnya setelah disetujui. Pembatalan dapat dilakukan sebelum persetujuan atau sebelum alat diserahkan kepada peminjam.</small>
                                @endif
                            </div>
                        </td>
                        <td class="align-middle text-center" data-label="Aksi" data-card-wide>
                            @if($loan->status === 'Pending' || ($loan->status === 'Approved' && $loan->detail->isNotEmpty() && $loan->detail->every(fn ($detail) => $detail->penyerahanDetail->isEmpty())))
                                <form class="d-inline-block" method="post" action="{{ route('loans.cancel', $loan) }}" data-confirm-title="Konfirmasi pembatalan peminjaman" data-confirm-message="Apakah Anda yakin ingin membatalkan {{ $loan->booking_code }}? Peminjaman yang disetujui hanya dapat dibatalkan sebelum alat diserahkan." data-confirm-label="Ya, Batalkan">
                                    @csrf
                                    <button class="btn btn-sm btn-light text-danger text-nowrap" type="submit"><i class="bi bi-x-circle me-1"></i>{{ $loan->status === 'Approved' ? 'Batalkan Peminjaman' : 'Batalkan Pengajuan' }}</button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">Belum ada pengajuan peminjaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $loans->links() }}</div>
</section>
@endsection
