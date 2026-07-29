<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentActionRequest;
use App\Http\Requests\QuotationActionRequest;
use App\Http\Requests\QuotationApprovalRequest;
use App\Http\Requests\QuotationVersionRequest;
use App\Models\Quotation;
use App\Services\QuotationAcceptanceService;
use App\Services\QuotationApprovalService;
use App\Services\QuotationToAppointmentService;
use App\Services\QuotationVersionService;
use Illuminate\Http\RedirectResponse;

class QuotationActionController extends Controller
{
    public function submit(QuotationApprovalRequest $request, Quotation $quotation, QuotationApprovalService $service): RedirectResponse
    {
        $this->authorize('submit', $quotation);
        $service->submit($quotation, $request->approval_notes);

        return back()->with('success', 'تم إرسال العرض للاعتماد.');
    }

    public function approve(QuotationApprovalRequest $request, Quotation $quotation, QuotationApprovalService $service): RedirectResponse
    {
        $this->authorize('approve', $quotation);
        $service->approve($quotation, $request->approval_notes);

        return back()->with('success', 'تم اعتماد العرض.');
    }

    public function approvalReject(QuotationApprovalRequest $request, Quotation $quotation, QuotationApprovalService $service): RedirectResponse
    {
        $this->authorize('approve', $quotation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->reject($quotation, $data['reason']);

        return back()->with('success', 'تم رفض الاعتماد.');
    }

    public function send(Quotation $quotation, QuotationAcceptanceService $service): RedirectResponse
    {
        $this->authorize('send', $quotation);
        $service->send($quotation);

        return back()->with('success', 'تم تسجيل إرسال العرض دون إرسال خارجي.');
    }

    public function accept(QuotationActionRequest $request, Quotation $quotation, QuotationAcceptanceService $service): RedirectResponse
    {
        $this->authorize('accept', $quotation);
        $data = $request->validate(['acceptance_method' => ['required', 'in:in_person,phone,whatsapp,email,system']]);
        $service->accept($quotation, $request->validated() + $data);

        return back()->with('success', 'تم تسجيل قبول العميل.');
    }

    public function reject(QuotationActionRequest $request, Quotation $quotation, QuotationAcceptanceService $service): RedirectResponse
    {
        $this->authorize('reject', $quotation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->reject($quotation, $data['reason']);

        return back()->with('success', 'تم تسجيل رفض العميل.');
    }

    public function cancel(QuotationActionRequest $request, Quotation $quotation, QuotationAcceptanceService $service): RedirectResponse
    {
        $this->authorize('cancel', $quotation);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->cancel($quotation, $data['reason']);

        return back()->with('success', 'تم إلغاء العرض.');
    }

    public function version(QuotationVersionRequest $request, Quotation $quotation, QuotationVersionService $service): RedirectResponse
    {
        $this->authorize('createVersion', $quotation);
        $version = $service->create($quotation, $request->validated('reason'));

        return redirect()->route('quotations.edit', $version)->with('success', 'تم إنشاء إصدار Draft جديد.');
    }

    public function appointment(
        AppointmentActionRequest $request,
        Quotation $quotation,
        QuotationToAppointmentService $service
    ): RedirectResponse {
        $this->authorize('accept', $quotation);
        $data = $request->validate([
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'assigned_employee_id' => ['nullable', 'integer'], 'priority' => ['required', 'in:low,normal,high,urgent'],
            'deposit_required' => ['sometimes', 'boolean'], 'deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'scheduled_start.required' => 'حدد بداية الموعد.',
            'scheduled_start.date' => 'بداية الموعد غير صحيحة.',
            'scheduled_start.after' => 'يجب أن تكون بداية الموعد بعد الوقت الحالي.',
            'scheduled_end.required' => 'حدد نهاية الموعد.',
            'scheduled_end.date' => 'نهاية الموعد غير صحيحة.',
            'scheduled_end.after' => 'يجب أن تكون نهاية الموعد بعد بداية الموعد.',
        ]);
        $appointment = $service->convert($quotation, $data);

        return redirect()->route('appointments.show', $appointment)->with('success', 'تم تحويل العرض إلى حجز.');
    }
}
