<?php

namespace Tests\Feature;

use App\Models\Alat;
use App\Models\Peminjaman;
use App\Models\User;
use Database\Seeders\OverdueLoanDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OverdueLoanDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_an_active_overdue_loan_visible_to_technician_and_admin(): void
    {
        Storage::fake('public');
        $student = User::factory()->create(['role' => 'siswa']);
        $technician = User::factory()->create(['role' => 'teknisi']);
        $admin = User::factory()->create(['role' => 'admin']);
        $equipment = Alat::where('kode_alat', 'KOM-002')->firstOrFail();
        $originalStock = $equipment->stok_tersedia;

        $this->seed(OverdueLoanDemoSeeder::class);
        $this->seed(OverdueLoanDemoSeeder::class);

        $loan = Peminjaman::with('detail.penyerahanDetail')
            ->where('booking_code', OverdueLoanDemoSeeder::BOOKING_CODE)
            ->firstOrFail();

        $this->assertSame('Approved', $loan->status);
        $this->assertSame($student->id_user, $loan->id_user);
        $this->assertGreaterThan(5, $loan->overdue_days);
        $this->assertSame(7, $loan->overdue_days);
        $this->assertSame($loan->overdue_days * 5000, $loan->estimated_fine);
        $this->assertSame($originalStock - 1, $equipment->fresh()->stok_tersedia);
        $this->assertCount(1, $loan->detail->first()->penyerahanDetail);
        $this->assertSame(1, Peminjaman::where('booking_code', OverdueLoanDemoSeeder::BOOKING_CODE)->count());

        $this->actingAs($technician)->get(route('technician.returns'))
            ->assertOk()
            ->assertSee(OverdueLoanDemoSeeder::BOOKING_CODE)
            ->assertSee("Terlambat {$loan->overdue_days} hari")
            ->assertSee(number_format($loan->estimated_fine, 0, ',', '.'));

        $this->actingAs($admin)->get(route('admin.reports'))
            ->assertOk()
            ->assertSee(OverdueLoanDemoSeeder::BOOKING_CODE)
            ->assertSee("Terlambat · {$loan->overdue_days} hari")
            ->assertSee('Estimasi')
            ->assertSee(number_format($loan->estimated_fine, 0, ',', '.'));

        $detail = $loan->detail->first();
        $this->actingAs($technician)->post(route('technician.returns.store'), [
            'id_peminjaman' => $loan->id_peminjaman,
            'booking_code' => $loan->booking_code,
            'id_detail' => $detail->id_detail,
            'asset_id' => 'DEMO-KOM-002-001',
            'jumlah' => 1,
            'idempotency_key' => '00000000-0000-4000-8000-000000000075',
            'kode_alat' => $equipment->kode_alat,
            'kondisi_alat' => 'Baik',
            'foto_bukti' => $this->fakeImage('demo-return.jpg'),
        ])->assertRedirect(route('technician.returns'))
            ->assertSessionHas('success', 'Semua alat pada transaksi telah kembali. Denda: Rp 35.000');

        $this->assertSame('Selesai', $loan->fresh()->status);
        $this->assertSame($originalStock, $equipment->fresh()->stok_tersedia);
        $this->assertDatabaseHas('detail_pengembalian', [
            'id_detail' => $detail->id_detail,
            'denda' => 35000,
        ]);
    }
}
