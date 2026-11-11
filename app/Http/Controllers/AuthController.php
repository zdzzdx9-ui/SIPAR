<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\PhotoCompressor;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $identityRules = ['required', 'string'];
        if ($request->input('role') === 'siswa') {
            $identityRules[] = Rule::exists('users', 'nis_nip')->where('role', 'siswa');
        }

        $credentials = $request->validate([
            'identity' => $identityRules,
            'password' => ['required', 'string'],
            'role' => ['required', Rule::in(['admin', 'teknisi', 'siswa'])],
        ], [
            'identity.required' => $request->input('role') === 'siswa'
                ? 'NIS wajib diisi.'
                : 'Username wajib diisi.',
            'identity.exists' => 'NIS tidak valid atau belum terdaftar.',
        ]);

        $column = $credentials['role'] === 'siswa'
            ? 'nis_nip'
            : (filter_var($credentials['identity'], FILTER_VALIDATE_EMAIL) ? 'email' : 'nis_nip');
        $remember = $request->boolean('remember');

        if (! Auth::attempt([$column => $credentials['identity'], 'password' => $credentials['password']], $remember)) {
            $message = in_array($credentials['role'], ['admin', 'teknisi'], true)
                ? 'Username atau kata sandi tidak cocok.'
                : 'NIS atau kata sandi tidak cocok.';

            return back()->withErrors(['identity' => $message])->onlyInput('identity', 'role');
        }

        if (Auth::user()->role !== $credentials['role']) {
            Auth::logout();

            return back()->withErrors(['identity' => 'Akun ini tidak memiliki akses untuk peran yang dipilih.'])->onlyInput('identity');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'nis_nip' => ['required', 'string', 'max:30', 'unique:users,nis_nip'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.max' => 'Nama lengkap maksimal 120 karakter.',
            'nis_nip.required' => 'NIS wajib diisi.',
            'nis_nip.max' => 'NIS maksimal 30 karakter.',
            'nis_nip.unique' => 'NIS tersebut sudah terdaftar. Gunakan NIS lain atau masuk dengan akun yang sudah ada.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum benar.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email tersebut sudah terdaftar. Gunakan email lain atau masuk dengan akun yang sudah ada.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Kata sandi dan konfirmasinya harus sama.',
        ]);
        $data['role'] = 'siswa';

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun siswa berhasil dibuat.');
    }

    public function profile(Request $request): View
    {
        $avatarDirectory = 'profile-avatars/'.$request->user()->id_user;
        $avatarPath = collect(Storage::disk('public')->files($avatarDirectory))
            ->sortByDesc(fn (string $path): int => Storage::disk('public')->lastModified($path))
            ->first();

        return view('profile', compact('avatarPath'));
    }

    public function updateProfile(Request $request, PhotoCompressor $photoCompressor): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $rules = [
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id_user.',id_user'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
        if ($user->role !== 'siswa') {
            $rules['nama'] = ['required', 'string', 'max:120'];
        }

        $data = $request->validate($rules);
        $avatar = $data['avatar'] ?? null;
        unset($data['avatar']);
        $user->update($data);

        $avatarPath = null;
        if ($avatar) {
            $avatarDirectory = 'profile-avatars/'.$user->id_user;
            $previousAvatars = Storage::disk('public')->files($avatarDirectory);
            $avatarPath = $photoCompressor->store($avatar, $avatarDirectory, 'avatar');

            foreach ($previousAvatars as $previousAvatar) {
                if ($previousAvatar !== $avatarPath) {
                    Storage::disk('public')->delete($previousAvatar);
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Profil berhasil diperbarui.',
                'nama' => $user->nama,
                'avatar_url' => $avatarPath ? asset('storage/'.$avatarPath) : null,
            ]);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Kata sandi berhasil diubah.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
