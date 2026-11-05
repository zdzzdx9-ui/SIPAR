<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        Alat::create([
            'kode_alat' => 'LAN-001', 'nama_alat' => 'LAN Tester', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'kondisi' => 'Baik', 'qr_code' => 'qr-admin-test', 'status_alat' => 'Tersedia',
        ]);

        $this->actingAs($admin);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false)
            ->assertSee('data-bs-target="#mobileNavigation"', false)
            ->assertSee('id="mobileNavigation"', false)
            ->assertSee(route('admin.categories'), false)
            ->assertSee(route('admin.students'), false)
            ->assertSee(route('admin.reports'), false)
            ->assertDontSee('Verifikasi Pengembalian');
        foreach ([
            route('dashboard'), route('admin.equipment'), route('admin.categories'), route('admin.students'),
            route('admin.approvals'),
            route('admin.reports'), route('admin.reports.xlsx'), route('profile'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('admin.students'))
            ->assertSee('id="new-student-password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('bi-eye', false);
        $this->get(route('profile'))
            ->assertSee('id="current-password"', false)
            ->assertSee('id="new-password-confirmation"', false)
            ->assertSee('data-password-toggle', false);
        $this->get('/admin/pengembalian/verifikasi')->assertNotFound();
        $this->get('/admin/pengembalian')->assertNotFound();
        $this->get('/admin/pengembalian/cari')->assertNotFound();
    }

    public function test_student_pages_render(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        Alat::create([
            'kode_alat' => 'LAN-002', 'nama_alat' => 'LAN Tester', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'kondisi' => 'Baik', 'qr_code' => 'qr-student-test', 'status_alat' => 'Tersedia',
        ]);

        $this->actingAs($student);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('search').'"', false)
            ->assertSee('data-bs-target="#mobileNavigation"', false)
            ->assertSee('id="mobileNavigation"', false)
            ->assertSee(route('catalog'), false)
            ->assertSee(route('loans.mine'), false)
            ->assertSee(route('profile'), false);
        foreach ([route('dashboard'), route('catalog'), route('loans.mine'), route('profile')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('profile'))
            ->assertSee('id="current-password"', false)
            ->assertSee('id="new-password"', false)
            ->assertSee('id="new-password-confirmation"', false)
            ->assertSee('data-password-toggle', false);
        $this->get(route('catalog'))
            ->assertSee('class="btn btn-primary btn-sm add-to-cart"', false)
            ->assertSee('id="loan-cart-form"', false)
            ->assertSee('data-disable-on-submit', false)
            ->assertSee('data-submitting-text="Mengajukan..."', false)
            ->assertSee('id="loan-submit-confirmation"', false)
            ->assertSee('Pastikan daftar alat, tanggal peminjaman, dan keperluan sudah benar.')
            ->assertSee('Batalkan')
            ->assertSee('Lanjutkan pengajuan')
            ->assertSee('name="tanggal_kembali_rencana"', false)
            ->assertSee('Keranjang peminjaman')
            ->assertSee('Ajukan seluruh keranjang');
    }

    public function test_student_with_loan_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Riwayat harus dipertahankan',
            'status' => 'Selesai',
        ]);

        $this->actingAs($admin)->delete(route('admin.students.delete', $student))
            ->assertSessionHasErrors('siswa');

        $this->assertDatabaseHas('users', ['id_user' => $student->id_user]);
        $this->assertDatabaseHas('loans', ['id_peminjaman' => $loan->id_peminjaman]);
    }

    public function test_student_without_loan_history_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($admin)->delete(route('admin.students.delete', $student))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id_user' => $student->id_user]);
    }

    public function test_database_restricts_deleting_user_with_loan_history(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'FK protection',
            'status' => 'Selesai',
        ]);

        try {
            $student->delete();
            $this->fail('Deleting a student with loan history should be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id_user' => $student->id_user]);
        }
    }

    public function test_student_dashboard_summary_counts_all_their_loans(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        foreach (['Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'Selesai', 'Rejected'] as $status) {
            Peminjaman::create([
                'id_user' => $student->id_user,
                'tanggal_pinjam' => now()->toDateString(),
                'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
                'keperluan' => 'Dashboard summary test',
                'status' => $status,
            ]);
        }

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('myLoanCount', 8)
            ->assertViewHas('myPendingCount', 6)
            ->assertViewHas('myCompletedCount', 1);
    }

    public function test_technician_navigation_shows_handover_and_return_task_counts(): void
    {
        $technician = User::factory()->create(['role' => 'teknisi']);
        $student = User::factory()->create(['role' => 'siswa']);
        $category = KategoriAlat::create(['nama_kategori' => 'Operasional']);
        $equipment = Alat::create([
            'kode_alat' => 'TECH-BADGE', 'nama_alat' => 'Alat Counter', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 0, 'kondisi' => 'Baik', 'qr_code' => 'qr-tech-badge', 'status_alat' => 'Dipinjam',
        ]);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Badge counter',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);

        $this->actingAs($technician)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penyerahan Alat')
            ->assertSee('Pengembalian Alat')
            ->assertSee('text-bg-warning', false)
            ->assertSee('>1</span>', false);

        \App\Models\DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 1,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pengembalian Alat')
            ->assertSee('>1</span>', false);
    }

    public function test_profile_identity_is_locked_and_password_can_be_changed(): void
    {
        $student = User::factory()->create(['role' => 'siswa', 'nis_nip' => 'LOCKED-USER']);

        $this->actingAs($student)->get(route('profile'))
            ->assertOk()
            ->assertSee('Username (NIS / NIP)')
            ->assertSee('readonly', false);

        $this->put(route('profile.update'), [
            'nama' => 'Nama Baru',
            'email' => $student->email,
            'nis_nip' => 'CHANGED-USER',
        ])->assertRedirect();
        $this->assertSame('LOCKED-USER', $student->fresh()->nis_nip);

        $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('password-baru-123', $student->fresh()->password));
    }

    public function test_loan_status_labels_are_localized_without_changing_stored_values(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $statuses = ['Pending', 'Approved', 'Rejected'];

        foreach ($statuses as $status) {
            Peminjaman::create([
                'id_user' => $student->id_user,
                'tanggal_pinjam' => now()->toDateString(),
                'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
                'keperluan' => 'Status localization test',
                'status' => $status,
            ]);
        }

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak');
        $this->get(route('loans.mine'))
            ->assertOk()
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('Disetujui');
        $this->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('value="Pending"', false)
            ->assertSee('value="Approved"', false)
            ->assertSee('value="Rejected"', false)
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak');

        $this->assertSame($statuses, Peminjaman::orderBy('id_peminjaman')->pluck('status')->all());
    }

}
