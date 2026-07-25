<?php

namespace App\Http\Controllers\Api;

use App\Core\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
}
