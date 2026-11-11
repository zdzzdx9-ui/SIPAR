<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAME_CHANGES = [
        'KOM-001' => ['Laptop Hp', 'Laptop HP'],
        'PRK-001' => ['Obeng mini', 'Obeng Mini'],
        'MIK-001' => ['Arduino uno', 'Arduino Uno'],
        'PRK-003' => ['Bor tembok', 'Bor Tembok'],
        'PRK-004' => ['Bor mini listrik', 'Bor Mini Listrik'],
        'MUL-002' => ['Spiker', 'Speaker'],
    ];

    public function up(): void
    {
        $this->updateNames(1);
    }

    public function down(): void
    {
        $this->updateNames(0);
    }

    private function updateNames(int $targetIndex): void
    {
        DB::transaction(function () use ($targetIndex): void {
            $now = now();

            foreach (self::NAME_CHANGES as $code => $names) {
                DB::table('alat')
                    ->where('kode_alat', $code)
                    ->where('nama_alat', $names[1 - $targetIndex])
                    ->update([
                        'nama_alat' => $names[$targetIndex],
                        'updated_at' => $now,
                    ]);
            }

        });
    }
};
