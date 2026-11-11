<?php

namespace Database\Seeders;

use App\Support\OfficialInventoryCatalog;
use Illuminate\Database\Seeder;

class OfficialInventorySeeder extends Seeder
{
    public function run(): void
    {
        OfficialInventoryCatalog::sync();
    }
}
