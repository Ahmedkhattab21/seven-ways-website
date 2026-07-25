<?php

namespace App\Http\Controllers;

use App\Services\WarrantyVerificationService;
use Illuminate\View\View;

class WarrantyVerificationController extends Controller
{
    public function __invoke(string $token, WarrantyVerificationService $service): View
    {
        return view('warranties.verify', ['verification' => $service->verify($token)]);
    }
}
