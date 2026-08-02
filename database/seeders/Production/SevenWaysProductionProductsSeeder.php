<?php

namespace Database\Seeders\Production;

use App\Services\ProductionBootstrap\SevenWaysProductionBootstrap;
use Illuminate\Database\Seeder;

class SevenWaysProductionProductsSeeder extends Seeder
{
    public function run(SevenWaysProductionBootstrap $bootstrap): void
    {
        $bootstrap->products();
    }
}
