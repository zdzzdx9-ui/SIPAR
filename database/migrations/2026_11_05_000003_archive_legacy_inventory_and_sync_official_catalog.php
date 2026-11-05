<?php

use App\Support\OfficialInventoryCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        OfficialInventoryCatalog::assertNoActiveLoans();

        Schema::table('loan_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_items', 'alat_snapshot_kode')) {
                $table->string('alat_snapshot_kode')->nullable();
            }
            if (! Schema::hasColumn('loan_items', 'alat_snapshot_nama')) {
                $table->string('alat_snapshot_nama')->nullable();
            }
            if (! Schema::hasColumn('loan_items', 'alat_snapshot_kategori')) {
                $table->string('alat_snapshot_kategori')->nullable();
            }
        });

        Schema::table('item_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('item_units', 'alat_snapshot_kode')) {
                $table->string('alat_snapshot_kode')->nullable();
            }
            if (! Schema::hasColumn('item_units', 'alat_snapshot_nama')) {
                $table->string('alat_snapshot_nama')->nullable();
            }
        });

        $this->makeInventoryReferenceNullable('loan_items', 'id_alat');
        $this->makeInventoryReferenceNullable('item_units', 'alat_id');

        OfficialInventoryCatalog::sync();
    }

    private function makeInventoryReferenceNullable(string $tableName, string $columnName): void
    {
        $foreignKey = collect(Schema::getForeignKeys($tableName))
            ->first(fn (array $key): bool => in_array($columnName, $key['columns'], true));
        $needsForeignKey = ! $foreignKey || strtolower((string) $foreignKey['on_delete']) !== 'set null';

        if ($foreignKey && $needsForeignKey) {
            $foreignKeyIdentifier = Schema::getConnection()->getDriverName() === 'sqlite'
                ? [$columnName]
                : $foreignKey['name'];
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($foreignKeyIdentifier));
        }

        $column = collect(Schema::getColumns($tableName))
            ->first(fn (array $item): bool => $item['name'] === $columnName);

        if (! ($column['nullable'] ?? false)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger($columnName)->nullable()->change());
        }

        if ($needsForeignKey) {
            Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
                $table->foreign($columnName)->references('id_alat')->on('alat')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('loan_items')->whereNull('id_alat')->exists()
            || DB::table('item_units')->whereNull('alat_id')->exists()) {
            throw new RuntimeException(
                'Migrasi arsip inventaris tidak dapat dibatalkan otomatis karena data alat lama telah diarsipkan. '
                .'Pulihkan database dari backup sebelum migrasi jika rollback diperlukan.'
            );
        }

        Schema::table('item_units', function (Blueprint $table): void {
            $table->dropForeign(['alat_id']);
            $table->unsignedBigInteger('alat_id')->nullable(false)->change();
            $table->foreign('alat_id')->references('id_alat')->on('alat')->restrictOnDelete();
            $table->dropColumn(['alat_snapshot_kode', 'alat_snapshot_nama']);
        });

        Schema::table('loan_items', function (Blueprint $table): void {
            $table->dropForeign(['id_alat']);
            $table->unsignedBigInteger('id_alat')->nullable(false)->change();
            $table->foreign('id_alat')->references('id_alat')->on('alat')->restrictOnDelete();
            $table->dropColumn(['alat_snapshot_kode', 'alat_snapshot_nama', 'alat_snapshot_kategori']);
        });
    }
};
