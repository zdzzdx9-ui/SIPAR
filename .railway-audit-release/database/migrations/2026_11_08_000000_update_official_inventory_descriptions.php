<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DESCRIPTIONS = [
        'KOM-001' => 'Komputer portabel untuk praktik pemrograman, pengembangan perangkat lunak, pengolahan dokumen, dan presentasi.',
        'SIM-001' => 'Media penyimpanan USB portabel untuk menyimpan, mencadangkan, dan memindahkan berkas pembelajaran atau proyek.',
        'KOM-002' => 'Perangkat penunjuk untuk menggerakkan kursor, memilih objek, dan menjalankan perintah pada komputer.',
        'KOM-003' => 'Perangkat input untuk mengetik kode program, teks, dan perintah saat menggunakan komputer.',
        'MUL-001' => 'Perangkat presentasi untuk menampilkan keluaran visual dari komputer pada layar atau permukaan yang lebih luas.',
        'KOM-004' => 'Layar keluaran visual untuk menampilkan antarmuka, materi, dan hasil kerja komputer.',
        'PRK-001' => 'Set obeng berukuran kecil untuk membuka dan memasang sekrup pada perangkat atau komponen berukuran kecil.',
        'MIK-001' => 'Papan mikrokontroler untuk latihan pemrograman dasar, membaca input sensor, dan mengendalikan komponen pada rangkaian elektronika.',
        'PRK-002' => 'Alat pemanas untuk melelehkan timah dan menyambungkan komponen elektronik pada papan rangkaian. Gunakan dengan pengawasan dan perlengkapan keselamatan.',
        'PRK-003' => 'Perkakas listrik untuk membuat lubang pada dinding atau material keras menggunakan mata bor yang sesuai. Gunakan dengan pengawasan dan perlengkapan keselamatan.',
        'PRK-004' => 'Perkakas bor berukuran ringkas untuk pekerjaan detail dan membuat lubang kecil pada bahan yang sesuai dengan mata bornya.',
        'MUL-002' => 'Perangkat keluaran suara untuk memperdengarkan audio dari komputer atau sumber suara yang kompatibel.',
        'MUL-003' => 'Perangkat mikrofon dan audio untuk menangkap serta memperdengarkan suara dalam presentasi atau kegiatan multimedia; pastikan konektor dan perangkat pendukung sesuai.',
    ];

    private const PREVIOUS_DESCRIPTIONS = [
        'KOM-001' => 'Laptop untuk pemrograman, pengolahan dokumen, dan kegiatan praktik komputer.',
        'SIM-001' => 'Media penyimpanan portabel untuk menyimpan dan memindahkan berkas.',
        'KOM-002' => 'Perangkat input untuk menggerakkan kursor dan mengoperasikan komputer.',
        'KOM-003' => 'Perangkat input untuk mengetik dan memberi perintah pada komputer.',
        'MUL-001' => 'Menampilkan materi atau presentasi dari komputer ke layar yang lebih besar.',
        'KOM-004' => 'Layar untuk menampilkan keluaran visual dari komputer.',
        'PRK-001' => 'Set obeng berukuran kecil untuk pekerjaan perakitan dan perbaikan perangkat.',
        'MIK-001' => 'Papan mikrokontroler untuk praktik rangkaian dan pemrograman elektronika dasar.',
        'PRK-002' => 'Perkakas pemanas untuk menyambungkan komponen elektronik dengan timah solder.',
        'PRK-003' => 'Perkakas listrik untuk membuat lubang pada dinding atau material keras.',
        'PRK-004' => 'Bor berukuran ringkas untuk pekerjaan detail pada kegiatan praktik.',
        'MUL-002' => 'Perangkat keluaran audio untuk memutar suara dari komputer atau perangkat lain.',
        'MUL-003' => 'Perangkat mikrofon dan audio untuk presentasi atau kegiatan multimedia.',
    ];

    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $code => $description) {
            DB::table('alat')->where('kode_alat', $code)->update(['deskripsi' => $description]);
        }
    }

    public function down(): void
    {
        foreach (self::PREVIOUS_DESCRIPTIONS as $code => $description) {
            DB::table('alat')->where('kode_alat', $code)->update(['deskripsi' => $description]);
        }
    }
};
