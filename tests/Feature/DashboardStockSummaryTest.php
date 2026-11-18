<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\KategoriAlat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStockSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sums_available_units_even_when_equipment_status_is_rusak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = KategoriAlat::create(['nama_kategori' => 'Peralatan Uji']);
        $existingAvailableStock = Alat::sum('stok_tersedia');

        Alat::create([
            'kode_alat' => 'MIXED-001',
            'nama_alat' => 'Alat dengan stok campuran',
            'id_kategori' => $category->id_kategori,
            'stok_total' => 30,
            'stok_tersedia' => 29,
            'stok_maintenance' => 0,
            'stok_rusak' => 1,
            'kondisi' => 'Baik',
            'qr_code' => 'mixed-stock-qr',
            'status_alat' => 'Rusak',
        ]);
        Alat::create([
            'kode_alat' => 'READY-001',
            'nama_alat' => 'Alat siap pinjam',
            'id_kategori' => $category->id_kategori,
            'stok_total' => 56,
            'stok_tersedia' => 56,
            'stok_maintenance' => 0,
            'stok_rusak' => 0,
            'kondisi' => 'Baik',
            'qr_code' => 'ready-stock-qr',
            'status_alat' => 'Tersedia',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('tersedia', $existingAvailableStock + 85);
    }
}
