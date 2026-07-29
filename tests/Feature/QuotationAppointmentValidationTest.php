<?php

namespace Tests\Feature;

use App\Http\Requests\AppointmentActionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class QuotationAppointmentValidationTest extends TestCase
{
    public function test_past_appointment_start_has_an_arabic_validation_message(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');
        try {
            $request = new AppointmentActionRequest;

            $validator = Validator::make([
                'scheduled_start' => '2026-07-29T11:00',
                'scheduled_end' => '2026-07-29T13:00',
            ], $request->rules(), $request->messages());

            $this->assertSame(
                'يجب أن تكون بداية الموعد بعد الوقت الحالي.',
                $validator->errors()->first('scheduled_start')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_quotation_form_preserves_values_and_prevents_past_times_in_the_browser(): void
    {
        $view = file_get_contents(resource_path('views/quotations/show.blade.php'));

        $this->assertStringContainsString("value=\"{{ old('scheduled_start') }}\"", $view);
        $this->assertStringContainsString("value=\"{{ old('scheduled_end') }}\"", $view);
        $this->assertStringContainsString('min="{{ now()->addMinute()', $view);
        $this->assertStringContainsString('الموعد لازم يكون بعد الوقت الحالي', $view);
    }
}
