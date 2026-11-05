<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@sipar.sch.id');
        if (! User::where('email', $adminEmail)->exists()) {
            $adminPassword = env('ADMIN_PASSWORD');
            if (! is_string($adminPassword) || strlen($adminPassword) < 10) {
                throw new RuntimeException('ADMIN_PASSWORD wajib diatur dan minimal 10 karakter sebelum akun admin pertama dibuat.');
            }

            User::create([
                'nama' => 'Admin SIPAR',
                'nis_nip' => env('ADMIN_USERNAME', 'ADMIN'),
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]);
        }
        $technicianPassword = env('TECHNICIAN_PASSWORD');
        if (! is_string($technicianPassword) || strlen($technicianPassword) < 10) {
            throw new RuntimeException('TECHNICIAN_PASSWORD wajib diatur dan minimal 10 karakter sebelum seeder dijalankan.');
        }
        User::updateOrCreate(['email' => env('TECHNICIAN_EMAIL', 'teknisi@sipar.sch.id')], [
            'nama' => 'Teknisi SIPAR', 'nis_nip' => env('TECHNICIAN_USERNAME', 'teknisi'),
            'password' => Hash::make($technicianPassword), 'role' => 'teknisi',
        ]);
        User::firstOrCreate(['email' => 'siswa@sipar.sch.id'], [
            'nama' => 'Rangga Saputra', 'nis_nip' => '20230001',
            'password' => Hash::make('password123'), 'role' => 'siswa',
        ]);

        $this->call(OfficialInventorySeeder::class);
    }
}
