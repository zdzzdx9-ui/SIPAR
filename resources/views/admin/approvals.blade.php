@extends('layouts.app')
@section('title','Verifikasi Peminjaman')
@push('head')
<style>
    .approval-details{min-width:0}
    .approval-detail-block{min-width:0;overflow-wrap:anywhere}
    .approval-equipment-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:8px;min-width:0}
    .approval-equipment-item{display:flex;align-items:center;gap:9px;min-width:0;padding:8px;border:1px solid #e8edf1;border-radius:8px;background:#f8fafc}
    .approval-equipment-item strong{overflow-wrap:anywhere}
    .approval-reject-panel{width:100%;padding:14px;border:1px solid #e8edf1;border-radius:8px;background:#f8fafc}
    .approval-reject-panel[hidden]{display:none}
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Verifikasi Peminjaman</h1><p>Verifikasi siswa, kebutuhan, stok, dan jadwal. Setelah disetujui, tugas fisik masuk ke Teknisi.</p></div></div>
<div class="row g-3">
    @forelse($loans as $loan)
        <div class="col-12">
            <article class="surface p-3 p-md-4 approval-card">
                <div class="d-flex justify-content-between gap-3 approval-card-header">
                    <div class="d-flex gap-3 min-w-0">
                        <span class="equipment-icon flex-shrink-0"><i class="bi bi-clipboard-check"></i></span>
                        <div class="min-w-0">
                            <h2 class="panel-title">{{ $loan->detail->pluck('alat.nama_alat')->filter()->join(', ') }}</h2>
                            <div class="panel-subtitle">{{ $loan->booking_code }} · diajukan {{ $loan->tanggal_pengajuan->format('d M Y H:i') }}</div>
                        </div>
                    </div>
                    <span class="badge-status approval-status status-{{ strtolower($loan->status) }}">{{ $loan->status_label }}</span>
                </div>
                <div class="row g-3 mt-2 approval-details">
                    <div class="col-12 col-md-6 col-lg-3 approval-detail-block">
                        <span class="text-muted">Peminjam</span><br>
                        <strong>{{ $loan->user->nama }}</strong> · {{ $loan->user->nis_nip }}
                    </div>
                    <div class="col-12 col-md-6 col-lg-5 approval-detail-block">
                        <span class="text-muted">Alat dan jumlah</span>
                        <div class="approval-equipment-list mt-2">
                            @foreach($loan->detail as $detail)
                                <div class="approval-equipment-item">
                                    <span class="equipment-icon overflow-hidden flex-shrink-0"><x-equipment-image :equipment="$detail->alat" alt="Foto {{ $detail->nama_alat }}"></x-equipment-image></span>
                                    <strong>{{ $detail->nama_alat }} × {{ $detail->jumlah }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-6 col-lg-2 approval-detail-block"><span class="text-muted">Tanggal pinjam</span><br>{{ $loan->tanggal_pinjam->format('d M Y') }}</div>
                    <div class="col-6 col-lg-2 approval-detail-block"><span class="text-muted">Rencana kembali</span><br>{{ $loan->tanggal_kembali_rencana->format('d M Y') }}</div>
                </div>
                <div class="approval-purpose mt-3"><strong>Keperluan:</strong> {{ $loan->keperluan }}</div>
                @if($loan->operational_notes)<div class="alert alert-info py-2 mt-2 mb-0">{{ $loan->operational_notes }}</div>@endif
                @if($loan->status === 'Pending')
                    <div class="d-flex flex-wrap gap-2 mt-3 approval-actions">
                        <form method="post" action="{{ route('admin.approvals.approve', $loan) }}" data-disable-on-submit data-confirm-message="Apakah Anda yakin ingin menyetujui peminjaman {{ $loan->booking_code }}?" data-confirm-title="Konfirmasi persetujuan">
                            @csrf
                            <button class="btn btn-primary" type="submit" data-submitting-text="Menyetujui..."><i class="bi bi-check2 me-1"></i>Setujui pengajuan</button>
                        </form>
                        <button class="btn btn-light text-danger" type="button" data-reject-toggle aria-expanded="false" aria-controls="reject-panel-{{ $loan->id_peminjaman }}">Tolak</button>
                        <div class="approval-reject-panel" id="reject-panel-{{ $loan->id_peminjaman }}" hidden>
                            <form method="post" action="{{ route('admin.approvals.reject', $loan) }}" data-disable-on-submit data-confirm-message="Apakah Anda yakin ingin menolak peminjaman {{ $loan->booking_code }}?" data-confirm-title="Konfirmasi penolakan">
                                @csrf
                                <label class="form-label" for="rejection-reason-{{ $loan->id_peminjaman }}">Alasan penolakan</label>
                                <textarea class="form-control mb-2" id="rejection-reason-{{ $loan->id_peminjaman }}" name="catatan_admin" rows="2" maxlength="1000" required data-validation-message="Alasan penolakan wajib diisi dengan valid." placeholder="Tuliskan alasan penolakan"></textarea>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-light" type="button" data-reject-cancel>Batalkan</button>
                                    <button class="btn btn-danger" type="submit" data-submitting-text="Menolak...">Konfirmasi penolakan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </article>
        </div>
    @empty
        <div class="col-12"><div class="surface p-5 text-center text-muted">Tidak ada pengajuan yang menunggu atau sedang dipinjam.</div></div>
    @endforelse
</div>
<div class="mt-3">{{ $loans->links() }}</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-reject-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        const opening = panel.hidden;
        panel.hidden = !opening;
        button.setAttribute('aria-expanded', String(opening));
        if (opening) panel.querySelector('textarea').focus();
    });
});
document.querySelectorAll('[data-reject-cancel]').forEach(button => {
    button.addEventListener('click', () => {
        const panel = button.closest('.approval-reject-panel');
        panel.hidden = true;
        document.querySelector(`[aria-controls="${panel.id}"]`)?.setAttribute('aria-expanded', 'false');
    });
});
</script>
@endpush
