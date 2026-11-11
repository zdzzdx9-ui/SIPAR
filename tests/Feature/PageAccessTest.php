<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\DetailPenyerahan;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('<div class="surface stat">', false)
            ->assertSee('style="background-color: #dbeafe !important; color: #2563eb !important; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;"', false)
            ->assertSee('<i class="bi bi-box" style="font-size: 24px;"></i>', false)
            ->assertSee('style="background-color: #ede9fe !important; color: #7c3aed !important; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;"', false)
            ->assertSee('<i class="bi bi-arrow-left-right" style="font-size: 24px;"></i>', false)
            ->assertSee('style="background-color: #fef3c7 !important; color: #d97706 !important; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;"', false)
            ->assertSee('<i class="bi bi-hourglass-split" style="font-size: 24px;"></i>', false)
            ->assertSee('style="background-color: #d1fae5 !important; color: #059669 !important; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;"', false)
            ->assertSee('<i class="bi bi-check-circle" style="font-size: 24px;"></i>', false)
            ->assertSee('Jenis Alat')
            ->assertSee('Transaksi Terbaru')
            ->assertSee('Inventaris Teratas')
            ->assertSee('app-footer', false)
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
        $this->get(route('admin.categories'))
            ->assertSee('Kategori Alat')
            ->assertSee('Jaringan')
            ->assertSee('name="nama_kategori"', false);
        $this->get(route('admin.students'))
            ->assertSee('Data Siswa')
            ->assertSee('Tambah akun siswa')
            ->assertSee('id="new-student-password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('bi-eye', false);
        $this->get(route('admin.equipment'))
            ->assertSee('Master Data Alat')
            ->assertSee('LAN Tester')
            ->assertSee('title="Edit"', false)
            ->assertSee('name="stok_total"', false);
        $this->get(route('admin.reports'))
            ->assertSee('Dari tanggal')
            ->assertSee('Sampai tanggal')
            ->assertSee('Semua status')
            ->assertSee('Total Denda');
        $this->get(route('profile'))
            ->assertSee('id="current-password"', false)
            ->assertSee('id="new-password-confirmation"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('Username (NIP / ID Staf)');
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
            'deskripsi' => 'Alat pengujian kabel jaringan.',
        ]);

        $this->actingAs($student);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('class="surface stat"', false)
            ->assertSee('Alat Tersedia')
            ->assertSee('Peminjaman Terbaru')
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
            ->assertSee('data-password-toggle', false)
            ->assertSee('Username (NIS)');
        $this->get(route('catalog'))
            ->assertSee('Katalog Alat')
            ->assertSee('Alat pengujian kabel jaringan.')
            ->assertSee('card-katalog', false)
            ->assertSee('.card-katalog:hover{transform:translateY(-4px)', false)
            ->assertSee('.card-katalog:hover .catalog-image-frame img{transform:scale(1.05)}', false)
            ->assertSee('transition:transform .3s ease-in-out', false)
            ->assertSee('object-fit:contain;object-position:center', false)
            ->assertSee('transform:translateX(-50%)', false)
            ->assertSee('catalog-card-actions{display:flex', false)
            ->assertSee('catalog-shell .responsive-search .btn{flex:0 0 auto;white-space:nowrap}', false)
            ->assertSee('class="catalog-image-frame overflow-hidden mb-3"', false)
            ->assertSee('class="btn btn-primary btn-sm loan-item-toggle"', false)
            ->assertSee('Pilih Alat')
            ->assertSee('id="loan-request-form"', false)
            ->assertSee('data-disable-on-submit', false)
            ->assertSee('data-submitting-text="Mengajukan..."', false)
            ->assertSee('id="view-loan-list"', false)
            ->assertSee('id="loan-list-count"', false)
            ->assertSee('aria-controls="loan-request-modal"', false)
            ->assertSee('>Pinjam</button>', false)
            ->assertSee('id="loan-request-modal"', false)
            ->assertSee('id="cancel-loan-request"', false)
            ->assertSee('id="submit-loan-request"', false)
            ->assertSee('Pilih minimal satu alat dari katalog terlebih dahulu!')
            ->assertSee('cancelLoanRequest.addEventListener', false)
            ->assertSee('id="loan-list-items"', false)
            ->assertSee('id="loan-empty-warning"', false)
            ->assertSee('Belum ada alat yang dipilih untuk dipinjam.')
            ->assertSee('Form Peminjaman')
            ->assertSee('id="global-action-confirmation"', false)
            ->assertSee('min-height:48px', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee("formModal.addEventListener('hidden.bs.modal'", false)
            ->assertSee('bootstrap.Modal.getOrCreateInstance(formModal).hide()', false)
            ->assertSee("if (formToRestore?.id === 'loan-request-form')", false)
            ->assertSee('Pastikan daftar alat, tanggal peminjaman, dan keperluan sudah benar.')
            ->assertSee('loanForm.dataset.confirmMessage = loanConfirmationMessage', false)
            ->assertSee('Batalkan Pilihan')
            ->assertSee('name="tanggal_kembali_rencana"', false)
            ->assertSee('Ajukan Peminjaman')
            ->assertSee('Pilih Alat')
            ->assertDontSee('Keranjang')
            ->assertDontSee('keranjang')
            ->assertDontSee('Sebelumnya')
            ->assertDontSee('Berikutnya')
            ->assertSee('app-footer', false);
    }

    public function test_student_catalog_displays_all_equipment_without_pagination(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $category = KategoriAlat::create(['nama_kategori' => 'Katalog Satu Halaman']);
        foreach (range(1, 13) as $number) {
            Alat::create([
                'kode_alat' => sprintf('SINGLE-%02d', $number),
                'nama_alat' => sprintf('Alat Halaman Sama %02d', $number),
                'id_kategori' => $category->id_kategori,
                'stok_total' => 1,
                'stok_tersedia' => 1,
                'kondisi' => 'Baik',
                'qr_code' => sprintf('single-page-%02d', $number),
                'status_alat' => 'Tersedia',
            ]);
        }

        $response = $this->actingAs($student)->get(route('catalog'))->assertOk();
        foreach (range(1, 13) as $number) {
            $response->assertSee(sprintf('Alat Halaman Sama %02d', $number));
        }
        $response->assertDontSee('Sebelumnya')
            ->assertDontSee('Berikutnya')
            ->assertDontSee('page=2');
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

    public function test_student_loan_history_separates_notes_and_actions(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today()->toDateString(),
            'tanggal_kembali_rencana' => today()->addDay()->toDateString(),
            'keperluan' => 'Uji pemisahan catatan dan aksi',
            'status' => 'Pending',
        ]);
        $equipment = Alat::query()->create([
            'kode_alat' => 'HISTORY-ACTION', 'nama_alat' => 'Alat Riwayat', 'id_kategori' => KategoriAlat::create(['nama_kategori' => 'Riwayat'])->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 1, 'kondisi' => 'Baik', 'qr_code' => 'history-action-qr', 'status_alat' => 'Tersedia',
        ]);
        $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);

        $this->actingAs($student)->get(route('loans.mine'))
            ->assertOk()
            ->assertSee('<th scope="col">Catatan</th>', false)
            ->assertSee('<th scope="col" class="text-center">Aksi</th>', false)
            ->assertSee('class="loan-equipment-image rounded-3 bg-light"', false)
            ->assertSee('object-fit:contain', false)
            ->assertSee('action="'.route('loans.cancel', $loan).'"', false)
            ->assertSee('Batalkan Pengajuan')
            ->assertSee('sebelum alat diserahkan');
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
            ->assertViewHas('handoverCount', 1)
            ->assertViewHas('activeReturnCount', 0)
            ->assertSee('Penyerahan Alat')
            ->assertSee('Pengembalian Alat')
            ->assertSee('text-bg-warning', false)
            ->assertSee('>1</span>', false);

        DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 1,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('handoverCount', 0)
            ->assertViewHas('activeReturnCount', 1)
            ->assertSee('Pengembalian Alat')
            ->assertSee('>1</span>', false);
        $this->get(route('profile'))
            ->assertOk()
            ->assertDontSee('class="topbar-search', false);
    }

    public function test_profile_identity_is_locked_and_password_can_be_changed(): void
    {
        $student = User::factory()->create(['role' => 'siswa', 'nis_nip' => 'LOCKED-USER']);

        $this->actingAs($student)->get(route('profile'))
            ->assertOk()
            ->assertSee('Nama lengkap')
            ->assertSee('name="nama"', false)
            ->assertSee('Username (NIS)')
            ->assertSee('readonly', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('profile'))
            ->assertOk()
            ->assertSee('Username (NIP / ID Staf)')
            ->assertSee('Username tidak dapat diubah.');
        $this->put(route('profile.update'), [
            'nama' => 'Nama Admin Diperbarui',
            'email' => $admin->email,
        ])->assertRedirect();
        $this->assertSame('Nama Admin Diperbarui', $admin->fresh()->nama);

        $technician = User::factory()->create(['role' => 'teknisi']);
        $this->actingAs($technician)->get(route('profile'))
            ->assertOk()
            ->assertSee('Username (NIP / ID Staf)');

        $this->actingAs($student);
        $this->put(route('profile.update'), [
            'nama' => 'Nama Baru',
            'email' => $student->email,
            'nis_nip' => 'CHANGED-USER',
        ])->assertRedirect();
        $this->assertSame('LOCKED-USER', $student->fresh()->nis_nip);
        $this->assertNotSame('Nama Baru', $student->fresh()->nama);

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
