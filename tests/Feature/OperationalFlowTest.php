<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\DetailPeminjaman;
use App\Models\ItemUnit;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_cancel_only_their_own_pending_request(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $otherStudent = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Pengajuan yang dibatalkan',
            'status' => 'Pending',
        ]);
        $otherLoan = Peminjaman::create([
            'id_user' => $otherStudent->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Pengajuan milik siswa lain',
            'status' => 'Pending',
        ]);

        $this->actingAs($student)->post(route('loans.cancel', $loan))->assertRedirect();
        $this->assertSame('Cancelled', $loan->fresh()->status);

        $this->actingAs($student)->post(route('loans.cancel', $otherLoan))
            ->assertSessionHasErrors('status');
        $this->assertSame('Pending', $otherLoan->fresh()->status);
    }

    public function test_admin_approves_all_requests_and_technician_receives_unit_handover_tasks(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Operasional']);
        $legacyEquipment = Alat::create([
            'kode_alat' => 'LEGACY-01', 'nama_alat' => 'Alat Legacy', 'id_kategori' => $category->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 1, 'kondisi' => 'Baik', 'qr_code' => 'legacy-qr', 'status_alat' => 'Tersedia',
        ]);
        $unitEquipment = Alat::create([
            'kode_alat' => 'UNIT-01', 'nama_alat' => 'Alat Unit', 'id_kategori' => $category->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 1, 'kondisi' => 'Baik', 'qr_code' => 'unit-qr', 'status_alat' => 'Tersedia',
        ]);
        ItemUnit::create([
            'alat_id' => $unitEquipment->id_alat, 'asset_id' => 'UNIT-01-A', 'qr_code' => 'unit-asset-qr',
            'condition' => 'Baik', 'status' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        foreach ([$legacyEquipment, $unitEquipment] as $equipment) {
            $loan = Peminjaman::create([
                'id_user' => $student->id_user,
                'tanggal_pinjam' => now()->toDateString(),
                'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
                'keperluan' => 'Uji antrean operasional',
                'status' => 'Pending',
            ]);
            DetailPeminjaman::create(['id_peminjaman' => $loan->id_peminjaman, 'id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $this->actingAs($admin)->get(route('admin.approvals'))
            ->assertOk()->assertSee('Alat Legacy')->assertSee('Alat Unit');
        $unitLoan = Peminjaman::whereHas('detail', fn ($query) => $query->where('id_alat', $unitEquipment->id_alat))->firstOrFail();
        $this->actingAs($admin)->post(route('admin.approvals.approve', $unitLoan))->assertRedirect();
        $this->assertSame('Approved', $unitLoan->fresh()->status);
        $this->assertDatabaseMissing('detail_peminjaman_item_unit', [
            'id_detail' => $unitLoan->detail->first()->id_detail,
            'item_unit_id' => $unitEquipment->itemUnits()->value('item_unit_id'),
        ]);
        $this->assertDatabaseHas('item_units', ['alat_id' => $unitEquipment->id_alat, 'status' => 'Tersedia']);
        $this->actingAs($technician)->get(route('technician.handovers'))
            ->assertOk()->assertSee('Alat Unit')->assertDontSee('Alat Legacy')
            ->assertSee($unitLoan->booking_code)
            ->assertSee('Kode Transaksi atau NIS siswa')
            ->assertSee('Asset ID / Serial Number');
        $this->get('/teknisi/verifikasi')->assertNotFound();
    }

    public function test_unit_handover_records_the_exact_asset_id(): void
    {
        Storage::fake('public');
        $category = KategoriAlat::create(['nama_kategori' => 'Unit']);
        $equipment = Alat::create([
            'kode_alat' => 'UNIT-02', 'nama_alat' => 'Alat Berunit', 'id_kategori' => $category->id_kategori,
            'stok_total' => 1, 'stok_tersedia' => 0, 'kondisi' => 'Baik', 'qr_code' => 'unit-02-qr', 'status_alat' => 'Dipinjam',
        ]);
        $unit = ItemUnit::create([
            'alat_id' => $equipment->id_alat, 'asset_id' => 'UNIT-02-A', 'qr_code' => 'unit-02-asset-qr',
            'condition' => 'Baik', 'status' => 'Dipinjam',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => now()->toDateString(),
            'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
            'keperluan' => 'Uji Asset ID',
            'status' => 'Approved',
        ]);
        $detail = DetailPeminjaman::create(['id_peminjaman' => $loan->id_peminjaman, 'id_alat' => $equipment->id_alat, 'jumlah' => 1]);
        $detail->itemUnits()->attach($unit->item_unit_id, ['assigned_by' => $technician->id_user]);

        $this->actingAs($technician)->post(route('technician.handovers.store', $loan), [
            'id_detail' => $detail->id_detail,
            'asset_ids' => $unit->asset_id,
            'jumlah' => '1',
            'kondisi_alat' => 'Baik',
            'foto_bukti' => $this->fakeImage('handover.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('detail_penyerahan', [
            'id_detail' => $detail->id_detail,
            'item_unit_id' => $unit->item_unit_id,
            'jumlah' => 1,
        ]);
    }

    public function test_student_admin_and_technician_complete_asset_id_lifecycle_with_all_return_conditions(): void
    {
        Storage::fake('public');
        $category = KategoriAlat::create(['nama_kategori' => 'Siklus Fisik']);
        $equipment = Alat::create([
            'kode_alat' => 'FULL-UNIT-01', 'nama_alat' => 'Alat Siklus Lengkap', 'id_kategori' => $category->id_kategori,
            'stok_total' => 4, 'stok_tersedia' => 4, 'stok_maintenance' => 0, 'stok_rusak' => 0,
            'kondisi' => 'Baik', 'qr_code' => 'full-unit-qr', 'status_alat' => 'Tersedia',
        ]);
        $units = collect(['FULL-A', 'FULL-B', 'FULL-C', 'FULL-D'])->map(fn ($assetId) => ItemUnit::create([
            'alat_id' => $equipment->id_alat,
            'asset_id' => $assetId,
            'qr_code' => 'qr-'.$assetId,
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]));
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $loans = collect();

        foreach (range(1, 4) as $studentNumber) {
            $student = User::factory()->create(['role' => 'siswa']);
            $this->actingAs($student)->post(route('loans.create'), [
                'items' => [['id_alat' => (string) $equipment->id_alat, 'jumlah' => '1']],
                'tanggal_pinjam' => now()->toDateString(),
                'tanggal_kembali_rencana' => now()->addDays(2)->toDateString(),
                'keperluan' => 'Simulasi siklus Asset ID '.$studentNumber,
            ])->assertRedirect(route('loans.mine'));
            $loans->push(Peminjaman::where('id_user', $student->id_user)->latest('id_peminjaman')->firstOrFail());
        }

        $this->assertSame(4, $loans->where('status', 'Pending')->count());
        $this->assertSame(4, $equipment->fresh()->stok_tersedia);

        foreach ($loans as $loan) {
            $this->actingAs($admin)->post(route('admin.approvals.approve', $loan))->assertRedirect();
        }
        $this->assertSame(0, $equipment->fresh()->stok_tersedia);
        $this->assertSame(4, ItemUnit::where('alat_id', $equipment->id_alat)->where('status', 'Tersedia')->count());

        foreach (['Baik', 'Maintenance', 'Rusak', 'Hilang'] as $index => $condition) {
            $loan = $loans[$index]->fresh();
            $detail = $loan->detail()->firstOrFail();
            $unit = $units[$index];
            $this->actingAs($technician)->post(route('technician.handovers.store', $loan), [
                'id_detail' => (string) $detail->id_detail,
                'asset_ids' => $unit->asset_id,
                'jumlah' => '1',
                'kondisi_alat' => 'Baik',
                'foto_bukti' => $this->fakeImage('handover-'.$index.'.jpg'),
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->post(route('technician.returns.store'), [
                'id_peminjaman' => (string) $loan->id_peminjaman,
                'idempotency_key' => sprintf('00000000-0000-4000-8000-%012d', $index + 11),
                'booking_code' => $loan->booking_code,
                'id_detail' => (string) $detail->id_detail,
                'item_unit_id' => (string) $unit->item_unit_id,
                'asset_id' => $unit->asset_id,
                'jumlah' => '1',
                'kode_alat' => $equipment->kode_alat,
                'kondisi_alat' => $condition,
                'foto_bukti' => $this->fakeImage('return-'.$index.'.jpg'),
            ])->assertRedirect(route('technician.returns'));
        }

        $equipment->refresh();
        $this->assertSame(3, $equipment->stok_total);
        $this->assertSame(1, $equipment->stok_tersedia);
        $this->assertSame(1, $equipment->stok_maintenance);
        $this->assertSame(1, $equipment->stok_rusak);
        foreach ($loans as $index => $loan) {
            $this->assertSame('Selesai', $loan->fresh()->status);
            $this->assertSame(
                ['Baik', 'Maintenance', 'Rusak', 'Hilang'][$index],
                $loan->detail->first()->fresh()->item_conditions[$units[$index]->asset_id]
            );
            $this->assertDatabaseHas('pengembalian', [
                'id_peminjaman' => $loan->id_peminjaman,
                'kondisi_alat' => ['Baik', 'Maintenance', 'Rusak', 'Hilang'][$index],
            ]);
        }
        $this->assertDatabaseHas('item_units', [
            'item_unit_id' => $units[3]->item_unit_id,
            'status' => 'Hilang',
        ]);
        $this->assertSame(3, $equipment->itemUnits()->where('status', '!=', 'Hilang')->count());
    }
}
