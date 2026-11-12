<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_peminjaman_item_unit', function (Blueprint $table): void {
            $table->index('item_unit_id', 'detail_peminjaman_item_unit_item_unit_id_index');
            $table->dropUnique('detail_peminjaman_item_unit_item_unit_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('detail_peminjaman_item_unit', function (Blueprint $table): void {
            $table->unique('item_unit_id', 'detail_peminjaman_item_unit_item_unit_id_unique');
            $table->dropIndex('detail_peminjaman_item_unit_item_unit_id_index');
        });
    }
};
