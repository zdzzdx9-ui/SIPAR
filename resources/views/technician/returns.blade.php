@extends('layouts.app')
@section('title', 'Pengembalian Alat')
@section('content')
<div class="page-head"><div><h1>Proses Pengembalian</h1><p>Cari transaksi menggunakan Kode Booking atau NIS siswa, lalu proses kondisi setiap item.</p></div></div>
<form class="surface p-3 mb-3 d-flex gap-2" method="get" action="{{ route('technician.returns') }}">
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Kode Booking atau NIS siswa" aria-label="Kode Booking atau NIS siswa">
    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
</form>
@forelse($activeLoans as $loan)
<section class="surface p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
        <div><h2 class="panel-title">{{ $loan->booking_code }}</h2><div class="panel-subtitle">{{ $loan->user->nama }} · NIS {{ $loan->user->nis_nip }}</div></div>
        <div class="text-end">Jatuh tempo {{ $loan->tanggal_kembali_rencana->format('d M Y') }} @if($loan->is_overdue)<span class="badge-status status-rusak">Terlambat</span>@endif</div>
    </div>
    @foreach($loan->detail as $detail)
        @php($returned = $detail->pengembalianDetail->sum('jumlah'))
        @php($handed = $detail->penyerahanDetail->sum('jumlah'))
        @php($assets = $detail->penyerahanDetail->whereNotNull('asset_id')->whereNotIn('asset_id', $detail->pengembalianDetail->pluck('asset_id')->filter()))
        @if($returned < $handed)
        <div class="border rounded p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-3"><span class="equipment-icon overflow-hidden"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->alat->nama_alat }}"></x-equipment-image></span><div><strong>{{ $detail->alat->nama_alat }}</strong><small class="d-block text-muted">{{ $detail->alat->kode_alat }} · telah diserahkan {{ $handed }}, kembali {{ $returned }}</small></div></div>
            @if($assets->isNotEmpty())
                @foreach($assets as $handover)
                <form class="row g-2 align-items-end border-top py-3" method="post" action="{{ route('technician.returns.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id_peminjaman" value="{{ $loan->id_peminjaman }}"><input type="hidden" name="booking_code" value="{{ $loan->booking_code }}"><input type="hidden" name="id_detail" value="{{ $detail->id_detail }}"><input type="hidden" name="kode_alat" value="{{ $detail->alat->kode_alat }}"><input type="hidden" name="asset_id" value="{{ $handover->asset_id }}"><input type="hidden" name="item_unit_id" value="{{ $handover->item_unit_id }}"><input type="hidden" name="jumlah" value="1">
                    <div class="col-12"><strong>Asset ID: {{ $handover->asset_id }}</strong></div>
                    <div class="col-12 col-md-3"><label class="form-label">Kondisi item</label><select class="form-select" name="kondisi_alat" required><option value="Baik">Baik</option><option value="Maintenance">Maintenance</option><option value="Rusak">Rusak</option><option value="Hilang">Hilang</option></select></div>
                    <div class="col-12 col-md-5"><label class="form-label">Foto kondisi item <span class="text-danger">*</span></label><input class="form-control" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" capture="environment" required></div>
                    <div class="col-12 col-md-4"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="2000"><button class="btn btn-primary mt-2" type="submit">Catat pengembalian</button></div>
                </form>
                @endforeach
            @else
                @php($remaining = max(0, $handed - $returned))
                <form class="row g-2 align-items-end border-top pt-3" method="post" action="{{ route('technician.returns.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id_peminjaman" value="{{ $loan->id_peminjaman }}"><input type="hidden" name="booking_code" value="{{ $loan->booking_code }}"><input type="hidden" name="id_detail" value="{{ $detail->id_detail }}"><input type="hidden" name="kode_alat" value="{{ $detail->alat->kode_alat }}">
                    <div class="col-12 col-md-2"><label class="form-label">Jumlah</label><input class="form-control" type="number" name="jumlah" min="1" max="{{ $remaining }}" value="1" required></div>
                    <div class="col-12 col-md-3"><label class="form-label">Kondisi item</label><select class="form-select" name="kondisi_alat" required><option value="Baik">Baik</option><option value="Maintenance">Maintenance</option><option value="Rusak">Rusak</option><option value="Hilang">Hilang</option></select></div>
                    <div class="col-12 col-md-4"><label class="form-label">Foto kondisi item <span class="text-danger">*</span></label><input class="form-control" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" capture="environment" required></div>
                    <div class="col-12 col-md-3"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="2000"><button class="btn btn-primary mt-2" type="submit">Catat pengembalian</button></div>
                </form>
            @endif
        </div>
        @endif
    @endforeach
</section>
@empty
<section class="surface p-5 text-center text-muted">{{ request('q') ? 'Transaksi aktif tidak ditemukan untuk Kode Booking atau NIS tersebut.' : 'Tidak ada transaksi aktif. Cari menggunakan Kode Booking atau NIS siswa.' }}</section>
@endforelse
<div class="mt-3">{{ $activeLoans->links() }}</div>
@endsection
