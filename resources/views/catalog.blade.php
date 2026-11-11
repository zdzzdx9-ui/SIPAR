@extends('layouts.app')
@section('title', 'Katalog Alat')
@push('head')
<style>
    .card-katalog{transition:transform .3s ease-in-out,box-shadow .3s ease-in-out,border-color .3s ease-in-out;box-shadow:0 4px 6px -1px rgba(15, 23, 42, 0.08),0 2px 4px -1px rgba(15, 23, 42, 0.04);background-color:#fff}
    .card-katalog:hover{transform:translateY(-4px);box-shadow:0 20px 25px -5px rgba(15, 23, 42, 0.12),0 10px 10px -5px rgba(15, 23, 42, 0.04);z-index:1}
    .catalog-image-frame{display:flex;align-items:center;justify-content:center;height:180px;width:100%;overflow:hidden;border-radius:8px;background:#f8fafc}
    .catalog-image-frame img{transition:transform .3s ease-in-out}
    .card-katalog:hover .catalog-image-frame img{transform:scale(1.05)}
    .catalog-image-frame>.equipment-photo{display:block;width:100%;height:100%;object-fit:contain;object-position:center;padding:12px}
    .catalog-image-frame>.equipment-photo-placeholder{min-height:100%}
    .catalog-thumb .equipment-photo{object-fit:contain}
    .catalog-card-footer{flex-wrap:wrap;gap:.5rem}
    .catalog-card-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:.5rem;min-width:0}
    .catalog-card-actions .btn{white-space:normal}
    .loan-list-dock{position:fixed;z-index:15;right:auto;bottom:max(1rem,env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem;width:max-content;max-width:calc(100vw - 2rem);padding:10px 12px 10px 16px;border:1px solid #dce5ed;border-radius:12px;background:#fff;box-shadow:0 8px 28px #10233426}
    .loan-list-panel{scroll-margin-top:80px}
    .catalog-shell .topbar{height:auto;min-height:62px;flex-wrap:wrap;gap:.75rem;padding-block:.5rem}
    .catalog-shell .topbar-search{width:auto;min-width:0;max-width:430px;flex:1 1 18rem}
    .catalog-shell .responsive-search .form-control{flex:1 1 0;min-width:0}
    .catalog-shell .responsive-search .btn{flex:0 0 auto;white-space:nowrap}
    @media(max-width:991.98px){.catalog-shell .topbar-search{order:3;max-width:none;flex-basis:100%}}
    @media(prefers-reduced-motion:reduce){.card-katalog,.catalog-image-frame img{transition:none}.card-katalog:hover,.card-katalog:hover .catalog-image-frame img{transform:none}}
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Katalog Alat</h1><p>Pilih beberapa jenis alat, lalu ajukan semuanya dalam satu transaksi.</p></div></div>
<div id="loan-empty-warning" class="alert alert-warning d-none" role="alert">Belum ada alat yang dipilih untuk dipinjam.</div>
<div id="loan-list-dock" class="loan-list-dock d-none" role="status" aria-live="polite">
    <span><strong id="loan-list-count">0 jenis alat dipilih</strong><small id="loan-list-summary" class="d-block text-muted">Maksimal satu unit untuk setiap jenis alat</small></span>
    <button class="btn btn-primary" id="view-loan-list" type="button" aria-haspopup="dialog" aria-controls="loan-request-modal">Pinjam</button>
</div>
<div class="row g-3">
    @forelse($equipment as $item)
    @php($activeStatus = $activeEquipmentStatuses->get($item->id_alat))
    @php($unavailable = $item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1 || $activeStatus)
    <div class="col-sm-6 col-xl-4">
        <article class="surface card-item card-katalog h-100 d-flex flex-column">
            <div class="catalog-image-frame overflow-hidden mb-3"><x-equipment-image :equipment="$item" alt="Foto {{ $item->nama_alat }}"></x-equipment-image></div>
            <div class="d-flex align-items-start gap-3"><span class="equipment-icon overflow-hidden catalog-thumb"><x-equipment-image :equipment="$item" alt="Foto {{ $item->nama_alat }}"></x-equipment-image></span><div class="flex-grow-1"><h2 class="panel-title">{{ $item->nama_alat }}</h2><div class="panel-subtitle">{{ $item->kode_alat }} · {{ $item->kategori->nama_kategori }}</div><p class="small text-muted mb-0 mt-2">{{ $item->deskripsi ?: 'Peralatan '.$item->kategori->nama_kategori.' untuk mendukung kegiatan praktik.' }}</p></div></div>
            <div class="catalog-card-footer d-flex justify-content-between align-items-center border-top mt-3 pt-3"><span class="text-muted">Stok <strong class="text-dark">{{ $item->stok_tersedia }}</strong></span><div class="catalog-card-actions">
                @if($activeStatus)<span class="text-muted">{{ $activeStatus === 'Pending' ? 'Sudah diajukan' : 'Sedang dipinjam' }}</span>
                @elseif($item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1)<span class="text-muted">Tidak tersedia</span>@endif
                <button class="btn btn-primary btn-sm loan-item-toggle" type="button" data-id="{{ $item->id_alat }}" data-name="{{ $item->nama_alat }}" data-code="{{ $item->kode_alat }}" data-unavailable="{{ $unavailable ? '1' : '0' }}" data-unavailable-label="{{ $activeStatus ? 'Sudah diajukan' : 'Tidak tersedia' }}" @disabled($unavailable) aria-pressed="false">{{ $activeStatus ? 'Sudah diajukan' : ($unavailable ? 'Tidak tersedia' : 'Pilih Alat') }}</button>
            </div></div>
        </article>
    </div>
    @empty
    <div class="col-12"><div class="surface p-5 text-center text-muted">Alat tidak ditemukan.</div></div>
    @endforelse
</div>
<div class="modal fade" id="loan-request-modal" tabindex="-1" aria-labelledby="loan-request-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post" action="{{ route('loans.create') }}" id="loan-request-form" data-disable-on-submit data-confirm-title="Konfirmasi pengajuan peminjaman">
            @csrf
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5 fw-bold" id="loan-request-modal-title">Form Peminjaman</h2>
                    <p class="text-muted mb-0">Pilih beberapa jenis alat; maksimal satu unit per jenis dan durasi peminjaman lima hari.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <h3 class="panel-title mb-2">Daftar alat yang dipinjam</h3>
                <div id="loan-list-items" class="mb-3"></div>
                <div class="row g-3">
                    <div class="col-12 col-sm-6"><label class="form-label" for="loan-date">Tanggal pinjam</label><input class="form-control" id="loan-date" type="date" name="tanggal_pinjam" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-12 col-sm-6"><label class="form-label" for="return-date">Rencana kembali</label><input class="form-control" id="return-date" type="date" name="tanggal_kembali_rencana" min="{{ now()->toDateString() }}" max="{{ now()->addDays(5)->toDateString() }}" value="{{ now()->addDays(5)->toDateString() }}" required></div>
                    <div class="col-12"><label class="form-label" for="loan-purpose">Keperluan</label><textarea class="form-control" id="loan-purpose" name="keperluan" rows="3" maxlength="2000" required placeholder="Jelaskan keperluan alat..."></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" id="cancel-loan-request" type="button">Batal</button>
                <button class="btn btn-primary" id="submit-loan-request" type="submit" data-submitting-text="Mengajukan..."><i class="bi bi-send me-1"></i>Ajukan Peminjaman</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const selectedItems = new Map();
    const loanListItems = document.getElementById('loan-list-items');
    const loanForm = document.getElementById('loan-request-form');
    const loanListDock = document.getElementById('loan-list-dock');
    const loanListCount = document.getElementById('loan-list-count');
    const loanListSummary = document.getElementById('loan-list-summary');
    const loanEmptyWarning = document.getElementById('loan-empty-warning');
    const loanModal = document.getElementById('loan-request-modal');
    const viewLoanList = document.getElementById('view-loan-list');
    const cancelLoanRequest = document.getElementById('cancel-loan-request');
    const submitLoanRequest = document.getElementById('submit-loan-request');
    const loanConfirmationMessage = 'Pastikan daftar alat, tanggal peminjaman, dan keperluan sudah benar. Apakah Anda yakin ingin mengajukan peminjaman?';

    function showEmptyWarning() {
        loanEmptyWarning.classList.remove('d-none');
    }

    function renderLoanList(warnWhenEmpty = false) {
        loanListItems.replaceChildren();
        [...selectedItems.values()].forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'd-flex flex-wrap align-items-center gap-2 border-bottom py-2';
            const label = document.createElement('div');
            label.className = 'flex-grow-1';
            const name = document.createElement('strong');
            name.textContent = item.name;
            const code = document.createElement('small');
            code.className = 'd-block text-muted';
            code.textContent = item.code;
            label.append(name, code);

            const quantityLabel = document.createElement('span');
            quantityLabel.className = 'badge text-bg-light';
            quantityLabel.textContent = '1 unit';
            const remove = document.createElement('button');
            remove.className = 'btn btn-outline-secondary btn-sm';
            remove.type = 'button';
            remove.textContent = 'Batalkan Pilihan';
            remove.setAttribute('aria-label', `Batalkan pilihan ${item.name}`);
            remove.addEventListener('click', () => {
                selectedItems.delete(item.id);
                renderLoanList(true);
            });
            row.append(label, quantityLabel, remove);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `items[${index}][id_alat]`;
            idInput.value = item.id;
            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = `items[${index}][jumlah]`;
            quantityInput.value = '1';
            row.append(idInput, quantityInput);
            loanListItems.append(row);
        });

        const empty = selectedItems.size === 0;
        loanListDock.classList.toggle('d-none', empty);
        if (empty) {
            delete loanForm.dataset.confirmMessage;
            if (warnWhenEmpty) showEmptyWarning();
            bootstrap.Modal.getInstance(loanModal)?.hide();
        } else {
            loanForm.dataset.confirmMessage = loanConfirmationMessage;
            loanEmptyWarning.classList.add('d-none');
        }
        loanListCount.textContent = `${selectedItems.size} jenis alat dipilih`;
        loanListSummary.textContent = `Total ${selectedItems.size} unit · maksimal 1 per jenis`;
        document.querySelectorAll('.loan-item-toggle').forEach(button => {
            const selected = selectedItems.has(Number(button.dataset.id));
            const unavailable = button.dataset.unavailable === '1';
            button.disabled = unavailable;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('btn-primary', !selected);
            button.classList.toggle('btn-outline-secondary', selected);
            button.textContent = selected
                ? 'Batalkan Pilihan'
                : unavailable
                    ? button.dataset.unavailableLabel
                    : 'Pilih Alat';
        });
    }

    document.querySelectorAll('.loan-item-toggle:not(:disabled)').forEach(button => button.addEventListener('click', () => {
        const id = Number(button.dataset.id);
        const removingSelection = selectedItems.has(id);
        if (removingSelection) {
            selectedItems.delete(id);
        } else {
            selectedItems.set(id, {id, name: button.dataset.name, code: button.dataset.code});
        }
        renderLoanList(removingSelection);
    }));

    viewLoanList.addEventListener('click', () => {
        if (selectedItems.size === 0) {
            showEmptyWarning();
            bootstrap.Modal.getInstance(loanModal)?.hide();
            return;
        }

        bootstrap.Modal.getOrCreateInstance(loanModal).show();
    });

    cancelLoanRequest.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(loanModal).hide();
    });

    loanForm.addEventListener('invalid', () => {
        if (selectedItems.size === 0) showEmptyWarning();
    }, true);
    submitLoanRequest.addEventListener('click', event => {
        if (selectedItems.size === 0) {
            event.preventDefault();
            window.alert('Pilih minimal satu alat dari katalog terlebih dahulu!');
        }
    });
    loanForm.addEventListener('submit', event => {
        if (selectedItems.size === 0) {
            event.preventDefault();
            window.alert('Pilih minimal satu alat dari katalog terlebih dahulu!');
        }
    });

    document.getElementById('loan-date').addEventListener('change', event => {
        const [year, month, day] = event.target.value.split('-').map(Number);
        const maxEnd = new Date(year, month - 1, day + 5);
        const minEnd = event.target.value;
        const endDate = document.getElementById('return-date');
        endDate.min = minEnd;
        endDate.max = `${maxEnd.getFullYear()}-${String(maxEnd.getMonth() + 1).padStart(2, '0')}-${String(maxEnd.getDate()).padStart(2, '0')}`;
        endDate.value = endDate.max;
    });
    loanForm.addEventListener('submit', event => {
        if (selectedItems.size === 0) {
            event.preventDefault();
            showEmptyWarning();
        }
    });
    renderLoanList();
})();
</script>
@endpush
