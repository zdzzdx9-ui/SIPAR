<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\DetailPenyerahan;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_search_and_loan_submission_stay_protected(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'QR-PUBLIC', 'nama_alat' => 'Alat QR Publik', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'kondisi' => 'Baik', 'qr_code' => 'qr-public-test', 'status_alat' => 'Tersedia',
        ]);

        $this->get(route('catalog'))->assertRedirect(route('login'));
        $this->post(route('loans.create'), [])->assertRedirect(route('login'));
        $this->get(route('technician.returns.search', ['q' => $equipment->kode_alat]))->assertRedirect(route('login'));
        $this->assertSame(2, $equipment->fresh()->stok_tersedia);
    }

    public function test_student_can_submit_one_equipment_item_from_the_catalog_modal(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'QR-REQUEST', 'nama_alat' => 'Alat Pengajuan QR', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'qr-request-test', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)->post(route('loans.create'), [
            'id_alat' => $equipment->id_alat,
            'jumlah' => 1,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Praktik hasil scan QR',
        ])->assertRedirect(route('loans.mine'));

        $loan = Peminjaman::firstOrFail();
        $this->assertDatabaseHas('loan_items', [
            'id_peminjaman' => $loan->id_peminjaman,
            'id_alat' => $equipment->id_alat,
            'jumlah' => 1,
        ]);
        $this->assertSame('Pending', $loan->status);
        $this->assertSame(3, $equipment->fresh()->stok_tersedia);
    }

    public function test_student_can_request_multiple_equipment_but_only_one_of_each_type(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $firstEquipment = Alat::create([
            'kode_alat' => 'ONE-UNIT-A', 'nama_alat' => 'Alat Satu', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'one-unit-a', 'status_alat' => 'Tersedia',
        ]);
        $secondEquipment = Alat::create([
            'kode_alat' => 'ONE-UNIT-B', 'nama_alat' => 'Alat Dua', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'one-unit-b', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $request = [
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Praktik',
        ];

        $this->actingAs($student)->post(route('loans.create'), $request + [
            'items' => [['id_alat' => $firstEquipment->id_alat, 'jumlah' => 2]],
        ])->assertSessionHasErrors('items.0.jumlah');
        $this->assertSame(0, Peminjaman::count());

        $this->post(route('loans.create'), $request + [
            'items' => [
                ['id_alat' => $firstEquipment->id_alat, 'jumlah' => 1],
                ['id_alat' => $secondEquipment->id_alat, 'jumlah' => 1],
            ],
        ])->assertRedirect(route('loans.mine'));

        $this->assertSame(1, Peminjaman::count());
        $this->assertSame(2, Peminjaman::firstOrFail()->detail()->count());
        $this->assertSame(
            [1, 1],
            Peminjaman::firstOrFail()->detail()->orderBy('id_alat')->pluck('jumlah')->all()
        );
    }

    public function test_loan_duration_is_limited_to_five_days_and_booking_fields_are_stored(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Durasi']);
        $equipment = Alat::create([
            'kode_alat' => 'DURATION-01', 'nama_alat' => 'Alat Durasi', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'kondisi' => 'Baik', 'qr_code' => 'duration-qr', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $payload = [
            'items' => [['id_alat' => $equipment->id_alat, 'jumlah' => 1]],
            'tanggal_pinjam' => today()->toDateString(),
            'keperluan' => 'Uji batas durasi',
        ];

        $this->actingAs($student)->post(route('loans.create'), $payload + [
            'tanggal_kembali_rencana' => today()->addDays(6)->toDateString(),
        ])->assertSessionHasErrors('tanggal_kembali_rencana');
        $this->assertSame(0, Peminjaman::count());

        $this->post(route('loans.create'), $payload + [
            'tanggal_kembali_rencana' => today()->addDays(5)->toDateString(),
        ])->assertRedirect(route('loans.mine'));

        $loan = Peminjaman::firstOrFail();
        $this->assertMatchesRegularExpression('/^PJM-\\d{8}$/', $loan->booking_code);
        $this->assertSame($student->id_user, $loan->user_id);
        $this->assertSame(today()->toDateString(), $loan->start_date->toDateString());
        $this->assertSame(today()->addDays(5)->toDateString(), $loan->end_date->toDateString());
    }

    public function test_student_cannot_request_the_same_equipment_until_active_request_is_closed(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'ONE-ACTIVE', 'nama_alat' => 'Router Aktif', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'qr-one-active', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Pengajuan aktif',
            'status' => 'Pending',
        ]);
        $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $requestData = [
            'items' => [['id_alat' => $equipment->id_alat, 'jumlah' => 1]],
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Pengajuan kedua',
        ];

        $this->actingAs($student)->get(route('catalog'))
            ->assertOk()
            ->assertSee('Sudah diajukan')
            ->assertSee('data-unavailable="1"', false);
        $this->actingAs($student)->post(route('loans.create'), $requestData)
            ->assertSessionHasErrors('items');
        $this->assertSame(1, Peminjaman::count());

        $loan->update(['status' => 'Approved']);
        $this->post(route('loans.create'), $requestData)->assertSessionHasErrors('items');
        $loan->update(['status' => 'Selesai']);

        $this->post(route('loans.create'), $requestData)->assertRedirect(route('loans.mine'));
        $this->assertSame(2, Peminjaman::count());
    }

    public function test_admin_approval_and_late_return_update_stock_and_fine_for_existing_multi_item_loan(): void
    {
        $now = Carbon::now()->addDays(3)->setTime(12, 0);
        $this->travelTo($now);
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'LAN-TEST', 'nama_alat' => 'LAN Tester', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'qr-lan-test', 'status_alat' => 'Tersedia',
        ]);
        $secondEquipment = Alat::create([
            'kode_alat' => 'ROUTER-TEST', 'nama_alat' => 'Router Test', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'kondisi' => 'Baik', 'qr_code' => 'qr-router-test', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $admin = User::factory()->create(['role' => 'admin']);

        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => $now->toDateString(),
            'tanggal_kembali_rencana' => $now->copy()->addDay()->toDateString(),
            'keperluan' => 'Praktik jaringan kelas XI RPL',
            'status' => 'Pending',
        ]);
        $loan->detail()->createMany([
            ['id_alat' => $equipment->id_alat, 'jumlah' => 2],
            ['id_alat' => $secondEquipment->id_alat, 'jumlah' => 1],
        ]);
        $this->assertSame('Pending', $loan->status);
        $this->assertSame(3, $equipment->fresh()->stok_tersedia);
        $this->assertSame(2, $secondEquipment->fresh()->stok_tersedia);
        $this->assertCount(2, $loan->detail);

        $this->actingAs($admin)->post(route('admin.approvals.approve', $loan))->assertRedirect();
        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(1, $secondEquipment->fresh()->stok_tersedia);
        $this->assertSame('Approved', $loan->fresh()->status);
        $this->assertStringContainsString('12.00 - 13.00 WIB', $loan->fresh()->operational_notes);
        $technician = User::factory()->create(['role' => 'teknisi']);
        foreach ($loan->detail as $detail) {
            DetailPenyerahan::create([
                'id_detail' => $detail->id_detail,
                'id_teknisi' => $technician->id_user,
                'jumlah' => $detail->jumlah,
                'tanggal_penyerahan' => now(),
                'kondisi_alat' => 'Baik',
            ]);
        }

        $returnedAt = $now->copy()->addDays(3)->setTime(11, 0);
        $this->travelTo($returnedAt->copy()->addMinute());

        $this->actingAs($technician)->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000001',
            'id_detail' => $loan->detail()->where('id_alat', $equipment->id_alat)->value('id_detail'),
            'jumlah' => 1,
            'kode_alat' => $equipment->kode_alat,
            'tanggal_kembali_aktual' => $returnedAt->toDateTimeString(),
            'kondisi_alat' => 'Rusak',
            'catatan' => 'Lengkap dan berfungsi.',
            'foto_bukti' => $this->fakeImage('return-1.jpg'),
        ])->assertRedirect(route('technician.returns'));

        $this->assertSame('Approved', $loan->fresh()->status);
        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $equipment->fresh()->stok_maintenance);
        $this->assertSame(1, $equipment->fresh()->stok_rusak);
        $this->assertDatabaseHas('detail_pengembalian', [
            'id_detail' => $loan->detail()->where('id_alat', $equipment->id_alat)->value('id_detail'),
            'jumlah' => 1,
        ]);
        $this->getJson(route('technician.returns.search', ['q' => $loan->booking_code]))
            ->assertOk()
            ->assertJsonPath('0.booking_code', $loan->booking_code)
            ->assertJsonPath('0.items.0.kode', $equipment->kode_alat);
        $this->actingAs($technician)->get(route('technician.returns'))->assertOk();
        $this->assertTrue($loan->fresh()->overdue_flag);
        $this->assertTrue($loan->fresh()->reminder_flag);
        $this->actingAs($student)->get(route('loans.mine'))->assertOk();

        $this->actingAs($technician)->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000002',
            'id_detail' => $loan->detail()->where('id_alat', $equipment->id_alat)->value('id_detail'),
            'jumlah' => 1,
            'kode_alat' => $equipment->kode_alat,
            'tanggal_kembali_aktual' => $returnedAt->toDateTimeString(),
            'kondisi_alat' => 'Baik',
            'foto_bukti' => $this->fakeImage('return-2.jpg'),
        ])->assertRedirect(route('technician.returns'));

        $this->assertSame('Approved', $loan->fresh()->status);
        $this->assertSame(2, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $equipment->fresh()->stok_maintenance);
        $this->assertSame(1, $equipment->fresh()->stok_rusak);

        $this->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000003',
            'id_detail' => $loan->detail()->where('id_alat', $secondEquipment->id_alat)->value('id_detail'),
            'jumlah' => 1,
            'kode_alat' => $secondEquipment->kode_alat,
            'tanggal_kembali_aktual' => $returnedAt->toDateTimeString(),
            'kondisi_alat' => 'Baik',
            'catatan' => 'Router lengkap.',
            'foto_bukti' => $this->fakeImage('return-router.jpg'),
        ])->assertRedirect(route('technician.returns'));

        $this->assertSame(2, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $equipment->fresh()->stok_maintenance);
        $this->assertSame(1, $equipment->fresh()->stok_rusak);
        $this->assertSame(2, $secondEquipment->fresh()->stok_tersedia);
        $this->assertSame('Selesai', $loan->fresh()->status);
        $this->assertDatabaseHas('pengembalian', ['id_peminjaman' => $loan->id_peminjaman, 'denda' => 10000]);
        $this->assertDatabaseHas('pengembalian', ['id_peminjaman' => $loan->id_peminjaman, 'kondisi_alat' => 'Rusak']);
    }

    public function test_lost_return_reduces_total_stock_without_inflating_available_stock(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'SWITCH-LOST', 'nama_alat' => 'Switch Test', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'stok_maintenance' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-switch-lost', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Tes alat hilang',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $equipment->update(['stok_tersedia' => 1, 'status_alat' => 'Tersedia']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 1,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);

        $this->actingAs($technician)->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000004',
            'id_detail' => $detail->id_detail,
            'jumlah' => 1,
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Hilang',
            'foto_bukti' => $this->fakeImage('lost.jpg'),
        ])->assertRedirect(route('technician.returns'));

        $this->assertSame(1, $equipment->fresh()->stok_total);
        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $equipment->fresh()->stok_maintenance);
        $this->assertSame('Selesai', $loan->fresh()->status);
        $this->assertDatabaseHas('pengembalian', ['id_peminjaman' => $loan->id_peminjaman, 'kondisi_alat' => 'Hilang']);
    }

    public function test_multi_item_approval_rolls_back_stock_when_one_item_is_no_longer_available(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $firstEquipment = Alat::create([
            'kode_alat' => 'ATOMIC-1', 'nama_alat' => 'Alat Atomic Satu', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 2, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-atomic-1', 'status_alat' => 'Tersedia',
        ]);
        $secondEquipment = Alat::create([
            'kode_alat' => 'ATOMIC-2', 'nama_alat' => 'Alat Atomic Dua', 'id_kategori' => $category->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 0, 'stok_maintenance' => 1, 'stok_rusak' => 0,
            'kondisi' => 'Maintenance', 'qr_code' => 'qr-atomic-2', 'status_alat' => 'Maintenance',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Tes approval atomik',
            'status' => 'Pending',
        ]);
        $loan->detail()->createMany([
            ['id_alat' => $firstEquipment->id_alat, 'jumlah' => 1],
            ['id_alat' => $secondEquipment->id_alat, 'jumlah' => 1],
        ]);

        $this->actingAs($admin)->post(route('admin.approvals.approve', $loan))
            ->assertSessionHasErrors('stok');

        $this->assertSame('Pending', $loan->fresh()->status);
        $this->assertSame(2, $firstEquipment->fresh()->stok_tersedia);
        $this->assertSame(1, $secondEquipment->fresh()->stok_maintenance);
        $this->assertSame(0, $secondEquipment->fresh()->stok_tersedia);
    }

    public function test_rejecting_a_pending_multi_item_loan_does_not_change_stock(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'REJECT-STOCK', 'nama_alat' => 'Alat Ditolak', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-reject-stock', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Tes penolakan',
            'status' => 'Pending',
        ]);
        $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 2]);

        $this->actingAs($admin)->post(route('admin.approvals.reject', $loan), [
            'catatan_admin' => 'Jadwal penggunaan alat tidak tersedia.',
        ])->assertRedirect();

        $this->assertSame('Rejected', $loan->fresh()->status);
        $this->assertSame(3, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $equipment->fresh()->stok_maintenance);
        $this->assertSame(0, $equipment->fresh()->stok_rusak);
        $this->assertDatabaseHas('loans', [
            'id_peminjaman' => $loan->id_peminjaman,
            'catatan_admin' => 'Jadwal penggunaan alat tidak tersedia.',
        ]);
    }

    public function test_student_status_badge_counts_decisions_and_clears_after_opening_history(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Notifikasi Status']);
        $equipment = Alat::create([
            'kode_alat' => 'STATUS-BADGE', 'nama_alat' => 'Alat Notifikasi', 'id_kategori' => $category->id_kategori,
            'stok_total' => 3, 'stok_tersedia' => 3, 'kondisi' => 'Baik', 'qr_code' => 'status-badge-qr', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $approvedLoan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today()->toDateString(),
            'tanggal_kembali_rencana' => today()->addDays(2)->toDateString(),
            'keperluan' => 'Notifikasi persetujuan',
            'status' => 'Pending',
        ]);
        $approvedLoan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $rejectedLoan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today()->toDateString(),
            'tanggal_kembali_rencana' => today()->addDays(2)->toDateString(),
            'keperluan' => 'Notifikasi penolakan',
            'status' => 'Pending',
        ]);
        $rejectedLoan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);

        $this->actingAs($admin)->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('data-disable-on-submit', false)
            ->assertSee('data-confirm-message="Apakah Anda yakin ingin menyetujui peminjaman', false)
            ->assertSee('data-submitting-text="Menyetujui..."', false)
            ->assertSee('data-submitting-text="Menolak..."', false)
            ->assertSee('class="approval-reject-panel" id="reject-panel-', false)
            ->assertSee(' hidden>', false)
            ->assertSee('data-validation-message="Alasan penolakan wajib diisi dengan valid."', false)
            ->assertSee('id="global-action-confirmation"', false);

        $this->actingAs($admin)
            ->post(route('admin.approvals.approve', $approvedLoan))
            ->assertRedirect();
        $this->post(route('admin.approvals.reject', $rejectedLoan), ['catatan_admin' => 'Jadwal tidak tersedia'])
            ->assertRedirect();

        $approvedLoan->update(['status' => 'Selesai']);
        $this->assertDatabaseHas('loans', ['id_peminjaman' => $approvedLoan->id_peminjaman, 'status_read_at' => null]);
        $this->assertDatabaseHas('loans', ['id_peminjaman' => $rejectedLoan->id_peminjaman, 'status_read_at' => null]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2 perubahan status belum dibaca');

        $this->get(route('loans.mine'))
            ->assertOk()
            ->assertSee('0 perubahan status belum dibaca');

        $this->assertNotNull($approvedLoan->fresh()->status_read_at);
        $this->assertNotNull($rejectedLoan->fresh()->status_read_at);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('0 perubahan status belum dibaca');
    }

    public function test_return_cannot_exceed_the_remaining_quantity(): void
    {
        Storage::fake('public');
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'RETURN-LIMIT', 'nama_alat' => 'Alat Batas Kembali', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 1, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-return-limit', 'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Tes batas jumlah pengembalian',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $technician = User::factory()->create(['role' => 'teknisi']);
        DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 1,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);

        $this->actingAs($technician)->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000005',
            'id_detail' => $detail->id_detail,
            'jumlah' => 2,
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Baik',
            'foto_bukti' => $this->fakeImage('over-return.jpg'),
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame('Approved', $loan->fresh()->status);
        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(0, $detail->pengembalianDetail()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('bukti/pengembalian'));
    }

    public function test_partial_technician_return_restores_stock_without_admin_approval(): void
    {
        Storage::fake('public');
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'RETURN-PARTIAL', 'nama_alat' => 'Alat Bertahap', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 0, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-return-partial', 'status_alat' => 'Dipinjam',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Tes pengembalian bertahap',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 2]);
        DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 2,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);

        $returnToken = '00000000-0000-4000-8000-000000000006';
        $returnData = [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => $returnToken,
            'id_detail' => $detail->id_detail,
            'jumlah' => 1,
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Baik',
            'foto_bukti' => $this->fakeImage('return-partial.jpg'),
        ];
        $this->actingAs($technician)->post(route('technician.returns.store'), $returnData)
            ->assertRedirect(route('technician.returns'));

        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(1, $detail->pengembalianDetail()->sum('jumlah'));
        $this->assertSame('Approved', $loan->fresh()->status);
        $this->assertDatabaseMissing('pengembalian', ['id_peminjaman' => $loan->id_peminjaman]);

        $this->post(route('technician.returns.store'), [
            'id_peminjaman' => (string) $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000007',
            'id_detail' => (string) $detail->id_detail,
            'jumlah' => '1',
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Maintenance',
            'foto_bukti' => $this->fakeImage('return-maintenance.jpg'),
        ])->assertRedirect(route('technician.returns'));

        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(1, $equipment->fresh()->stok_maintenance);
        $this->assertDatabaseHas('pengembalian', [
            'id_peminjaman' => $loan->id_peminjaman,
            'kondisi_alat' => 'Maintenance',
        ]);
        $this->assertSame('Selesai', $loan->fresh()->status);
    }

    public function test_replayed_legacy_return_request_does_not_inflate_stock(): void
    {
        Storage::fake('public');
        $category = KategoriAlat::create(['nama_kategori' => 'Pengembalian Idempoten']);
        $equipment = Alat::create([
            'kode_alat' => 'RETURN-IDEMPOTENT', 'nama_alat' => 'Alat Idempoten', 'id_kategori' => $category->id_kategori,
            'stok_total' => 2, 'stok_tersedia' => 0, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-return-idempotent', 'status_alat' => 'Dipinjam',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today()->toDateString(),
            'tanggal_kembali_rencana' => today()->addDay()->toDateString(),
            'keperluan' => 'Uji pengiriman ulang pengembalian',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 2]);
        DetailPenyerahan::create([
            'id_detail' => $detail->id_detail,
            'id_teknisi' => $technician->id_user,
            'jumlah' => 2,
            'tanggal_penyerahan' => now(),
            'kondisi_alat' => 'Baik',
        ]);
        $requestData = [
            'id_peminjaman' => $loan->id_peminjaman,
            'idempotency_key' => '00000000-0000-4000-8000-000000000008',
            'id_detail' => $detail->id_detail,
            'jumlah' => 1,
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Baik',
        ];

        $this->actingAs($technician)->post(route('technician.returns.store'), $requestData + [
            'foto_bukti' => $this->fakeImage('return-first.jpg'),
        ])->assertRedirect(route('technician.returns'));
        $this->post(route('technician.returns.store'), $requestData + [
            'foto_bukti' => $this->fakeImage('return-replay.jpg'),
        ])->assertRedirect(route('technician.returns'))
            ->assertSessionHas('success', 'Pengembalian ini sudah tercatat; permintaan duplikat diabaikan.');

        $this->assertSame(1, $equipment->fresh()->stok_tersedia);
        $this->assertSame(1, $detail->pengembalianDetail()->sum('jumlah'));
        $this->assertSame(1, $detail->pengembalianDetail()->count());
        $this->assertSame(1, count(Storage::disk('public')->allFiles('bukti/pengembalian')));
        $this->assertDatabaseHas('detail_pengembalian', [
            'idempotency_key' => $requestData['idempotency_key'],
            'jumlah' => 1,
        ]);
    }

    public function test_return_search_looks_up_active_transactions_by_booking_code_or_student_nis(): void
    {
        $technician = User::factory()->create(['role' => 'teknisi']);
        $category = KategoriAlat::create(['nama_kategori' => 'Jaringan']);
        $equipment = Alat::create([
            'kode_alat' => 'QR-NO-LOAN', 'nama_alat' => 'Alat Belum Dipinjam', 'id_kategori' => $category->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 1, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'qr-no-loan', 'status_alat' => 'Tersedia',
        ]);

        $this->actingAs($technician)->getJson(route('technician.returns.search', ['q' => 'alat-tidak-dikenal']))
            ->assertNotFound()
            ->assertJsonPath('message', 'Transaksi aktif tidak ditemukan. Periksa Kode Transaksi atau NIS siswa.');

        $student = User::factory()->create(['role' => 'siswa', 'nis_nip' => 'NIS-LOOKUP-01']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDay()->toDateString(),
            'keperluan' => 'Lookup via NIS',
            'status' => 'Approved',
        ]);
        $loan->detail()->create(['id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $this->getJson(route('technician.returns.search', ['q' => $loan->booking_code]))
            ->assertOk()
            ->assertJsonPath('0.booking_code', $loan->booking_code)
            ->assertJsonPath('0.items.0.nama', 'Alat Belum Dipinjam')
            ->assertJsonPath('0.items.0.status', 'Belum diserahkan')
            ->assertJsonPath('0.items.0.dapat_dikembalikan', 0);
        $this->getJson(route('technician.returns.search', ['q' => 'NIS-LOOKUP-01']))
            ->assertOk()
            ->assertJsonPath('0.booking_code', $loan->booking_code);
        $this->getJson(route('technician.returns.search', ['q' => $equipment->kode_alat]))
            ->assertOk()
            ->assertJsonPath('0.booking_code', $loan->booking_code)
            ->assertJsonPath('0.items.0.kode', $equipment->kode_alat);

        $this->get(route('technician.returns', ['q' => $equipment->kode_alat]))
            ->assertOk()
            ->assertSee($loan->booking_code)
            ->assertSee($equipment->kode_alat)
            ->assertSee('Belum diserahkan oleh teknisi. Pengembalian belum dapat dicatat.')
            ->assertSee('NIS siswa');
    }

    public function test_student_cannot_open_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'siswa']))
            ->get(route('admin.equipment'))
            ->assertForbidden();
    }
}
