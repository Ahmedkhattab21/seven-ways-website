<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentRequest;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function storeForCustomer(AttachmentRequest $request, Customer $customer, AttachmentService $service): RedirectResponse
    {
        $service->store($customer, $request->file('file'), $request->input('category'));

        return back()->with('status', 'تم رفع المرفق.');
    }

    public function storeForVehicle(AttachmentRequest $request, Vehicle $vehicle, AttachmentService $service): RedirectResponse
    {
        $service->store($vehicle, $request->file('file'), $request->input('category'));

        return back()->with('status', 'تم رفع المرفق.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment, AttachmentService $service): RedirectResponse
    {
        $this->authorize('delete', $attachment);
        $service->delete($attachment);

        return back()->with('status', 'تم حذف المرفق.');
    }
}
