<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OfficialInventoryCatalog
{
    /**
     * @return array<int, array{code: string, name: string, category: string, stock: int, description: ?string}>
     */
    public static function items(): array
    {
        return [
            ['code' => 'KOM-001', 'name' => 'Laptop HP', 'category' => 'Komputer', 'stock' => 3, 'description' => 'Komputer portabel untuk praktik pemrograman, pengembangan perangkat lunak, pengolahan dokumen, dan presentasi.'],
            ['code' => 'SIM-001', 'name' => 'Flashdisk', 'category' => 'Penyimpanan', 'stock' => 10, 'description' => 'Media penyimpanan USB portabel untuk menyimpan, mencadangkan, dan memindahkan berkas pembelajaran atau proyek.'],
            ['code' => 'KOM-002', 'name' => 'Mouse', 'category' => 'Komputer', 'stock' => 20, 'description' => 'Perangkat penunjuk untuk menggerakkan kursor, memilih objek, dan menjalankan perintah pada komputer.'],
            ['code' => 'KOM-003', 'name' => 'Keyboard', 'category' => 'Komputer', 'stock' => 10, 'description' => 'Perangkat masukan untuk mengetik kode program, teks, dan perintah saat menggunakan komputer.'],
            ['code' => 'MUL-001', 'name' => 'Proyektor', 'category' => 'Multimedia', 'stock' => 3, 'description' => 'Perangkat presentasi untuk menampilkan keluaran visual dari komputer pada layar atau permukaan yang lebih luas.'],
            ['code' => 'KOM-004', 'name' => 'Monitor', 'category' => 'Komputer', 'stock' => 3, 'description' => 'Layar keluaran visual untuk menampilkan antarmuka, materi, dan hasil kerja komputer.'],
            ['code' => 'PRK-001', 'name' => 'Obeng Mini', 'category' => 'Perkakas', 'stock' => 1, 'description' => 'Set obeng berukuran kecil untuk membuka dan memasang sekrup pada perangkat atau komponen kecil.'],
            ['code' => 'MIK-001', 'name' => 'Arduino Uno', 'category' => 'Mikrokontroler', 'stock' => 30, 'description' => 'Papan mikrokontroler untuk latihan pemrograman dasar, membaca masukan sensor, dan mengendalikan komponen pada rangkaian elektronika.'],
            ['code' => 'PRK-002', 'name' => 'Solder', 'category' => 'Perkakas', 'stock' => 1, 'description' => 'Alat pemanas untuk melelehkan timah dan menyambungkan komponen elektronik pada papan rangkaian. Gunakan dengan pengawasan dan perlengkapan keselamatan.'],
            ['code' => 'PRK-003', 'name' => 'Bor Tembok', 'category' => 'Perkakas', 'stock' => 1, 'description' => 'Perkakas listrik untuk membuat lubang pada dinding atau material keras menggunakan mata bor yang sesuai. Gunakan dengan pengawasan dan perlengkapan keselamatan.'],
            ['code' => 'PRK-004', 'name' => 'Bor Mini Listrik', 'category' => 'Perkakas', 'stock' => 1, 'description' => 'Perkakas bor berukuran ringkas untuk pekerjaan detail dan membuat lubang kecil pada bahan yang sesuai dengan mata bornya.'],
            ['code' => 'MUL-002', 'name' => 'Speaker', 'category' => 'Multimedia', 'stock' => 1, 'description' => 'Perangkat keluaran audio untuk menghasilkan suara dari komputer atau perangkat yang kompatibel.'],
            ['code' => 'MUL-003', 'name' => 'Mikrofon', 'category' => 'Multimedia', 'stock' => 1, 'description' => 'Perangkat masukan audio untuk menangkap suara dalam presentasi dan kegiatan praktik multimedia.'],
            ['code' => 'MUL-004', 'name' => 'Sound System', 'category' => 'Multimedia', 'stock' => 1, 'description' => 'Perangkat audio untuk memperkuat suara dari mikrofon atau sumber audio lain yang kompatibel.'],
        ];
    }

    public static function assertNoActiveLoans(): void
    {
        $count = DB::table('loans')->whereIn('status', ['Pending', 'Approved'])->count();

        if ($count > 0) {
            throw new RuntimeException(
                "Sinkronisasi inventaris dibatalkan: ditemukan {$count} transaksi Pending atau Approved. "
                .'Selesaikan transaksi aktif sebelum mengulang sinkronisasi.'
            );
        }
    }

    public static function sync(): void
    {
        $connection = DB::connection();
        $lockName = 'sipar:official-inventory-sync';
        $hasAdvisoryLock = $connection->getDriverName() === 'mysql';

        if ($hasAdvisoryLock) {
            $lock = $connection->selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockName]);

            if ((int) ($lock->acquired ?? 0) !== 1) {
                throw new RuntimeException('Tidak dapat memperoleh kunci sinkronisasi inventaris.');
            }
        }

        try {
            $connection->transaction(function (): void {
                DB::table('alat')->orderBy('id_alat')->lockForUpdate()->get(['id_alat']);
                self::assertNoActiveLoans();

                $now = now();
                $categories = [];

                foreach (self::items() as $item) {
                    if (! isset($categories[$item['category']])) {
                        $existingCategory = DB::table('kategori_alat')
                            ->where('nama_kategori', $item['category'])
                            ->first();

                        if ($existingCategory) {
                            $categoryId = $existingCategory->id_kategori;
                        } else {
                            $categoryId = DB::table('kategori_alat')->insertGetId([
                                'nama_kategori' => $item['category'],
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        $categories[$item['category']] = $categoryId;
                    }
                }

                DB::table('loan_items')
                    ->whereNotNull('id_alat')
                    ->orderBy('id_detail')
                    ->chunkById(200, function ($details): void {
                        foreach ($details as $detail) {
                            $equipment = DB::table('alat')
                                ->leftJoin('kategori_alat', 'kategori_alat.id_kategori', '=', 'alat.id_kategori')
                                ->where('alat.id_alat', $detail->id_alat)
                                ->first([
                                    'alat.kode_alat',
                                    'alat.nama_alat',
                                    'kategori_alat.nama_kategori',
                                ]);

                            if (! $equipment) {
                                continue;
                            }

                            DB::table('loan_items')->where('id_detail', $detail->id_detail)->update([
                                'alat_snapshot_kode' => $detail->alat_snapshot_kode ?? $equipment->kode_alat,
                                'alat_snapshot_nama' => $detail->alat_snapshot_nama ?? $equipment->nama_alat,
                                'alat_snapshot_kategori' => $detail->alat_snapshot_kategori ?? $equipment->nama_kategori,
                            ]);
                        }
                    }, 'id_detail');

                DB::table('item_units')
                    ->whereNotNull('alat_id')
                    ->orderBy('item_unit_id')
                    ->chunkById(200, function ($units): void {
                        foreach ($units as $unit) {
                            $equipment = DB::table('alat')
                                ->where('id_alat', $unit->alat_id)
                                ->first(['kode_alat', 'nama_alat']);

                            if (! $equipment) {
                                continue;
                            }

                            DB::table('item_units')->where('item_unit_id', $unit->item_unit_id)->update([
                                'alat_snapshot_kode' => $unit->alat_snapshot_kode ?? $equipment->kode_alat,
                                'alat_snapshot_nama' => $unit->alat_snapshot_nama ?? $equipment->nama_alat,
                            ]);
                        }
                    }, 'item_unit_id');

                DB::table('loan_items')->whereNotNull('id_alat')->update(['id_alat' => null]);
                DB::table('item_units')->whereNotNull('alat_id')->update(['alat_id' => null]);
                DB::table('alat')->delete();

                foreach (self::items() as $item) {
                    DB::table('alat')->insert([
                        'kode_alat' => $item['code'],
                        'nama_alat' => $item['name'],
                        'id_kategori' => $categories[$item['category']],
                        'stok_total' => $item['stock'],
                        'stok_tersedia' => $item['stock'],
                        'stok_maintenance' => 0,
                        'stok_rusak' => 0,
                        'kondisi' => 'Baik',
                        'deskripsi' => $item['description'],
                        'foto' => null,
                        'qr_code' => (string) Str::uuid(),
                        'status_alat' => 'Tersedia',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('kategori_alat')->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('alat')
                        ->whereColumn('alat.id_kategori', 'kategori_alat.id_kategori');
                })->delete();
            });
        } finally {
            if ($hasAdvisoryLock) {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
        }
    }
}
