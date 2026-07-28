<?php

namespace App\Services;

class MigrationSafetyScanner
{
    public function scan(string $source): array
    {
        if (! preg_match('/function\s+up\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{(.*?)function\s+down\s*\(/s', $source, $match)) {
            return ['Unable to isolate up()'];
        }

        $up = $match[1];
        $patterns = [
            'DROP operation' => '/(?:Schema::drop|->drop(?:Column|Foreign|Index|Unique|Primary)|\bDROP\s+(?:TABLE|COLUMN|INDEX|DATABASE)\b)/i',
            'RENAME operation' => '/(?:Schema::rename|->renameColumn|\bRENAME\s+(?:TABLE|COLUMN)\b)/i',
            'TRUNCATE operation' => '/(?:->truncate\s*\(|\bTRUNCATE\s+TABLE\b)/i',
        ];

        return collect($patterns)
            ->filter(fn (string $pattern) => preg_match($pattern, $up) === 1)
            ->keys()
            ->values()
            ->all();
    }
}
