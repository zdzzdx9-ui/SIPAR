@extends('layouts.app')
@section('title','Profil Saya')
@push('head')
<style>
    .profile-avatar{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem}
    .profile-avatar-image,.profile-avatar-placeholder{width:5rem;height:5rem;flex:none;border:1px solid var(--line);border-radius:50%;object-fit:cover}
    .profile-avatar-placeholder{display:grid;place-items:center;background:var(--blue-soft);color:var(--blue);font-size:2rem}
    .profile-avatar-image[hidden],.profile-avatar-placeholder[hidden]{display:none}
    .profile-toast-container{z-index:1085}
    .profile-save-toast{border:0;background:#168361;color:#fff}
</style>
@endpush
@section('content')
<div class="page-head"><div><h1>Profil Saya</h1><p>Perbarui informasi akun dan password.</p></div></div>
<div class="row g-3"><div class="col-lg-7"><section class="surface p-3 p-md-4"><h2 class="panel-title mb-3">Informasi Akun</h2>
    <div class="profile-avatar">
        @if($avatarPath)
            <img class="profile-avatar-image" id="profile-avatar-preview" src="{{ asset('storage/'.$avatarPath) }}" alt="Foto profil {{ auth()->user()->nama }}">
            <span class="profile-avatar-placeholder" id="profile-avatar-placeholder" aria-hidden="true" hidden><i class="bi bi-person-fill"></i></span>
        @else
            <img class="profile-avatar-image" id="profile-avatar-preview" alt="Pratinjau foto profil" hidden>
            <span class="profile-avatar-placeholder" id="profile-avatar-placeholder" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
        @endif
        <div><label class="btn btn-light mb-1" for="profile-avatar">Ganti Foto</label><input class="form-control visually-hidden" id="profile-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" form="profile-update-form"><div class="form-text">JPG, PNG, atau WEBP · Maksimal 5 MB</div></div>
    </div>
    <div class="alert alert-danger py-2" id="profile-save-error" role="alert" hidden></div>
    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="profile-update-form">@csrf @method('PUT')
        <label class="form-label" for="profile-name">Nama lengkap</label><input class="form-control mb-3" id="profile-name" name="nama" value="{{ old('nama',auth()->user()->nama) }}" required>
        <label class="form-label">{{ auth()->user()->role === 'siswa' ? 'Username (NIS)' : 'Username (NIP / ID Staf)' }}</label><input class="form-control mb-3" value="{{ auth()->user()->nis_nip }}" readonly aria-readonly="true"><div class="form-text mb-3">Username tidak dapat diubah.</div>
        <label class="form-label" for="profile-email">Email</label><input class="form-control mb-3" id="profile-email" type="email" name="email" value="{{ old('email',auth()->user()->email) }}" required>
        <button class="btn btn-primary" type="submit">Simpan profil</button>
    </form>
</section></div><div class="col-lg-5"><section class="surface p-3 p-md-4"><h2 class="panel-title mb-3">Ubah Password</h2><form method="post" action="{{ route('profile.password') }}">@csrf @method('PUT')<label class="form-label" for="current-password">Password saat ini</label><div class="password-field mb-3"><input class="form-control" id="current-password" type="password" name="current_password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div><label class="form-label" for="new-password">Password baru</label><div class="password-field mb-3"><input class="form-control" id="new-password" type="password" name="password" required minlength="8" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div><label class="form-label" for="new-password-confirmation">Ulangi password baru</label><div class="password-field mb-3"><input class="form-control" id="new-password-confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div><button class="btn btn-primary">Ubah password</button></form></section></div></div>
<div class="toast-container profile-toast-container position-fixed top-0 end-0 p-3"><div class="toast profile-save-toast" id="profile-save-toast" role="status" aria-live="polite" aria-atomic="true"><div class="toast-body" id="profile-save-toast-message"></div></div></div>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('profile-update-form');
    const avatarInput = document.getElementById('profile-avatar');
    const avatarPreview = document.getElementById('profile-avatar-preview');
    const avatarPlaceholder = document.getElementById('profile-avatar-placeholder');
    const errorMessage = document.getElementById('profile-save-error');
    const toast = bootstrap.Toast.getOrCreateInstance(document.getElementById('profile-save-toast'));
    const toastMessage = document.getElementById('profile-save-toast-message');
    let previewUrl = null;

    avatarInput.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        avatarPreview.src = previewUrl;
        avatarPreview.hidden = false;
        avatarPlaceholder.hidden = true;
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        errorMessage.hidden = true;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalLabel = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Menyimpan...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new Error('Sesi berakhir atau server tidak dapat menyimpan profil. Muat ulang halaman lalu coba kembali.');
            }
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = Object.values(result.errors || {}).flat()[0];
                throw new Error(validationMessage || result.message || 'Profil gagal disimpan. Silakan coba kembali.');
            }

            if (result.avatar_url) {
                avatarPreview.src = result.avatar_url;
                avatarPreview.hidden = false;
                avatarPlaceholder.hidden = true;
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            document.querySelectorAll('[data-auth-user-name]').forEach(element => {
                element.textContent = result.nama;
            });
            toastMessage.textContent = result.message;
            toast.show();
        } catch (error) {
            errorMessage.textContent = error.message || 'Profil gagal disimpan. Periksa koneksi lalu coba kembali.';
            errorMessage.hidden = false;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
        }
    });
})();
</script>
@endpush
