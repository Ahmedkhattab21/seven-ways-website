<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\RegistrationRequest;
use App\Mail\WebsiteContactMessage;
use App\Models\WebsiteRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RegistrationController extends Controller
{
    public function store(RegistrationRequest $request): RedirectResponse
    {
        $registration = $request->safe()->except('website');
        $savedRegistration = WebsiteRegistration::query()->create([
            ...$registration,
            'locale' => app()->getLocale(),
        ]);
        $recipient = config('website.contact.recipient');
        $service = __('website.registration.services.'.$registration['service']);
        $country = __('website.registration.countries.'.$registration['country']);

        $message = collect([
            __('website.registration.fields.country').': '.$country,
            __('website.registration.fields.city').': '.$registration['city'],
            __('website.registration.fields.vehicle_type').': '.$registration['vehicle_type'],
            __('website.registration.fields.vehicle_model').': '.$registration['vehicle_model'],
            __('website.registration.fields.vehicle_year').': '.($registration['vehicle_year'] ?? '—'),
            __('website.registration.fields.service').': '.$service,
            __('website.registration.fields.notes').': '.($registration['notes'] ?? '—'),
        ])->implode(PHP_EOL);

        $contact = [
            'name' => $registration['full_name'],
            'phone' => $registration['phone'],
            'email' => $registration['email'] ?? null,
            'branch' => $registration['preferred_branch'] ?? null,
            'subject' => __('website.registration.email_subject'),
            'message' => $message,
        ];

        try {
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Website contact recipient is not configured.');
            }

            Mail::to($recipient)->send(new WebsiteContactMessage($contact));

            $savedRegistration->update(['email_sent_at' => now()]);
        } catch (Throwable $exception) {
            Log::error('Website registration delivery failed.', [
                'exception' => $exception,
                'registration_id' => $savedRegistration->getKey(),
            ]);
        }

        return back()->with('registration_success', __('website.registration.success'));
    }
}
