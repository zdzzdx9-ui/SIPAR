<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('peminjaman', 'loans');
        Schema::rename('detail_peminjaman', 'loan_items');

        Schema::table('loans', function (Blueprint $table): void {
            $table->string('booking_code', 32)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id_user')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('operational_notes')->nullable();
            $table->boolean('overdue_flag')->default(false);
            $table->boolean('reminder_flag')->default(false);
        });

        Schema::table('loan_items', function (Blueprint $table): void {
            $table->json('asset_ids')->nullable();
            $table->json('item_conditions')->nullable();
        });

        DB::table('loans')->orderBy('id_peminjaman')->chunkById(200, function ($loans): void {
            foreach ($loans as $loan) {
                DB::table('loans')->where('id_peminjaman', $loan->id_peminjaman)->update([
                    'booking_code' => 'PJM-'.str_pad((string) $loan->id_peminjaman, 8, '0', STR_PAD_LEFT),
                    'user_id' => $loan->id_user,
                    'start_date' => $loan->tanggal_pinjam,
                    'end_date' => $loan->tanggal_kembali_rencana,
                ]);
            }
        }, 'id_peminjaman');

        Schema::table('loans', function (Blueprint $table): void {
            $table->unique('booking_code');
            $table->index(['status', 'end_date']);
        });

        Schema::table('detail_penyerahan', function (Blueprint $table): void {
            $table->string('asset_id', 80)->nullable();
        });
        Schema::table('detail_pengembalian', function (Blueprint $table): void {
            $table->string('asset_id', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detail_penyerahan', function (Blueprint $table): void {
            $table->dropColumn('asset_id');
        });
        Schema::table('detail_pengembalian', function (Blueprint $table): void {
            $table->dropColumn('asset_id');
        });
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropIndex(['status', 'end_date']);
            $table->dropUnique(['booking_code']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'booking_code',
                'user_id',
                'start_date',
                'end_date',
                'operational_notes',
                'overdue_flag',
                'reminder_flag',
            ]);
        });
        Schema::table('loan_items', function (Blueprint $table): void {
            $table->dropColumn(['asset_ids', 'item_conditions']);
        });

        Schema::rename('loan_items', 'detail_peminjaman');
        Schema::rename('loans', 'peminjaman');
    }
};
