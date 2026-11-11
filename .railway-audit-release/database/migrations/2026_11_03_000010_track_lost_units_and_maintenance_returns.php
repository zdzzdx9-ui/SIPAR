<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE item_units MODIFY status ENUM('Tersedia', 'Dipinjam', 'Hilang') NOT NULL DEFAULT 'Tersedia'");
            DB::statement("ALTER TABLE pengembalian MODIFY kondisi_alat ENUM('Baik', 'Maintenance', 'Rusak', 'Hilang') NOT NULL");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('item_units', function (Blueprint $table): void {
                $table->enum('status', ['Tersedia', 'Dipinjam', 'Hilang'])->default('Tersedia')->change();
            });
            Schema::table('pengembalian', function (Blueprint $table): void {
                $table->enum('kondisi_alat', ['Baik', 'Maintenance', 'Rusak', 'Hilang'])->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('item_units')->where('status', 'Hilang')->update([
            'status' => 'Tersedia',
            'condition' => 'Rusak',
        ]);
        DB::table('pengembalian')->where('kondisi_alat', 'Maintenance')->update([
            'kondisi_alat' => 'Baik',
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE item_units MODIFY status ENUM('Tersedia', 'Dipinjam') NOT NULL DEFAULT 'Tersedia'");
            DB::statement("ALTER TABLE pengembalian MODIFY kondisi_alat ENUM('Baik', 'Rusak', 'Hilang') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('item_units', function (Blueprint $table): void {
                $table->enum('status', ['Tersedia', 'Dipinjam'])->default('Tersedia')->change();
            });
            Schema::table('pengembalian', function (Blueprint $table): void {
                $table->enum('kondisi_alat', ['Baik', 'Rusak', 'Hilang'])->change();
            });
        }
    }
};
