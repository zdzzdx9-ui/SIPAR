<?php

namespace Database\Seeders;

use App\Models\Alat;
use App\Models\DetailPeminjaman;
use App\Models\DetailPenyerahan;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OverdueLoanDemoSeeder extends Seeder
{
    public const BOOKING_CODE = 'PJM-DEMO-TERLAMBAT';

    public function run(): void
    {
        if (Peminjaman::where('booking_code', self::BOOKING_CODE)->exists()) {
            return;
        }

        $student = User::where('role', 'siswa')->orderBy('id_user')->first();
        $technician = User::where('role', 'teknisi')->orderBy('id_user')->first();
        $equipment = Alat::where('kode_alat', 'KOM-002')->first();

        if (! $student || ! $technician || ! $equipment) {
            throw new RuntimeException('Data siswa, teknisi, dan alat KOM-002 harus tersedia sebelum membuat transaksi demo keterlambatan.');
        }

        $loanDate = today()->subDays(12);
        $returnDate = today()->subDays(7);

        DB::transaction(function () use ($student, $technician, $equipment, $loanDate, $returnDate): void {
            $lockedEquipment = Alat::whereKey($equipment->id_alat)->lockForUpdate()->firstOrFail();
            if ($lockedEquipment->status_alat !== 'Tersedia' || $lockedEquipment->stok_tersedia < 1) {
                throw new RuntimeException('Alat KOM-002 harus memiliki stok tersedia sebelum membuat transaksi demo keterlambatan.');
            }

            $loan = Peminjaman::create([
                'id_user' => $student->id_user,
                'tanggal_pengajuan' => $loanDate->copy()->setTime(8, 0),
                'tanggal_pinjam' => $loanDate,
                'tanggal_kembali_rencana' => $returnDate,
                'keperluan' => 'Transaksi demo untuk menguji pengembalian terlambat dan perhitungan denda.',
                'status' => 'Approved',
                'booking_code' => self::BOOKING_CODE,
                'overdue_flag' => true,
                'reminder_flag' => true,
            ]);

            $detail = DetailPeminjaman::create([
                'id_peminjaman' => $loan->id_peminjaman,
                'id_alat' => $lockedEquipment->id_alat,
                'jumlah' => 1,
                'alat_snapshot_kode' => $lockedEquipment->kode_alat,
                'alat_snapshot_nama' => $lockedEquipment->nama_alat,
                'alat_snapshot_kategori' => $lockedEquipment->kategori->nama_kategori,
            ]);

            DetailPenyerahan::create([
                'id_detail' => $detail->id_detail,
                'asset_id' => 'DEMO-KOM-002-001',
                'id_teknisi' => $technician->id_user,
                'jumlah' => 1,
                'tanggal_penyerahan' => $loanDate->copy()->setTime(9, 0),
                'kondisi_alat' => 'Baik',
                'catatan' => 'Penyerahan unit untuk skenario demo keterlambatan.',
            ]);

            $lockedEquipment->stok_tersedia--;
            $lockedEquipment->syncStatusFromStock();
            $lockedEquipment->save();
        });
    }
}
