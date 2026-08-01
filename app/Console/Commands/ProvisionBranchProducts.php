<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionBranchProducts extends Command
{
    protected $signature = 'catalog:provision-branch-products
        {branch : Branch ID}
        {--apply : Persist the missing links}
        {--unavailable : Create links as unavailable}';

    protected $description = 'Preview or safely provision missing branch-product availability links';

    public function handle(): int
    {
        $branch = Branch::query()->findOrFail((int) $this->argument('branch'));
        $missing = Product::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->whereDoesntHave('branchProducts', fn ($query) => $query->where('branch_id', $branch->id))
            ->orderBy('id')
            ->get(['id', 'sku', 'name', 'is_sellable']);

        $this->info("Branch: {$branch->name}");
        $this->info("Missing products: {$missing->count()}");
        $this->table(['ID', 'SKU', 'Name'], $missing->map->only(['id', 'sku', 'name'])->all());
        if (! $this->option('apply')) {
            $this->warn('Preview only. Re-run with --apply to create availability links.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($branch, $missing) {
            foreach ($missing as $product) {
                BranchProduct::query()->firstOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $product->id],
                    [
                        'company_id' => $branch->company_id,
                        'is_available' => ! $this->option('unavailable'),
                        'is_sellable' => ! $this->option('unavailable') && $product->is_sellable,
                    ]
                );
            }
        });
        $this->info('Branch-product links created. No prices, stock, or documents were changed.');

        return self::SUCCESS;
    }
}
