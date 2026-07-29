<?php

namespace App\Http\Controllers;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\AppointmentConfirmed;
use App\Http\Requests\AppointmentActionRequest;
use App\Http\Requests\AppointmentDepositRequest;
use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Services\AppointmentCancellationService;
use App\Services\AppointmentCheckInService;
use App\Services\AppointmentDepositService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AppointmentActionController extends Controller
{
    public function confirm(Appointment $appointment, TenantContext $tenant, AuditService $audit): RedirectResponse
    {
        $this->authorize('confirm', $appointment);
        DB::transaction(function () use ($appointment, $tenant, $audit) {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if ($locked->status !== 'pending') {
                throw new BusinessRuleException('Only pending appointments can be confirmed.');
            }
            $locked->forceFill(['status' => 'confirmed', 'updated_by' => $tenant->user()?->id])->save();
            $audit->record('appointment.confirmed', $locked);
            DB::afterCommit(fn () => event(new AppointmentConfirmed($locked->id)));
        });

        return back()->with('success', 'تم تأكيد الحجز.');
    }

    public function checkIn(
        AppointmentActionRequest $request,
        Appointment $appointment,
        AppointmentCheckInService $service
    ): RedirectResponse {
        $this->authorize('checkIn', $appointment);
        $isRecovery = $appointment->status === 'checked_in';
        $workOrder = $service->checkIn(
            $appointment,
            $request->safe()->only(['arrival_notes', 'odometer_snapshot'])
        );

        $message = match (true) {
            ! $workOrder->wasRecentlyCreated => 'يوجد أمر عمل مرتبط بهذا الحجز بالفعل. تم فتح أمر العمل الحالي.',
            $isRecovery => 'تم استكمال إنشاء أمر العمل بنجاح.',
            default => 'تم تسجيل وصول العميل وإنشاء أمر العمل بنجاح.',
        };

        return redirect()->route('work-orders.show', $workOrder)->with('success', $message);
    }

    public function cancel(
        AppointmentActionRequest $request,
        Appointment $appointment,
        AppointmentCancellationService $service
    ): RedirectResponse {
        $this->authorize('cancel', $appointment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel($appointment, $data['reason'], $request->deposit_decision);

        return back()->with('success', 'تم إلغاء الحجز تشغيليًا.');
    }

    public function noShow(
        AppointmentActionRequest $request,
        Appointment $appointment,
        AppointmentCancellationService $service
    ): RedirectResponse {
        $this->authorize('noShow', $appointment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->noShow($appointment, $data['reason'], $request->deposit_decision);

        return back()->with('success', 'تم تسجيل عدم حضور العميل.');
    }

    public function deposit(
        AppointmentDepositRequest $request,
        Appointment $appointment,
        AppointmentDepositService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('appointment_deposits.record'), 403);
        $service->record($appointment, $request->validated());

        return back()->with('success', 'تم تسجيل العربون كسجل تشغيلي فقط دون أثر محاسبي.');
    }

    public function cancelDeposit(
        AppointmentActionRequest $request,
        AppointmentDeposit $appointmentDeposit,
        AppointmentDepositService $service
    ): RedirectResponse {
        $this->authorize('cancel', $appointmentDeposit);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel($appointmentDeposit, $data['reason']);

        return back()->with('success', 'تم إلغاء سجل العربون دون حذفه.');
    }
}
