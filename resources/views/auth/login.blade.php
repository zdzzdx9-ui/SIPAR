@extends('layouts.auth')
@section('title', 'Masuk')
@section('content')
@php($selectedRole = old('role', 'siswa'))
<div class="auth-frame">
    <aside class="auth-aside auth-aside-login">
        <a class="brand auth-brand-hero" href="{{ route('login') }}"><img class="brand-logo" src="{{ asset('assets/logo.png') }}" alt="Logo SMK Negeri 5 Surakarta"><span>SIPAR</span></a>
        <div class="auth-school"><strong>SISTEM INFORMASI PEMINJAMAN ALAT RPL</strong></div>
        <div class="aside-copy auth-tagline"><p>Ajukan peminjaman, pantau verifikasi, dan kembalikan alat dalam satu sistem yang tertata.</p></div>
        <div class="aside-foot">SIPAR— Sistem Informasi Peminjaman Alat RPL · SMK Negeri 5 Surakarta</div>
    </aside>
    <main class="auth-main"><form class="auth-form" method="post" action="{{ route('login.store') }}">
        @csrf
        <div class="role-switch" aria-label="Pilih peran"><button class="{{ $selectedRole === 'siswa' ? 'active' : '' }}" type="button" data-role="siswa" aria-pressed="{{ $selectedRole === 'siswa' ? 'true' : 'false' }}"><i class="bi bi-person me-1"></i>Siswa</button><button class="{{ $selectedRole === 'teknisi' ? 'active' : '' }}" type="button" data-role="teknisi" aria-pressed="{{ $selectedRole === 'teknisi' ? 'true' : 'false' }}"><i class="bi bi-wrench-adjustable me-1"></i>Teknisi</button><button class="{{ $selectedRole === 'admin' ? 'active' : '' }}" type="button" data-role="admin" aria-pressed="{{ $selectedRole === 'admin' ? 'true' : 'false' }}"><i class="bi bi-shield-lock me-1"></i>Admin</button></div><input type="hidden" name="role" id="login-role" value="{{ $selectedRole }}">
        <h2>Masuk ke akun</h2><p>Pilih peran dan gunakan kredensial akun SIPAR Anda.</p>
        @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
        <label class="form-label" for="identity" id="identity-label">{{ in_array($selectedRole, ['admin', 'teknisi'], true) ? 'Username' : 'NIS' }}</label><input class="form-control mb-3" id="identity" name="identity" value="{{ old('identity') }}" placeholder="{{ $selectedRole === 'admin' ? 'Masukkan username admin' : ($selectedRole === 'teknisi' ? 'Masukkan username teknisi' : 'Masukkan NIS terdaftar') }}" autocomplete="username" required autofocus>
        <label class="form-label" for="password">Password</label><div class="password-field mb-2"><input class="form-control pe-5" id="password" name="password" type="password" placeholder="Masukkan password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div>
        <div class="login-options d-flex justify-content-between align-items-center mb-4"><label class="form-check-label text-muted login-remember"><input class="form-check-input" name="remember" type="checkbox" value="1">Ingat saya</label><span class="text-muted whitespace-nowrap" id="login-support">{{ $selectedRole === 'siswa' ? 'Lupa password? Hubungi Admin SIPAR.' : '' }}</span></div>
        <button class="btn btn-primary w-100" type="submit">Masuk ke SIPAR <i class="bi bi-arrow-right ms-1"></i></button>
        <div class="student-registration-slot"><div class="help-note" id="student-registration" @if($selectedRole !== 'siswa') hidden @endif>Akun baru? <a href="{{ route('register') }}">Daftar sebagai siswa</a></div></div>
    </form></main>
</div>
<script>
const roleInput=document.getElementById('login-role');
const identityLabel=document.getElementById('identity-label');
const identityInput=document.getElementById('identity');
const supportText=document.getElementById('login-support');
const studentRegistration=document.getElementById('student-registration');
function selectLoginRole(role){
    const isStudent=role==='siswa';
    const isAdmin=role==='admin';
    const isTeknisi=role==='teknisi';
    roleInput.value=role;
    identityLabel.textContent=isStudent?'NIS':'Username';
    identityInput.placeholder=isAdmin?'Masukkan username admin':isTeknisi?'Masukkan username teknisi':'Masukkan NIS terdaftar';
    supportText.textContent=isStudent?'Lupa password? Hubungi Admin SIPAR.':'';
    studentRegistration.hidden=!isStudent;
    document.querySelectorAll('.role-switch button').forEach(button=>{
        const selected=button.dataset.role===role;
        button.classList.toggle('active',selected);
        button.setAttribute('aria-pressed',String(selected));
    });
}
document.querySelectorAll('.role-switch button').forEach(button=>button.addEventListener('click',()=>selectLoginRole(button.dataset.role)));
selectLoginRole(roleInput.value);
</script>
@endsection
