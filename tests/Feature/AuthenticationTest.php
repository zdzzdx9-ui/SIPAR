<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Alat;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sign_in_using_nis_and_reach_dashboard(): void
    {
        User::factory()->create([
            'nama' => 'Admin Toolman',
            'nis_nip' => 'ADMIN',
            'email' => 'admin@sipar.sch.id',
            'password' => 'ADMIN123',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), ['identity' => 'ADMIN', 'password' => 'ADMIN123', 'role' => 'admin'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Dashboard');
    }

    public function test_provisioned_technician_can_sign_in_with_default_credentials(): void
    {
        $this->post(route('login.store'), [
            'identity' => 'teknisi',
            'password' => 'TEKNISI123',
            'role' => 'teknisi',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_login_page_shows_student_registration_without_seed_instructions(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masukkan NIS atau email')
            ->assertSee('Lupa password? Hubungi Admin SIPAR.')
            ->assertSee('login-options d-flex justify-content-between align-items-center', false)
            ->assertSee('text-muted whitespace-nowrap', false)
            ->assertSee('Daftar sebagai siswa')
            ->assertSee('data-password-toggle', false)
            ->assertDontSee('Akun demo tersedia setelah menjalankan seeder.');
    }

    public function test_registration_page_has_password_visibility_toggles_for_both_password_fields(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('id="register-password"', false)
            ->assertSee('id="register-password-confirmation"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('bi-eye', false)
            ->assertSee('name="nis_nip"', false)
            ->assertSee('name="nis_nip" value="" autocomplete="username"', false)
            ->assertSee('name="email" type="email" value="" autocomplete="email"', false)
            ->assertSee('name="password" type="password" required minlength="8" autocomplete="new-password"', false)
            ->assertSee('name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"', false);
    }

    public function test_login_credentials_use_browser_autocomplete_tokens(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="identity"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('name="password" type="password"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_login_urls_follow_the_https_scheme_from_the_railway_proxy(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'sipar-production.up.railway.app',
            'X-Forwarded-Port' => '443',
        ])->get(route('login'))
            ->assertOk()
            ->assertSee('action="https://sipar-production.up.railway.app/login"', false)
            ->assertSee('href="https://sipar-production.up.railway.app/daftar"', false);
    }

    public function test_admin_login_error_uses_username_wording(): void
    {
        User::factory()->create(['role' => 'admin', 'nis_nip' => 'ADMIN-TEST']);

        $this->from(route('login'))->followingRedirects()->post(route('login.store'), [
            'identity' => 'ADMIN-TEST',
            'password' => 'wrong-password',
            'role' => 'admin',
        ])->assertOk()
            ->assertSee('Username atau password tidak cocok.')
            ->assertSee('id="login-role" value="admin"', false)
            ->assertSee('id="identity-label">Username</label>', false)
            ->assertDontSee('Kendala akses? Hubungi pengelola SIPAR.')
            ->assertSee('id="login-support"></span>', false);
    }

    public function test_seeder_does_not_overwrite_existing_admin_credentials(): void
    {
        User::factory()->create([
            'nama' => 'Admin Lama',
            'nis_nip' => 'ADMIN001',
            'email' => 'admin@sipar.sch.id',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@sipar.sch.id')->firstOrFail();
        $this->assertSame('ADMIN001', $admin->nis_nip);
        $this->assertTrue(Hash::check('password123', $admin->password));
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_seeder_creates_first_admin_with_configured_secret(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@sipar.sch.id')->firstOrFail();
        $this->assertSame('ADMIN', $admin->nis_nip);
        $this->assertTrue(Hash::check('test-admin-password', $admin->password));
    }

    public function test_seeder_replaces_unofficial_equipment_and_preserves_loan_history(): void
    {
        $category = KategoriAlat::firstOrCreate(['nama_kategori' => 'Perkakas']);
        $equipment = [];
        foreach (['Crimping Tool Set', 'Keyboard Mekanik'] as $name) {
            $equipment[$name] = Alat::create([
                'kode_alat' => 'DUMMY-'.str_replace(' ', '-', strtoupper($name)),
                'nama_alat' => $name,
                'id_kategori' => $category->id_kategori,
                'stok_total' => 1,
                'stok_tersedia' => 1,
                'kondisi' => 'Baik',
                'qr_code' => 'qr-'.str_replace(' ', '-', strtolower($name)),
                'status_alat' => 'Tersedia',
            ]);
        }
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Riwayat alat dummy lama',
            'status' => 'Selesai',
        ]);
        $loan->detail()->create(['id_alat' => $equipment['Crimping Tool Set']->id_alat, 'jumlah' => 1]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('alat', ['nama_alat' => 'Crimping Tool Set']);
        $this->assertDatabaseMissing('alat', ['nama_alat' => 'Keyboard Mekanik']);
        $this->assertDatabaseHas('loan_items', [
            'id_detail' => $loan->detail()->value('id_detail'),
            'id_alat' => null,
            'alat_snapshot_kode' => $equipment['Crimping Tool Set']->kode_alat,
            'alat_snapshot_nama' => 'Crimping Tool Set',
        ]);
        $this->assertSame(13, Alat::count());
    }

    public function test_login_rejects_a_role_that_does_not_match_the_account(): void
    {
        User::factory()->create(['role' => 'siswa', 'nis_nip' => '20260002']);

        $this->post(route('login.store'), ['identity' => '20260002', 'password' => 'password', 'role' => 'admin'])
            ->assertSessionHasErrors('identity');
    }

    public function test_registration_creates_student_and_not_admin(): void
    {
        $this->post(route('register.store'), [
            'nama' => 'Siswa Baru',
            'nis_nip' => '20260001',
            'email' => 'siswa.baru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['nis_nip' => '20260001', 'role' => 'siswa']);
    }

    public function test_registration_explains_when_nis_or_email_is_already_registered(): void
    {
        User::factory()->create([
            'nis_nip' => '20260003',
            'email' => 'already.used@example.test',
        ]);

        $this->from(route('register'))->post(route('register.store'), [
            'nama' => 'Siswa Duplikat',
            'nis_nip' => '20260003',
            'email' => 'already.used@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['nis_nip', 'email']);

        $this->followingRedirects()->get(route('register'))
            ->assertOk()
            ->assertSee('NIS tersebut sudah terdaftar.')
            ->assertSee('Email tersebut sudah terdaftar.');
    }

    public function test_registration_explains_invalid_email_and_mismatched_password(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'nama' => 'Siswa Data Tidak Valid',
            'nis_nip' => '20260004',
            'email' => 'bukan-email',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->followingRedirects()->get(route('register'))
            ->assertOk()
            ->assertSee('Format email belum benar.')
            ->assertSee('Password dan ulangi password harus sama.');
    }
}
