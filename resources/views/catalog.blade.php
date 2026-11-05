@extends('layouts.app')
@section('title', 'Katalog Alat')
@section('content')
<div class="page-head"><div><h1>Katalog Alat</h1><p>Pilih beberapa jenis alat, lalu ajukan semuanya dalam satu transaksi.</p></div></div>
<form method="get" class="surface p-2 mb-3 d-flex gap-2" id="catalog-search"><input class="form-control border-0" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode alat..." autocomplete="off"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></form>
<div class="row g-3">
    @forelse($equipment as $item)
    <div class="col-sm-6 col-xl-4">
        <article class="surface card-item h-100 d-flex flex-column">
            <x-equipment-image :equipment="$item" class="w-100 rounded mb-3" style="height:150px;object-fit:cover" alt="Foto {{ $item->nama_alat }}"></x-equipment-image>
            <div class="d-flex align-items-start gap-3"><span class="equipment-icon overflow-hidden"><x-equipment-image :equipment="$item" alt="Foto {{ $item->nama_alat }}"></x-equipment-image></span><div class="flex-grow-1"><h2 class="panel-title">{{ $item->nama_alat }}</h2><div class="panel-subtitle">{{ $item->kode_alat }} · {{ $item->kategori->nama_kategori }}</div></div></div>
            <div class="d-flex justify-content-between align-items-center border-top mt-3 pt-3"><span class="text-muted">Stok <strong class="text-dark">{{ $item->stok_tersedia }}</strong></span><div class="d-flex align-items-center gap-2"><input class="form-control form-control-sm cart-quantity" type="number" min="1" max="{{ $item->stok_tersedia }}" value="1" style="width:76px" aria-label="Jumlah {{ $item->nama_alat }}" @disabled($item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1)><button class="btn btn-primary btn-sm add-to-cart" type="button" data-id="{{ $item->id_alat }}" data-name="{{ $item->nama_alat }}" data-code="{{ $item->kode_alat }}" data-stock="{{ $item->stok_tersedia }}" @disabled($item->status_alat !== 'Tersedia' || $item->stok_tersedia < 1)>Tambah</button></div></div>
        </article>
    </div>
    @empty
    <div class="col-12"><div class="surface p-5 text-center text-muted">Alat tidak ditemukan.</div></div>
    @endforelse
</div>
<div class="mt-3">{{ $equipment->links() }}</div>
<section class="surface p-3 p-md-4 mt-4">
    <h2 class="panel-title">Keranjang peminjaman</h2>
    <p class="panel-subtitle">Maksimal durasi peminjaman 5 hari. Pengembalian lebih awal diperbolehkan.</p>
    <div id="cart-empty" class="text-muted py-2">Keranjang masih kosong.</div>
    <form method="post" action="{{ route('loans.create') }}" id="loan-cart-form" class="d-none">
        @csrf
        <div id="cart-items" class="mb-3"></div>
        <div class="row g-3">
            <div class="col-12 col-sm-6"><label class="form-label" for="loan-date">Tanggal pinjam</label><input class="form-control" id="loan-date" type="date" name="tanggal_pinjam" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
            <div class="col-12 col-sm-6"><label class="form-label" for="return-date">Rencana kembali</label><input class="form-control" id="return-date" type="date" name="tanggal_kembali_rencana" min="{{ now()->toDateString() }}" max="{{ now()->addDays(5)->toDateString() }}" value="{{ now()->addDays(5)->toDateString() }}" required></div>
            <div class="col-12"><label class="form-label" for="loan-purpose">Keperluan</label><textarea class="form-control" id="loan-purpose" name="keperluan" rows="3" maxlength="2000" required placeholder="Jelaskan keperluan alat..."></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Ajukan seluruh keranjang</button></div>
        </div>
    </form>
</section>
@endsection
@push('scripts')
<script>
const cart = new Map();
const cartItems = document.getElementById('cart-items');
const cartEmpty = document.getElementById('cart-empty');
const cartForm = document.getElementById('loan-cart-form');
function renderCart() {
    cartItems.replaceChildren();
    cart.forEach(item => {
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
        const quantity = document.createElement('input');
        quantity.className = 'form-control form-control-sm';
        quantity.type = 'number';
        quantity.min = '1';
        quantity.max = item.stock;
        quantity.value = item.quantity;
        quantity.setAttribute('aria-label', `Jumlah ${item.name}`);
        quantity.style.width = '82px';
        const remove = document.createElement('button');
        remove.className = 'btn btn-light btn-sm text-danger';
        remove.type = 'button';
        remove.setAttribute('aria-label', `Hapus ${item.name}`);
        const icon = document.createElement('i');
        icon.className = 'bi bi-trash';
        remove.append(icon);
        row.append(label, quantity, remove);
        quantity.addEventListener('change', () => {
            item.quantity = Math.max(1, Math.min(Number(quantity.value) || 1, item.stock));
            renderCart();
        });
        remove.addEventListener('click', () => {
            cart.delete(item.id);
            renderCart();
        });
        const index = [...cart.keys()].indexOf(item.id);
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = `items[${index}][id_alat]`;
        idInput.value = item.id;
        const quantityInput = document.createElement('input');
        quantityInput.type = 'hidden';
        quantityInput.name = `items[${index}][jumlah]`;
        quantityInput.value = item.quantity;
        row.append(idInput, quantityInput);
        cartItems.append(row);
    });
    const empty = cart.size === 0;
    cartEmpty.classList.toggle('d-none', !empty);
    cartForm.classList.toggle('d-none', empty);
}
document.querySelectorAll('.add-to-cart').forEach(button => button.addEventListener('click', () => {
    const id = Number(button.dataset.id);
    const stock = Number(button.dataset.stock);
    const quantity = Number(button.closest('article').querySelector('.cart-quantity').value) || 1;
    const existing = cart.get(id);
    cart.set(id, {
        id,
        name: button.dataset.name,
        code: button.dataset.code,
        stock,
        quantity: Math.min(stock, (existing?.quantity || 0) + quantity),
    });
    renderCart();
}));
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
    if (cart.size === 0) event.preventDefault();
});
let catalogSearchTimer;
document.querySelector('#catalog-search input[name="q"]').addEventListener('input', () => {
    clearTimeout(catalogSearchTimer);
    catalogSearchTimer = setTimeout(() => document.getElementById('catalog-search').requestSubmit(), 350);
});
</script>
@endpush
