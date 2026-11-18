@extends('layouts.app')
@section('title', 'Pengembalian Alat')
@section('content')
<div class="page-head"><div><h1>Proses Pengembalian</h1><p>Cari dengan Kode Transaksi, Kode Alat, atau NIS siswa. Hasil menampilkan seluruh item dan status pengembaliannya.</p></div></div>
<form class="surface p-3 mb-3 responsive-search" method="get" action="{{ route('technician.returns') }}">
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Contoh: PJM-00000004, MIK-001, atau NIS siswa" aria-label="Kode Transaksi, Kode Alat, atau NIS siswa">
    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Cari</button>
</form>
@forelse($activeLoans as $loan)
<section class="surface p-3 p-md-4 mb-3 operational-loan-card">
    <div class="d-flex justify-content-between gap-3 flex-wrap mb-3 operational-loan-heading">
        <div class="operational-loan-identity">
            <div class="small text-muted mb-1">Kode Transaksi</div>
            <span class="badge text-bg-primary font-monospace px-3 py-2">{{ $loan->booking_code }}</span>
            <div class="panel-subtitle">{{ $loan->user->nama }} · NIS {{ $loan->user->nis_nip }}</div>
        </div>
        <div class="text-end operational-deadline">Jatuh tempo {{ $loan->tanggal_kembali_rencana->format('d M Y') }}
            @if($loan->is_overdue)
                <span class="badge-status status-rusak">Terlambat {{ $loan->overdue_days }} hari</span>
                <small class="d-block text-muted mt-1">Denda berjalan Rp {{ number_format($loan->estimated_fine, 0, ',', '.') }} · Rp5.000/hari</small>
            @endif
        </div>
    </div>
    @foreach($loan->detail as $detail)
        @php($returned = $detail->pengembalianDetail->sum('jumlah'))
        @php($handed = $detail->penyerahanDetail->sum('jumlah'))
        @php($assets = $detail->penyerahanDetail->whereNotNull('asset_id')->whereNotIn('asset_id', $detail->pengembalianDetail->pluck('asset_id')->filter()))
        <div class="border rounded p-3 mb-3 technician-equipment-block">
            <div class="d-flex align-items-center gap-2 mb-3 technician-equipment-heading"><span class="equipment-icon overflow-hidden"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->nama_alat }}"></x-equipment-image></span><div class="technician-equipment-copy"><strong>{{ $detail->nama_alat }}</strong><small class="d-block text-muted">{{ $detail->kode_alat ?? 'Kode alat arsip' }} · diajukan {{ $detail->jumlah }}, diserahkan {{ $handed }}, dikembalikan {{ $returned }}</small></div></div>
            @if($handed === 0)
                <div class="alert alert-warning mb-0">Belum diserahkan oleh teknisi. Pengembalian belum dapat dicatat.</div>
            @elseif($returned >= $handed)
                <div class="alert alert-success mb-0">Semua unit yang telah diserahkan sudah dikembalikan.</div>
            @else
            @if($assets->isNotEmpty())
                @foreach($assets as $handover)
                <form class="row g-2 align-items-end border-top py-3 technician-return-form" method="post" action="{{ route('technician.returns.store') }}" enctype="multipart/form-data" data-disable-on-submit data-confirm-title="Konfirmasi pengembalian alat" data-confirm-message="Pastikan kondisi dan foto bukti pengembalian sudah benar. Apakah Anda yakin ingin mencatat pengembalian aset {{ $handover->asset_id }}?" data-confirm-label="Ya, Kembalikan">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="id_peminjaman" value="{{ $loan->id_peminjaman }}"><input type="hidden" name="booking_code" value="{{ $loan->booking_code }}"><input type="hidden" name="id_detail" value="{{ $detail->id_detail }}"><input type="hidden" name="kode_alat" value="{{ $detail->alat->kode_alat }}"><input type="hidden" name="asset_id" value="{{ $handover->asset_id }}"><input type="hidden" name="item_unit_id" value="{{ $handover->item_unit_id }}"><input type="hidden" name="jumlah" value="1">
                    <div class="col-12 technician-item-id"><strong>Asset ID: {{ $handover->asset_id }}</strong></div>
                    <div class="col-12 col-md-3"><label class="form-label">Kondisi item</label><select class="form-select" name="kondisi_alat" required><option value="Baik">Baik</option><option value="Maintenance">Maintenance</option><option value="Rusak">Rusak</option><option value="Hilang">Hilang</option></select></div>
                    <div class="col-12 col-md-5"><label class="form-label">Foto kondisi item <span class="text-danger">*</span></label><input class="form-control" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" required><div class="form-text">Pilih file foto JPG, PNG, atau WEBP. Foto akan dioptimalkan otomatis sebelum disimpan (maks. 5 MB).</div></div>
                    <div class="col-12 col-md-4"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="2000"><button class="btn btn-primary mt-2" type="submit" data-submitting-text="Menyimpan...">Catat pengembalian</button></div>
                </form>
                @endforeach
            @else
                @php($remaining = max(0, $handed - $returned))
                <form class="row g-2 align-items-end border-top pt-3 technician-return-form" method="post" action="{{ route('technician.returns.store') }}" enctype="multipart/form-data" data-disable-on-submit data-confirm-title="Konfirmasi pengembalian alat" data-confirm-message="Pastikan jumlah dan kondisi alat yang diterima sudah benar. Apakah Anda yakin ingin mencatat pengembalian ini?" data-confirm-label="Ya, Kembalikan">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="id_peminjaman" value="{{ $loan->id_peminjaman }}"><input type="hidden" name="booking_code" value="{{ $loan->booking_code }}"><input type="hidden" name="id_detail" value="{{ $detail->id_detail }}"><input type="hidden" name="kode_alat" value="{{ $detail->alat->kode_alat }}">
                    <div class="col-12 col-md-2"><label class="form-label">Jumlah</label><input class="form-control" type="number" name="jumlah" min="1" max="{{ $remaining }}" value="1" required></div>
                    <div class="col-12 col-md-3"><label class="form-label">Kondisi item</label><select class="form-select" name="kondisi_alat" required><option value="Baik">Baik</option><option value="Maintenance">Maintenance</option><option value="Rusak">Rusak</option><option value="Hilang">Hilang</option></select></div>
                    <div class="col-12 col-md-4"><label class="form-label">Foto kondisi item <span class="text-danger">*</span></label><input class="form-control" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" required><div class="form-text">Pilih file foto JPG, PNG, atau WEBP. Foto akan dioptimalkan otomatis sebelum disimpan (maks. 5 MB).</div></div>
                    <div class="col-12 col-md-3"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="2000"><button class="btn btn-primary mt-2" type="submit" data-submitting-text="Menyimpan...">Catat pengembalian</button></div>
                </form>
            @endif
            @endif
        </div>
    @endforeach
</section>
@empty
<section class="surface p-5 text-center text-muted">{{ request('q') ? 'Transaksi aktif tidak ditemukan untuk Kode Transaksi, Kode Alat, atau NIS tersebut.' : 'Tidak ada transaksi aktif. Cari menggunakan Kode Transaksi, Kode Alat, atau NIS siswa.' }}</section>
@endforelse
<div class="mt-3">{{ $activeLoans->links() }}</div>
@endsection
