<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
            $table->index(['status', 'approved_at']);
        });

        DB::table('loans')->where('status', 'Approved')->update([
            'approved_at' => DB::raw('updated_at'),
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Expired', 'Selesai') NOT NULL DEFAULT 'Pending'");
        }
    }

    public function down(): void
    {
        DB::table('loans')->where('status', 'Expired')->update(['status' => 'Cancelled']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loans MODIFY status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Selesai') NOT NULL DEFAULT 'Pending'");
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->dropIndex(['status', 'approved_at']);
            $table->dropColumn('approved_at');
        });
    }
};
