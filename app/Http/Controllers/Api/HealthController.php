<?php

namespace App\Http\Controllers\Api;

use App\Core\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                'Application health check failed',
                ['database' => ['unavailable']],
                503,
                ['application' => 'ok', 'database' => 'unavailable']
            );
        }

        return ApiResponse::success(
            ['application' => 'ok', 'database' => 'ok'],
            'Application is healthy'
        );
    }

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseReady(),
            'cache' => $this->cacheReady(),
            'storage' => $this->storageReady(),
            'queue' => $this->queueReady(),
        ];
        $ready = ! in_array('unavailable', $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    private function databaseReady(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (Throwable $exception) {
            report($exception);

            return 'unavailable';
        }
    }

    private function cacheReady(): string
    {
        $key = 'health:'.Str::uuid();
        $result = 'unavailable';

        try {
            Cache::put($key, 'ok', 10);
            $result = Cache::get($key) === 'ok' ? 'ok' : 'unavailable';
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Cache::forget($key);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $result;
    }

    private function storageReady(): string
    {
        $path = 'private/health/'.Str::uuid().'.tmp';
        $result = 'unavailable';

        try {
            $result = Storage::disk('local')->put($path, 'ok') ? 'ok' : 'unavailable';
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $result;
    }

    private function queueReady(): string
    {
        try {
            $connection = (string) config('queue.default');
            if ($connection === 'database') {
                return Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))
                    ? 'ok'
                    : 'unavailable';
            }

            return array_key_exists($connection, (array) config('queue.connections')) ? 'ok' : 'unavailable';
        } catch (Throwable $exception) {
            report($exception);

            return 'unavailable';
        }
    }
}
