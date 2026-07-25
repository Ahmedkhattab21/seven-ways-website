<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // Temporary presentation-only placeholders. Replace with real module data later.
        $statistics = [
            ['label' => 'مبيعات اليوم', 'value' => '—', 'hint' => 'لا توجد بيانات بعد', 'icon' => 'trend'],
            ['label' => 'أوامر العمل المفتوحة', 'value' => '0', 'hint' => 'لم يتم تفعيل الموديول', 'icon' => 'clipboard'],
            ['label' => 'السيارات داخل الورشة', 'value' => '0', 'hint' => 'لا توجد بيانات بعد', 'icon' => 'car'],
            ['label' => 'تنبيهات المخزون', 'value' => '0', 'hint' => 'المخزون غير مفعّل', 'icon' => 'alert'],
        ];

        return view('dashboard.index', compact('statistics'));
    }
}
