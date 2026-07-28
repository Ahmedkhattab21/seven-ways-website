<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyProductionAssets extends Command
{
    protected $signature = 'production:verify-assets';

    protected $description = 'Verify the Vite manifest and local public website assets';

    public function handle(): int
    {
        $paths = collect(config('website.assets', []))
            ->filter(fn ($path) => is_string($path))
            ->merge(collect(config('website.service_media', []))->pluck('video'))
            ->filter()
            ->unique();
        $missing = $paths
            ->filter(fn (string $path) => ! is_file(public_path(ltrim($path, '/'))))
            ->values();

        if (! is_file(public_path('build/manifest.json'))) {
            $missing->push('build/manifest.json');
        }

        if ($missing->isNotEmpty()) {
            $missing->each(fn (string $path) => $this->error("Missing public asset: {$path}"));

            return self::FAILURE;
        }

        $this->info("Production asset verification passed ({$paths->count()} configured assets).");

        return self::SUCCESS;
    }
}
