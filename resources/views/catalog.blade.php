@extends('layouts.app')
@section('title', 'Katalog Alat')
@push('head')
<style>
    .card-katalog{transition:all 0.3s ease;box-shadow:0 4px 6px -1px rgba(15, 23, 42, 0.08),0 2px 4px -1px rgba(15, 23, 42, 0.04);background-color:#fff}
    .card-katalog:hover{transform:translateY(-4px);box-shadow:0 20px 25px -5px rgba(15, 23, 42, 0.12),0 10px 10px -5px rgba(15, 23, 42, 0.04);z-index:1}
    .card-katalog .overflow-hidden img{transition:transform .4s ease-in-out}
    .card-katalog:hover .overflow-hidden img{transform:scale(1.08)}
    .catalog-image-frame{display:flex;align-items:center;justify-content:center;aspect-ratio:16/10;width:100%;overflow:hidden;border-radius:8px;background:#f5f8fa}
    .catalog-image-frame>.equipment-photo{display:block;width:100%;height:100%;object-fit:contain;object-position:center;padding:8px}
    .catalog-image-frame>.equipment-photo-placeholder{min-height:100%}
    .catalog-thumb .equipment-photo{object-fit:contain}
    #catalog-search{position:relative;min-height:54px;padding:7px!important;border-radius:10px}
    #catalog-search .form-control{min-height:40px;padding-left:10px!important;padding-right:10px;border:0!important;background:transparent;font-size:13px}
    #catalog-search .form-control:focus{box-shadow:none}
    #catalog-search .btn{min-width:54px;height:40px;display:grid;place-items:center;border-radius:8px}
    .catalog-cart-dock{position:fixed;z-index:15;right:24px;bottom:20px;display:flex;align-items:center;gap:14px;max-width:calc(100vw - 32px);padding:10px 12px 10px 16px;border:1px solid #dce5ed;border-radius:12px;background:#fff;box-shadow:0 8px 28px #10233426}
    @media(prefers-reduced-motion:reduce){.card-katalog,.card-katalog .overflow-hidden img{transition:none}.card-katalog:hover,.card-katalog:hover .overflow-hidden img{transform:none}}
    @media(max-width:575.98px){.catalog-cart-dock{right:12px;bottom:12px;left:12px;justify-content:space-between}#catalog-search{min-height:50px}}
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Katalog Alat</h1><p>Pilih beberapa jenis alat, lalu ajukan semuanya dalam satu transaksi.</p></div></div>
<form method="get" class="surface p-2 mb-3 responsive-search" id="catalog-search" role="search"><input class="form-control border-0" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode alat..." aria-label="Cari nama atau kode alat" autocomplete="off"><button class="btn btn-primary" type="submit" aria-label="Cari alat"><i class="bi bi-search" aria-hidden="true"></i></button></form>
<div id="cart-dock" class="catalog-cart-dock d-none" role="status" aria-live="polite">
    <span><strong id="cart-count">0 jenis alat</strong><small id="cart-summary" class="d-block text-muted">Satu unit untuk setiap jenis alat</small></span>
    <button class="btn btn-primary" id="view-cart" type="button">Lengkapi Form Peminjaman</button>
</div>
<div class="row g-3">
    @forelse($equipment as $item)
    @php($activeStatus = $activeEquipmentStatuses->get($item->id_alat))
    @php($unavailable = $item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1 || $activeStatus)
    <div class="col-sm-6 col-xl-4">
        <article class="surface card-item card-katalog h-100 d-flex flex-column">
            <div class="catalog-image-frame overflow-hidden mb-3"><x-equipment-image :equipment="$item" alt="Foto {{ $item->nama_alat }}"></x-equipment-image></div>
            <div class="d-flex align-items-start gap-3"><span class="equipment-icon overflow-hidden catalog-thumb"><x-equipment-image :equipment="$item" alt="Foto {{ $item->nama_alat }}"></x-equipment-image></span><div class="flex-grow-1"><h2 class="panel-title">{{ $item->nama_alat }}</h2><div class="panel-subtitle">{{ $item->kode_alat }} · {{ $item->kategori->nama_kategori }}</div><p class="small text-muted mb-0 mt-2">{{ $item->deskripsi ?: 'Peralatan '.$item->kategori->nama_kategori.' untuk mendukung kegiatan praktik.' }}</p></div></div>
            <div class="d-flex justify-content-between align-items-center border-top mt-3 pt-3"><span class="text-muted">Stok <strong class="text-dark">{{ $item->stok_tersedia }}</strong></span><div class="d-flex align-items-center gap-2">
                @if($activeStatus)<span class="text-muted">{{ $activeStatus === 'Pending' ? 'Sudah diajukan' : 'Sedang dipinjam' }}</span>
                @elseif($item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1)<span class="text-muted">Tidak tersedia</span>@endif
                <button class="btn btn-primary btn-sm add-to-cart" type="button" data-id="{{ $item->id_alat }}" data-name="{{ $item->nama_alat }}" data-code="{{ $item->kode_alat }}" data-unavailable="{{ $unavailable ? '1' : '0' }}" data-unavailable-label="{{ $activeStatus ? 'Sudah diajukan' : 'Tidak tersedia' }}" @disabled($unavailable)>{{ $activeStatus ? 'Sudah diajukan' : ($unavailable ? 'Tidak tersedia' : 'Tambah ke keranjang') }}</button>
            </div></div>
        </article>
    </div>
    @empty
    <div class="col-12"><div class="surface p-5 text-center text-muted">Alat tidak ditemukan.</div></div>
    @endforelse
</div>
<section class="surface p-3 p-md-4 mt-4" id="loan-cart">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="panel-title">Keranjang peminjaman</h2>
            <p class="panel-subtitle mb-0">Pilih beberapa jenis alat; setiap jenis maksimal 1 unit per siswa. Maksimal durasi peminjaman 5 hari dan boleh dikembalikan lebih awal.</p>
        </div>
        <button class="btn btn-sm btn-outline-danger d-none" id="clear-cart" type="button">
            <i class="bi bi-trash me-1" aria-hidden="true"></i>Bersihkan Keranjang
        </button>
    </div>
    <div id="cart-empty" class="text-muted py-2">Keranjang masih kosong.</div>
    <form method="post" action="{{ route('loans.create') }}" id="loan-cart-form" class="d-none" data-disable-on-submit>
        @csrf
        <div id="cart-items" class="mb-3"></div>
        <div class="row g-3">
            <div class="col-12 col-sm-6"><label class="form-label" for="loan-date">Tanggal pinjam</label><input class="form-control" id="loan-date" type="date" name="tanggal_pinjam" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
            <div class="col-12 col-sm-6"><label class="form-label" for="return-date">Rencana kembali</label><input class="form-control" id="return-date" type="date" name="tanggal_kembali_rencana" min="{{ now()->toDateString() }}" max="{{ now()->addDays(5)->toDateString() }}" value="{{ now()->addDays(5)->toDateString() }}" required></div>
            <div class="col-12"><label class="form-label" for="loan-purpose">Keperluan</label><textarea class="form-control" id="loan-purpose" name="keperluan" rows="3" maxlength="2000" required placeholder="Jelaskan keperluan alat..."></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit" data-submitting-text="Mengajukan..."><i class="bi bi-send me-1"></i>Ajukan seluruh keranjang</button></div>
        </div>
    </form>
</section>
<div class="modal fade" id="loan-submit-confirmation" tabindex="-1" aria-labelledby="loan-submit-confirmation-title" aria-describedby="loan-submit-confirmation-description" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="loan-submit-confirmation-title">Konfirmasi pengajuan peminjaman</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p id="loan-submit-confirmation-description" class="mb-0">Pastikan daftar alat, tanggal peminjaman, dan keperluan sudah benar. Jika dilanjutkan, pengajuan akan dikirim untuk verifikasi dan statusnya dapat dipantau melalui Riwayat Peminjaman.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batalkan</button>
                <button type="button" class="btn btn-primary" id="confirm-loan-submission">Lanjutkan pengajuan</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const cart = new Map();
    const cartItems = document.getElementById('cart-items');
    const cartEmpty = document.getElementById('cart-empty');
    const cartForm = document.getElementById('loan-cart-form');
    const cartDock = document.getElementById('cart-dock');
    const cartCount = document.getElementById('cart-count');
    const cartSummary = document.getElementById('cart-summary');
    const cartSection = document.getElementById('loan-cart');
    const clearCartButton = document.getElementById('clear-cart');
    const confirmationElement = document.getElementById('loan-submit-confirmation');
    const confirmationModal = bootstrap.Modal.getOrCreateInstance(confirmationElement);
    const confirmSubmissionButton = document.getElementById('confirm-loan-submission');

    function renderCart() {
        cartItems.replaceChildren();
        [...cart.values()].forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 border-bottom py-2';
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
            remove.className = 'btn btn-light btn-sm text-danger';
            remove.type = 'button';
            remove.setAttribute('aria-label', `Hapus ${item.name} dari keranjang`);
            remove.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
            remove.addEventListener('click', () => {
                cart.delete(item.id);
                renderCart();
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
            cartItems.append(row);
        });

        const empty = cart.size === 0;
        cartEmpty.classList.toggle('d-none', !empty);
        cartForm.classList.toggle('d-none', empty);
        cartDock.classList.toggle('d-none', empty);
        clearCartButton.classList.toggle('d-none', empty);
        cartCount.textContent = `${cart.size} jenis alat`;
        cartSummary.textContent = `Total ${cart.size} unit · maksimal 1 per jenis`;
        document.querySelectorAll('.add-to-cart').forEach(button => {
            const selected = cart.has(Number(button.dataset.id));
            const unavailable = button.dataset.unavailable === '1';
            button.disabled = selected || unavailable;
            button.textContent = selected
                ? 'Sudah ditambahkan'
                : unavailable
                    ? button.dataset.unavailableLabel
                    : 'Tambah ke keranjang';
        });
    }

    document.querySelectorAll('.add-to-cart:not(:disabled)').forEach(button => button.addEventListener('click', () => {
        const id = Number(button.dataset.id);
        if (cart.has(id)) return;
        cart.set(id, {id, name: button.dataset.name, code: button.dataset.code});
        renderCart();
    }));

    document.getElementById('view-cart').addEventListener('click', () => {
        cartSection.scrollIntoView({behavior: 'smooth', block: 'start'});
        document.getElementById('loan-date').focus({preventScroll: true});
    });

    clearCartButton.addEventListener('click', () => {
        if (cart.size === 0 || !window.confirm('Bersihkan semua alat dari keranjang peminjaman?')) return;
        cart.clear();
        renderCart();
    });

    document.getElementById('loan-date').addEventListener('change', event => {
        const [year, month, day] = event.target.value.split('-').map(Number);
        const maxEnd = new Date(year, month - 1, day + 5);
        const minEnd = event.target.value;
        const endDate = document.getElementById('return-date');
        endDate.min = minEnd;
        endDate.max = `${maxEnd.getFullYear()}-${String(maxEnd.getMonth() + 1).padStart(2, '0')}-${String(maxEnd.getDate()).padStart(2, '0')}`;
        if (endDate.value < minEnd) endDate.value = minEnd;
        if (endDate.value > endDate.max) endDate.value = endDate.max;
    });
    cartForm.addEventListener('submit', event => {
        if (cart.size === 0) {
            event.preventDefault();
            return;
        }
        if (cartForm.dataset.confirmed !== 'true') {
            event.preventDefault();
            confirmationModal.show();
            return;
        }
        delete cartForm.dataset.confirmed;
    });
    confirmSubmissionButton.addEventListener('click', () => {
        cartForm.dataset.confirmed = 'true';
        confirmationModal.hide();
        cartForm.requestSubmit(cartForm.querySelector('button[type="submit"]'));
    });
    let catalogSearchTimer;
    document.querySelector('#catalog-search input[name="q"]').addEventListener('input', () => {
        clearTimeout(catalogSearchTimer);
        catalogSearchTimer = setTimeout(() => document.getElementById('catalog-search').requestSubmit(), 350);
    });
})();
</script>
@endpush
