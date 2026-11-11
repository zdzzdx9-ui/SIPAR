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
            $table->timestamp('status_read_at')->nullable();
            $table->index(['id_user', 'status', 'status_read_at'], 'loans_student_status_read_index');
        });

        DB::table('loans')
            ->whereNull('status_read_at')
            ->update(['status_read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropIndex('loans_student_status_read_index');
            $table->dropColumn('status_read_at');
        });
    }
};
