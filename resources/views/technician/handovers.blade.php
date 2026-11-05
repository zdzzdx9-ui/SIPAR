@extends('layouts.app')
@section('title', 'Penyerahan Alat')
@section('content')
<div class="page-head"><div><h1>Penyerahan Alat</h1><p>Cari transaksi menggunakan Kode Booking atau NIS siswa, lalu catat serial number untuk setiap item.</p></div></div>
<form class="surface p-3 mb-3 d-flex gap-2" method="get" action="{{ route('technician.handovers') }}">
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Kode Transaksi atau NIS siswa" aria-label="Kode Transaksi atau NIS siswa">
    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
</form>
@forelse($loans as $loan)
<section class="surface p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
        <div>
            <div class="small text-muted mb-1">Kode Transaksi</div>
            <span class="badge text-bg-primary font-monospace px-3 py-2">{{ $loan->booking_code }}</span>
            <div class="panel-subtitle">{{ $loan->user->nama }} · NIS {{ $loan->user->nis_nip }}</div>
        </div>
        <div class="text-end"><div>Jatuh tempo {{ $loan->tanggal_kembali_rencana->format('d M Y') }}</div>@if($loan->is_overdue)<span class="badge-status status-rusak">Terlambat</span>@endif</div>
    </div>
    @if($loan->operational_notes)<div class="alert alert-info py-2">{{ $loan->operational_notes }}</div>@endif
    @foreach($loan->detail as $detail)
        @php($handed = $detail->penyerahanDetail->sum('jumlah'))
        @if($handed < $detail->jumlah)
        <form class="border rounded p-3 mb-2" method="post" action="{{ route('technician.handovers.store', $loan) }}" enctype="multipart/form-data" data-disable-on-submit>
            @csrf
            <input type="hidden" name="id_detail" value="{{ $detail->id_detail }}">
            <input type="hidden" name="jumlah" value="{{ $detail->jumlah - $handed }}">
            <div class="d-flex align-items-center gap-2 mb-3"><span class="equipment-icon overflow-hidden"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->alat->nama_alat }}"></x-equipment-image></span><div><strong>{{ $detail->alat->nama_alat }}</strong><small class="d-block text-muted">{{ $detail->alat->kode_alat }} · sisa {{ $detail->jumlah - $handed }} unit</small></div></div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Asset ID / Serial Number (satu per unit, pisahkan dengan baris baru atau koma)</label><textarea class="form-control" name="asset_ids" rows="2" required placeholder="Contoh: LAP-001&#10;LAP-002"></textarea></div>
                <div class="col-12 col-sm-6"><label class="form-label">Kondisi saat diserahkan</label><select class="form-select" name="kondisi_alat" required><option value="Baik">Baik</option><option value="Rusak">Rusak</option></select></div>
                <div class="col-12 col-sm-6"><label class="form-label">Foto bukti siswa dengan alat <span class="text-danger">*</span> (maks. 5 MB)</label><input class="form-control" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" capture="environment" required></div>
                <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="catatan" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-primary" type="submit" data-submitting-text="Menyimpan...">Simpan penyerahan</button></div>
            </div>
        </form>
        @else
        <div class="border rounded p-3 mb-2"><strong>{{ $detail->alat->nama_alat }}</strong> · seluruh {{ $detail->jumlah }} unit telah diserahkan<br><small class="text-muted">Asset ID: {{ $detail->penyerahanDetail->pluck('asset_id')->filter()->join(', ') }}</small></div>
        @endif
    @endforeach
</section>
@empty
<section class="surface p-5 text-center text-muted">{{ request('q') ? 'Transaksi aktif tidak ditemukan untuk Kode Booking atau NIS tersebut.' : 'Belum ada transaksi disetujui. Cari menggunakan Kode Booking atau NIS siswa.' }}</section>
@endforelse
<div class="mt-3">{{ $loans->links() }}</div>
@endsection
