<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'nama' => 'Admin SIPAR',
                'nis_nip' => 'ADMIN01',
                'email' => 'admin@sipar.sch.id',
                'password' => config('user-seeding.admin_password'),
                'role' => 'admin',
            ],
            [
                'nama' => 'Siswa Test',
                'nis_nip' => '12345',
                'email' => 'siswa@sipar.sch.id',
                'password' => config('user-seeding.student_password'),
                'role' => 'siswa',
            ],
            [
                'nama' => 'Teknisi SIPAR',
                'nis_nip' => config('user-seeding.technician_username'),
                'email' => config('user-seeding.technician_email'),
                'password' => config('user-seeding.technician_password'),
                'role' => 'teknisi',
            ],
        ];

        foreach ($accounts as $account) {
            if (! is_string($account['password']) || $account['password'] === '') {
                throw new RuntimeException(
                    'Atur SIPAR_ADMIN_PASSWORD, SIPAR_TEST_STUDENT_PASSWORD, dan TECHNICIAN_PASSWORD sebagai environment secrets sebelum menjalankan UserSeeder.'
                );
            }
        }

        if (strlen($accounts[2]['password']) < 10) {
            throw new RuntimeException('TECHNICIAN_PASSWORD harus memiliki panjang minimal 10 karakter.');
        }

        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                $nisOwner = User::query()
                    ->where('nis_nip', $account['nis_nip'])
                    ->where('email', '!=', $account['email'])
                    ->exists();

                if ($nisOwner) {
                    throw new RuntimeException(
                        "NIS/NIP {$account['nis_nip']} sudah digunakan oleh akun dengan email lain."
                    );
                }

                $user = User::query()
                    ->where('email', $account['email'])
                    ->lockForUpdate()
                    ->first();

                $attributes = [
                    'nama' => $account['nama'],
                    'nis_nip' => $account['nis_nip'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                ];

                if ($user) {
                    $user->update($attributes);

                    continue;
                }

                User::create($attributes + [
                    'email' => $account['email'],
                ]);
            }
        });
    }
}
