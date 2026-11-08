@extends('layouts.app')
@section('title','Riwayat Peminjaman')
@push('head')
<style>
    .loan-equipment-image{display:inline-flex;width:42px;height:42px;flex:none;align-items:center;justify-content:center;overflow:hidden;border-radius:.75rem;background:#f8f9fa}
    .loan-equipment-image>.equipment-photo{display:block;width:100%;height:100%;object-fit:contain;object-position:center;padding:3px}
    .loan-equipment-image>.equipment-photo-placeholder{width:100%;height:100%}
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Riwayat Peminjaman</h1><p>Pantau status permohonan dan pengembalian alat Anda.</p></div><a class="btn btn-primary" href="{{ route('catalog') }}"><i class="bi bi-plus-lg me-1"></i>Pengajuan baru</a></div>
<section class="surface">
    <div class="table-responsive">
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
                        $note = $loan->operational_notes
                            ?? $loan->catatan_admin
                            ?? ($fine > 0 ? 'Denda tercatat Rp '.number_format($fine, 0, ',', '.') : null);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $loan->booking_code }}</strong>
                            <small class="d-block text-muted">{{ $loan->tanggal_pengajuan->format('d M Y') }}</small>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($loan->detail as $detail)
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span class="loan-equipment-image rounded-3 bg-light"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->nama_alat }}"></x-equipment-image></span>
                                        <span>{{ $detail->nama_alat }} · kembali {{ $detail->pengembalianDetail->sum('jumlah') }}/{{ $detail->jumlah }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td>{{ $loan->tanggal_pinjam->format('d M Y') }}</td>
                        <td>{{ $loan->tanggal_kembali_rencana->format('d M Y') }}</td>
                        <td><span class="badge-status status-{{ strtolower($loan->status) }}">{{ $loan->status_label }}</span></td>
                        <td class="align-middle">{{ $note ?? '—' }}</td>
                        <td class="align-middle text-center">
                            @if($loan->status === 'Pending')
                                <form class="d-inline-block" method="post" action="{{ route('loans.cancel', $loan) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                    @csrf
                                    <button class="btn btn-sm btn-light text-danger text-nowrap" type="submit"><i class="bi bi-x-circle me-1"></i>Batal</button>
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
