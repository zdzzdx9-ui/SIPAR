<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_and_updates_requested_accounts_with_hashed_passwords(): void
    {
        config([
            'user-seeding.admin_password' => 'admin-secret-for-test',
            'user-seeding.student_password' => 'student-secret-for-test',
        ]);

        $existingAdmin = User::factory()->create([
            'nama' => 'Nama Lama',
            'nis_nip' => 'ADMIN',
            'email' => 'admin@sipar.sch.id',
            'role' => 'admin',
        ]);
        $existingStudent = User::factory()->create([
            'nama' => 'Siswa Lama',
            'nis_nip' => '20230001',
            'email' => 'siswa@sipar.sch.id',
            'role' => 'siswa',
        ]);

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::where('email', 'admin@sipar.sch.id')->firstOrFail();
        $student = User::where('email', 'siswa@sipar.sch.id')->firstOrFail();

        $this->assertSame($existingAdmin->id_user, $admin->id_user);
        $this->assertSame('Admin SIPAR', $admin->nama);
        $this->assertSame('ADMIN01', $admin->nis_nip);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('admin-secret-for-test', $admin->password));

        $this->assertSame($existingStudent->id_user, $student->id_user);
        $this->assertSame('Siswa Test', $student->nama);
        $this->assertSame('12345', $student->nis_nip);
        $this->assertSame('siswa', $student->role);
        $this->assertTrue(Hash::check('student-secret-for-test', $student->password));
        $this->assertSame(
            2,
            User::whereIn('email', ['admin@sipar.sch.id', 'siswa@sipar.sch.id'])->count()
        );
    }

    public function test_seeder_requires_password_environment_secrets(): void
    {
        config([
            'user-seeding.admin_password' => null,
            'user-seeding.student_password' => null,
        ]);
        $existingUserCount = User::count();

        try {
            $this->seed(UserSeeder::class);
            $this->fail('Seeder harus berhenti jika password environment tidak diatur.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SIPAR_ADMIN_PASSWORD', $exception->getMessage());
        }

        $this->assertSame($existingUserCount, User::count());
    }
}
