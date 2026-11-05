<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\DetailPeminjaman;
use App\Models\ItemUnit;
use App\Models\KategoriAlat;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\OfficialInventoryCatalog;
use Database\Seeders\OfficialInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OfficialInventorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_replaces_catalog_and_preserves_legacy_loan_and_unit_snapshots(): void
    {
        $category = KategoriAlat::create(['nama_kategori' => 'Kategori Lama']);
        $legacyEquipment = Alat::create([
            'kode_alat' => 'OLD-FAKE-01',
            'nama_alat' => 'Kamera Fiktif',
            'id_kategori' => $category->id_kategori,
            'stok_total' => 7,
            'stok_tersedia' => 7,
            'kondisi' => 'Baik',
            'qr_code' => 'old-fake-qr',
            'status_alat' => 'Tersedia',
        ]);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today(),
            'tanggal_kembali_rencana' => today()->addDay(),
            'keperluan' => 'Riwayat alat lama',
            'status' => 'Rejected',
        ]);
        $detail = $loan->detail()->create([
            'id_alat' => $legacyEquipment->id_alat,
            'jumlah' => 2,
        ]);
        $unit = ItemUnit::create([
            'alat_id' => $legacyEquipment->id_alat,
            'asset_id' => 'OLD-ASSET-01',
            'qr_code' => 'old-asset-qr',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $detail->itemUnits()->attach($unit->item_unit_id);

        $this->seed(OfficialInventorySeeder::class);
        $this->seed(OfficialInventorySeeder::class);

        $inventory = Alat::with('kategori')->orderBy('kode_alat')->get();
        $this->assertCount(13, $inventory);
        $this->assertSame(
            collect(OfficialInventoryCatalog::items())
                ->sortBy('code')
                ->map(fn (array $item): array => [$item['code'], $item['name'], $item['stock'], $item['stock']])
                ->values()
                ->all(),
            $inventory->map(fn (Alat $item): array => [
                $item->kode_alat,
                $item->nama_alat,
                $item->stok_total,
                $item->stok_tersedia,
            ])->all()
        );
        $this->assertSame(13, Alat::count());
        $this->assertDatabaseHas('loan_items', [
            'id_detail' => $detail->id_detail,
            'id_alat' => null,
            'alat_snapshot_kode' => 'OLD-FAKE-01',
            'alat_snapshot_nama' => 'Kamera Fiktif',
            'alat_snapshot_kategori' => 'Kategori Lama',
        ]);
        $this->assertDatabaseHas('item_units', [
            'item_unit_id' => $unit->item_unit_id,
            'alat_id' => null,
            'alat_snapshot_kode' => 'OLD-FAKE-01',
            'alat_snapshot_nama' => 'Kamera Fiktif',
        ]);
        $this->assertSame('Kamera Fiktif', $detail->fresh()->nama_alat);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Kamera Fiktif');
    }

    public function test_seeder_aborts_without_changing_inventory_when_an_active_loan_exists(): void
    {
        $equipment = Alat::firstOrFail();
        $originalStock = $equipment->stok_tersedia;
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today(),
            'tanggal_kembali_rencana' => today()->addDay(),
            'keperluan' => 'Transaksi aktif',
            'status' => 'Pending',
        ]);
        $loan->detail()->create([
            'id_alat' => $equipment->id_alat,
            'jumlah' => 1,
        ]);

        try {
            $this->seed(OfficialInventorySeeder::class);
            $this->fail('Seeder seharusnya menolak sinkronisasi saat ada transaksi aktif.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('transaksi Pending atau Approved', $exception->getMessage());
        }

        $this->assertSame($originalStock, $equipment->fresh()->stok_tersedia);
        $this->assertSame('Pending', $loan->fresh()->status);
        $this->assertDatabaseHas('loan_items', [
            'id_detail' => $loan->detail()->value('id_detail'),
            'id_alat' => $equipment->id_alat,
        ]);
    }
}
