<?php

namespace Tests\Feature;

use App\Models\Alat;
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
        $this->assertCount(14, $inventory);
        $this->assertSame(
            collect(OfficialInventoryCatalog::items())
                ->sortBy('code')
                ->map(fn (array $item): array => [$item['code'], $item['name'], $item['stock'], $item['stock'], $item['description']])
                ->values()
                ->all(),
            $inventory->map(fn (Alat $item): array => [
                $item->kode_alat,
                $item->nama_alat,
                $item->stok_total,
                $item->stok_tersedia,
                $item->deskripsi,
            ])->all()
        );
        $this->assertSame(14, Alat::count());
        $this->assertDatabaseHas('alat', [
            'kode_alat' => 'MUL-003',
            'nama_alat' => 'Mikrofon',
            'stok_total' => 1,
        ]);
        $this->assertDatabaseHas('alat', [
            'kode_alat' => 'MUL-004',
            'nama_alat' => 'Sound System',
            'stok_total' => 1,
        ]);
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

    public function test_description_migration_updates_only_official_inventory_descriptions(): void
    {
        $equipment = Alat::where('kode_alat', 'KOM-001')->firstOrFail();
        $availableStock = $equipment->stok_tersedia;
        $equipment->update(['deskripsi' => 'Deskripsi lama']);

        $migration = require database_path('migrations/2026_11_08_000000_update_official_inventory_descriptions.php');
        $migration->up();

        $this->assertSame(
            collect(OfficialInventoryCatalog::items())->firstWhere('code', 'KOM-001')['description'],
            $equipment->fresh()->deskripsi
        );
        $this->assertSame($availableStock, $equipment->fresh()->stok_tersedia);
    }

    public function test_split_migration_preserves_legacy_history_as_snapshots_and_creates_two_products(): void
    {
        $legacyEquipment = Alat::where('kode_alat', 'MUL-003')->firstOrFail();
        Alat::where('kode_alat', 'MUL-004')->delete();
        $legacyEquipment->update([
            'nama_alat' => 'Mic + sound',
            'deskripsi' => 'Perangkat mikrofon dan audio lama.',
        ]);

        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today(),
            'tanggal_kembali_rencana' => today()->addDay(),
            'keperluan' => 'Riwayat perangkat audio gabungan',
            'status' => 'Rejected',
        ]);
        $detail = $loan->detail()->create([
            'id_alat' => $legacyEquipment->id_alat,
            'jumlah' => 1,
        ]);
        $unit = ItemUnit::create([
            'alat_id' => $legacyEquipment->id_alat,
            'asset_id' => 'LEGACY-AUDIO-01',
            'qr_code' => 'legacy-audio-qr',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $detail->itemUnits()->attach($unit->item_unit_id);

        $migration = require database_path('migrations/2026_11_08_000001_split_microphone_sound_inventory.php');
        $migration->up();

        $this->assertSame(14, Alat::count());
        $this->assertDatabaseHas('alat', [
            'kode_alat' => 'MUL-003',
            'nama_alat' => 'Mikrofon',
            'stok_total' => 1,
            'stok_tersedia' => 1,
        ]);
        $this->assertDatabaseHas('alat', [
            'kode_alat' => 'MUL-004',
            'nama_alat' => 'Sound System',
            'stok_total' => 1,
            'stok_tersedia' => 1,
        ]);
        $this->assertDatabaseHas('loan_items', [
            'id_detail' => $detail->id_detail,
            'id_alat' => null,
            'alat_snapshot_kode' => 'MUL-003',
            'alat_snapshot_nama' => 'Mic + sound',
            'alat_snapshot_kategori' => 'Multimedia',
        ]);
        $this->assertDatabaseHas('item_units', [
            'item_unit_id' => $unit->item_unit_id,
            'alat_id' => null,
            'alat_snapshot_kode' => 'MUL-003',
            'alat_snapshot_nama' => 'Mic + sound',
        ]);
        $this->assertDatabaseHas('detail_peminjaman_item_unit', [
            'id_detail' => $detail->id_detail,
            'item_unit_id' => $unit->item_unit_id,
        ]);
        $this->assertSame('Mic + sound', $detail->fresh()->nama_alat);
    }

    public function test_split_migration_stops_if_legacy_audio_product_has_an_active_loan(): void
    {
        $legacyEquipment = Alat::where('kode_alat', 'MUL-003')->firstOrFail();
        Alat::where('kode_alat', 'MUL-004')->delete();
        $legacyEquipment->update(['nama_alat' => 'Mic + sound']);
        $student = User::factory()->create(['role' => 'siswa']);
        $loan = Peminjaman::create([
            'id_user' => $student->id_user,
            'tanggal_pinjam' => today(),
            'tanggal_kembali_rencana' => today()->addDay(),
            'keperluan' => 'Transaksi aktif perangkat audio',
            'status' => 'Approved',
        ]);
        $detail = $loan->detail()->create([
            'id_alat' => $legacyEquipment->id_alat,
            'jumlah' => 1,
        ]);

        $migration = require database_path('migrations/2026_11_08_000001_split_microphone_sound_inventory.php');
        try {
            $migration->up();
            $this->fail('Migrasi seharusnya berhenti saat ada peminjaman aktif alat lama.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('transaksi Pending atau Approved', $exception->getMessage());
        }

        $this->assertSame('Mic + sound', $legacyEquipment->fresh()->nama_alat);
        $this->assertSame($legacyEquipment->id_alat, $detail->fresh()->id_alat);
        $this->assertDatabaseMissing('alat', ['kode_alat' => 'MUL-004']);
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
