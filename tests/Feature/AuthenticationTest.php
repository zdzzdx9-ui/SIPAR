<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\PhotoCompressor;
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
            ->assertSee('Masukkan NIS terdaftar')
            ->assertSee('Lupa password? Hubungi Admin SIPAR.')
            ->assertSee('login-options d-flex justify-content-between align-items-center', false)
            ->assertSee('text-muted whitespace-nowrap', false)
            ->assertSeeInOrder(['class="role-switch"', '<h2>Masuk ke akun</h2>'], false)
            ->assertSee('Daftar sebagai siswa')
            ->assertSee('SMK Negeri 5 Surakarta')
            ->assertSee('class="auth-main"', false)
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
            ->assertSee('auth-global-footer', false)
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
            ->assertSee('Username atau kata sandi tidak cocok.')
            ->assertSee('id="login-role" value="admin"', false)
            ->assertSee('id="identity-label">Username</label>', false)
            ->assertDontSee('Kendala akses? Hubungi pengelola SIPAR.')
            ->assertSee('id="login-support"></span>', false);
    }

    public function test_student_login_requires_a_registered_student_nis(): void
    {
        User::factory()->create([
            'nis_nip' => '20260010',
            'email' => 'student@example.test',
            'role' => 'siswa',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'identity' => '20269999',
            'password' => 'password',
            'role' => 'siswa',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('identity');

        $this->get(route('login'))
            ->assertSee('NIS tidak valid atau belum terdaftar.');

        $this->from(route('login'))->post(route('login.store'), [
            'identity' => 'student@example.test',
            'password' => 'password',
            'role' => 'siswa',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('identity');

        $this->post(route('login.store'), [
            'identity' => '20260010',
            'password' => 'password',
            'role' => 'siswa',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_profile_username_label_matches_the_active_role(): void
    {
        foreach ([
            ['role' => 'siswa', 'label' => 'Username (NIS)'],
            ['role' => 'teknisi', 'label' => 'Username (NIP / ID Staf)'],
            ['role' => 'admin', 'label' => 'Username (NIP / ID Staf)'],
        ] as $account) {
            $user = User::factory()->create(['role' => $account['role']]);

            $this->actingAs($user)->get(route('profile'))
                ->assertOk()
                ->assertSee($account['label']);
        }
    }

    public function test_profile_link_is_in_the_sidebar_footer_and_keeps_its_active_state(): void
    {
        $user = User::factory()->create(['nama' => 'RIJAL', 'role' => 'siswa']);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSeeInOrder([
                '<div class="sidebar-footer">',
                'class="nav-link active" href="'.route('profile').'"><i class="bi bi-person-gear"></i>Profil Saya</a>',
                '<i class="bi bi-box-arrow-right"></i>Keluar',
            ], false)
            ->assertSee('.sidebar-logout:hover{background:#71343b40;color:#ffc0c4}', false);
    }

    public function test_topbar_profile_links_to_profile_and_logout_requires_confirmation(): void
    {
        $user = User::factory()->create(['nama' => 'Viewport User', 'role' => 'siswa']);

        $response = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<a class="topbar-profile" href="'.route('profile').'" aria-label="Buka profil saya">', false)
            ->assertSee('class="topbar-avatar" aria-hidden="true"><span class="topbar-avatar-initial" data-auth-user-initial>V</span>', false)
            ->assertSeeInOrder(['class="topbar-avatar" aria-hidden="true"', 'class="topbar-name"'], false)
            ->assertSee('data-confirm-message="Apakah Anda yakin ingin keluar dari akun?"', false)
            ->assertSee('data-confirm-label="Ya, Keluar"', false)
            ->assertSee('id="global-action-confirmation-submit"', false)
            ->assertSee('Batal');

        $this->assertSame(1, substr_count($response->getContent(), 'class="topbar-profile"'));
        $this->assertSame(0, substr_count($response->getContent(), '<div class="side-user">'));
        $this->assertStringNotContainsString('mobile-drawer .side-user', $response->getContent());
    }

    public function test_profile_update_returns_json_for_async_saves(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'nama' => 'Admin SIPAR']);

        $this->actingAs($user)->putJson(route('profile.update'), [
            'nama' => 'Admin Diperbarui',
            'email' => 'admin.updated@example.test',
        ])->assertOk()
            ->assertJson([
                'message' => 'Profil berhasil diperbarui.',
                'nama' => 'Admin Diperbarui',
                'avatar_url' => null,
            ]);

        $this->assertDatabaseHas('users', [
            'id_user' => $user->id_user,
            'nama' => 'Admin Diperbarui',
            'email' => 'admin.updated@example.test',
        ]);
    }

    public function test_profile_avatar_is_uploaded_and_replaced_without_a_database_column(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        $avatarNames = ['avatar-first.png', 'avatar-second.png'];
        $compressor = \Mockery::mock(PhotoCompressor::class);
        $compressor->shouldReceive('store')->twice()->andReturnUsing(function ($photo, $directory) use (&$avatarNames) {
            $path = $directory.'/'.array_shift($avatarNames);
            Storage::disk('public')->put($path, 'compressed-image');

            return $path;
        });
        $this->app->instance(PhotoCompressor::class, $compressor);
        $makeAvatar = fn () => \Illuminate\Http\Testing\File::createWithContent(
            'avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')
        );

        $firstResponse = $this->actingAs($user)->post(route('profile.update'), [
            '_method' => 'PUT',
            'nama' => $user->nama,
            'email' => $user->email,
            'avatar' => $makeAvatar(),
        ], ['Accept' => 'application/json'])->assertOk();
        $firstAvatarUrl = $firstResponse->json('avatar_url');
        $firstAvatarPath = str_replace(asset('storage').'/', '', $firstAvatarUrl);
        Storage::disk('public')->assertExists($firstAvatarPath);

        $secondResponse = $this->post(route('profile.update'), [
            '_method' => 'PUT',
            'nama' => $user->nama,
            'email' => $user->email,
            'avatar' => $makeAvatar(),
        ], ['Accept' => 'application/json'])->assertOk();
        $secondAvatarPath = str_replace(asset('storage').'/', '', $secondResponse->json('avatar_url'));

        Storage::disk('public')->assertExists($secondAvatarPath);
        Storage::disk('public')->assertMissing($firstAvatarPath);
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee($secondAvatarPath)
            ->assertSee('.profile-avatar-image{background:#fff}', false)
            ->assertSee('.side-user-avatar{display:grid;width:34px;height:34px;flex:none;place-items:center;overflow:hidden;border-radius:50%;background:#fff', false)
            ->assertSee('.topbar-avatar{display:grid;width:34px;height:34px;flex:none;place-items:center;overflow:hidden;border:1px solid #dce8f3;border-radius:50%;background:#e8f2ff', false)
            ->assertSee('src="'.asset('storage/'.$secondAvatarPath).'" alt="" data-auth-user-avatar', false)
            ->assertSee('class="topbar-avatar" aria-hidden="true"><img src="'.asset('storage/'.$secondAvatarPath).'" alt="" data-auth-user-avatar', false)
            ->assertSee("document.querySelectorAll('[data-auth-user-avatar]')", false);

        $this->assertArrayNotHasKey('avatar', \Illuminate\Support\Facades\Schema::getColumnListing('users'));
    }

    public function test_profile_form_has_a_photo_picker_and_async_success_toast(): void
    {
        $user = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('id="profile-avatar"', false)
            ->assertSee('name="avatar"', false)
            ->assertSee('Ganti Foto')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('id="profile-save-toast"', false)
            ->assertSee("fetch(form.action", false)
            ->assertSee("document.querySelectorAll('[data-auth-user-avatar]')", false)
            ->assertSee("toast.show()", false);
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
        $this->assertSame(14, Alat::count());
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
            ->assertSee('Kata sandi dan konfirmasinya harus sama.');
    }
}
