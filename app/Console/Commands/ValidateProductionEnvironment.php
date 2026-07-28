<?php

namespace App\Console\Commands;

use App\Services\ProductionEnvironmentValidator;
use Illuminate\Console\Command;

class ValidateProductionEnvironment extends Command
{
    protected $signature = 'production:validate-env';

    protected $description = 'Validate production runtime configuration without printing secrets';

    public function handle(ProductionEnvironmentValidator $validator): int
    {
        $errors = $validator->errors();
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Production environment validation passed.');

        return self::SUCCESS;
    }
}
