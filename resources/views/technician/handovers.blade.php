@extends('layouts.app')
@section('title', 'Penyerahan Alat')
@section('content')
<div class="page-head"><div><h1>Penyerahan Alat</h1><p>Cari transaksi menggunakan Kode Booking atau NIS siswa. Semua alat dalam satu transaksi dicatat dan disimpan bersama.</p></div></div>
<form class="surface p-3 mb-3 responsive-search" method="get" action="{{ route('technician.handovers') }}">
    <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Kode Transaksi atau NIS siswa" aria-label="Kode Transaksi atau NIS siswa">
    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Cari</button>
</form>
@forelse($loans as $loan)
    @php($pendingDetails = $loan->detail->filter(fn ($detail) => $detail->penyerahanDetail->sum('jumlah') < $detail->jumlah))
    <section class="surface p-3 p-md-4 mb-3">
        <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
            <div>
                <div class="small text-muted mb-1">Kode Transaksi</div>
                <span class="badge text-bg-primary font-monospace px-3 py-2">{{ $loan->booking_code }}</span>
                <div class="panel-subtitle">{{ $loan->user->nama }} · NIS {{ $loan->user->nis_nip }}</div>
            </div>
            <div class="text-end"><div>Jatuh tempo {{ $loan->tanggal_kembali_rencana->format('d M Y') }}</div>@if($loan->is_overdue)<span class="badge-status status-rusak">Terlambat {{ $loan->overdue_days }} hari</span>@endif</div>
        </div>
        @if($loan->operational_notes)<div class="alert alert-info py-2">{{ $loan->operational_notes }}</div>@endif

        @if($pendingDetails->isNotEmpty())
            <form class="border rounded p-3" method="post" action="{{ route('technician.handovers.store', $loan) }}" enctype="multipart/form-data" data-disable-on-submit data-confirm-title="Konfirmasi penyerahan alat" data-confirm-message="Pastikan nomor aset, kondisi, dan foto bukti sudah benar. Apakah Anda yakin ingin mencatat penyerahan alat untuk {{ $loan->booking_code }}?" data-confirm-label="Ya, Serahkan">
                @csrf
                @foreach($loan->detail as $detail)
                    @php($handed = $detail->penyerahanDetail->sum('jumlah'))
                    @php($remaining = $detail->jumlah - $handed)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="equipment-icon overflow-hidden"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->alat->nama_alat }}"></x-equipment-image></span>
                            <div>
                                <strong>{{ $detail->alat->nama_alat }}</strong>
                                <small class="d-block text-muted">{{ $detail->alat->kode_alat }} · {{ $remaining > 0 ? "sisa {$remaining} unit" : "seluruh {$detail->jumlah} unit telah diserahkan" }}</small>
                            </div>
                        </div>
                        @if($remaining > 0)
                            <input type="hidden" name="items[{{ $detail->id_detail }}][id_detail]" value="{{ $detail->id_detail }}">
                            <input type="hidden" name="items[{{ $detail->id_detail }}][jumlah]" value="{{ $remaining }}">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="asset-ids-{{ $detail->id_detail }}">Asset ID / Serial Number{{ $detail->alat->itemUnits->isNotEmpty() ? ' pilihan' : ' (satu per unit)' }}</label>
                                    <textarea class="form-control" id="asset-ids-{{ $detail->id_detail }}" name="items[{{ $detail->id_detail }}][asset_ids]" rows="2" @if($detail->alat->itemUnits->isEmpty()) required @endif placeholder="Contoh: LAP-001&#10;LAP-002"></textarea>
                                    @if($detail->alat->itemUnits->isNotEmpty())
                                        <small class="text-muted">Opsional. Sistem otomatis memilih unit berstatus Tersedia jika dikosongkan atau ID pilihan sedang dipakai.</small>
                                    @endif
                                </div>
                                <div class="col-12 col-sm-6"><label class="form-label" for="condition-{{ $detail->id_detail }}">Kondisi {{ $detail->alat->nama_alat }}</label><select class="form-select" id="condition-{{ $detail->id_detail }}" name="items[{{ $detail->id_detail }}][kondisi_alat]" required><option value="Baik">Baik</option><option value="Rusak">Rusak</option></select></div>
                                <div class="col-12"><label class="form-label" for="note-{{ $detail->id_detail }}">Catatan {{ $detail->alat->nama_alat }}</label><textarea class="form-control" id="note-{{ $detail->id_detail }}" name="items[{{ $detail->id_detail }}][catatan]" rows="2"></textarea></div>
                            </div>
                        @else
                            <small class="text-muted">Asset ID: {{ $detail->penyerahanDetail->pluck('asset_id')->filter()->join(', ') }}</small>
                        @endif
                    </div>
                @endforeach
                <div class="mb-3"><label class="form-label" for="handover-photo-{{ $loan->id_peminjaman }}">Foto bukti siswa dengan seluruh alat <span class="text-danger">*</span> (maks. 5 MB)</label><input class="form-control" id="handover-photo-{{ $loan->id_peminjaman }}" type="file" name="foto_bukti" accept="image/jpeg,image/png,image/webp" capture="environment" required><div class="form-text">Foto akan dioptimalkan otomatis sebelum disimpan.</div></div>
                <button class="btn btn-primary" type="submit" data-submitting-text="Menyimpan seluruh transaksi...">Simpan seluruh penyerahan</button>
            </form>
        @else
            @foreach($loan->detail as $detail)
                <div class="border rounded p-3 mb-2"><strong>{{ $detail->alat->nama_alat }}</strong> · seluruh {{ $detail->jumlah }} unit telah diserahkan<br><small class="text-muted">Asset ID: {{ $detail->penyerahanDetail->pluck('asset_id')->filter()->join(', ') }}</small></div>
            @endforeach
        @endif
    </section>
@empty
    <section class="surface p-5 text-center text-muted">{{ request('q') ? 'Transaksi aktif tidak ditemukan untuk Kode Booking atau NIS tersebut.' : 'Belum ada transaksi disetujui. Cari menggunakan Kode Booking atau NIS siswa.' }}</section>
@endforelse
<div class="mt-3">{{ $loans->links() }}</div>
@endsection
