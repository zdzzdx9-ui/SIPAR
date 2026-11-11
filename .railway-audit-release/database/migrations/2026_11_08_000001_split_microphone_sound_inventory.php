<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $microphone = DB::table('alat')->where('kode_alat', 'MUL-003')->lockForUpdate()->first();
            $soundSystem = DB::table('alat')->where('kode_alat', 'MUL-004')->lockForUpdate()->first();

            if ($microphone?->nama_alat === 'Mikrofon' && $soundSystem?->nama_alat === 'Sound System') {
                return;
            }

            if (! $microphone || $microphone->nama_alat !== 'Mic + sound' || $soundSystem) {
                throw new RuntimeException(
                    'Pemisahan inventaris dibatalkan: data MUL-003 atau MUL-004 tidak sesuai katalog lama. '
                    .'Periksa katalog sebelum menjalankan migrasi.'
                );
            }

            $activeLoans = DB::table('loan_items')
                ->join('loans', 'loans.id_peminjaman', '=', 'loan_items.id_peminjaman')
                ->where('loan_items.id_alat', $microphone->id_alat)
                ->whereIn('loans.status', ['Pending', 'Approved'])
                ->count();

            if ($activeLoans > 0) {
                throw new RuntimeException(
                    'Pemisahan inventaris dibatalkan: masih ada transaksi Pending atau Approved untuk Mic + sound. '
                    .'Selesaikan transaksi tersebut sebelum deploy ulang.'
                );
            }

            DB::table('loan_items')
                ->where('id_alat', $microphone->id_alat)
                ->orderBy('id_detail')
                ->chunkById(200, function ($details) use ($microphone): void {
                    foreach ($details as $detail) {
                        DB::table('loan_items')->where('id_detail', $detail->id_detail)->update([
                            'id_alat' => null,
                            'alat_snapshot_kode' => $detail->alat_snapshot_kode ?? $microphone->kode_alat,
                            'alat_snapshot_nama' => $detail->alat_snapshot_nama ?? $microphone->nama_alat,
                            'alat_snapshot_kategori' => $detail->alat_snapshot_kategori ?? 'Multimedia',
                        ]);
                    }
                }, 'id_detail');

            DB::table('item_units')
                ->where('alat_id', $microphone->id_alat)
                ->orderBy('item_unit_id')
                ->chunkById(200, function ($units) use ($microphone): void {
                    foreach ($units as $unit) {
                        DB::table('item_units')->where('item_unit_id', $unit->item_unit_id)->update([
                            'alat_id' => null,
                            'alat_snapshot_kode' => $unit->alat_snapshot_kode ?? $microphone->kode_alat,
                            'alat_snapshot_nama' => $unit->alat_snapshot_nama ?? $microphone->nama_alat,
                        ]);
                    }
                }, 'item_unit_id');

            $now = now();
            $microphoneDescription = 'Perangkat input audio untuk menangkap suara pembicara dalam presentasi dan kegiatan praktik multimedia.';
            $soundSystemDescription = 'Perangkat audio untuk memperkuat dan memperdengarkan suara dari mikrofon atau sumber audio yang kompatibel.';

            DB::table('alat')->where('id_alat', $microphone->id_alat)->update([
                'nama_alat' => 'Mikrofon',
                'stok_total' => 1,
                'stok_tersedia' => 1,
                'stok_maintenance' => 0,
                'stok_rusak' => 0,
                'kondisi' => 'Baik',
                'deskripsi' => $microphoneDescription,
                'status_alat' => 'Tersedia',
                'updated_at' => $now,
            ]);

            DB::table('alat')->insert([
                'kode_alat' => 'MUL-004',
                'nama_alat' => 'Sound System',
                'id_kategori' => $microphone->id_kategori,
                'stok_total' => 1,
                'stok_tersedia' => 1,
                'stok_maintenance' => 0,
                'stok_rusak' => 0,
                'kondisi' => 'Baik',
                'deskripsi' => $soundSystemDescription,
                'foto' => null,
                'qr_code' => (string) Str::uuid(),
                'status_alat' => 'Tersedia',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Pemisahan inventaris Mikrofon dan Sound System tidak dapat dibatalkan otomatis. '
            .'Pulihkan database dari backup sebelum migrasi jika rollback diperlukan.'
        );
    }
};
