<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ContactRequest;
use App\Mail\WebsiteContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('website');
        $recipient = config('website.contact.recipient');

        try {
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Website contact recipient is not configured.');
            }

            Mail::to($recipient)->send(new WebsiteContactMessage($data));
        } catch (Throwable $exception) {
            Log::error('Website contact message delivery failed.', [
                'exception' => get_class($exception),
            ]);

            return back()
                ->withInput($request->safe()->except(['website']))
                ->with('contact_error', __('website.flash.contact_error'));
        }

        return back()->with('contact_success', __('website.flash.contact_success'));
    }
}
