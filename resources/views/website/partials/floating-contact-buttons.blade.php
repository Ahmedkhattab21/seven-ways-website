@php($primaryBranch = config('website.branches.0'))
@php($callPhone = config('website.contact.call_phone'))
@php($whatsappUrl = config('website.contact.whatsapp_url'))
@if ($primaryBranch)
    <div class="sw-floating-contact" aria-label="{{ __('website.contact.quick_actions') }}">
        <a
            class="sw-floating-contact__button sw-floating-contact__button--phone"
            href="tel:{{ $callPhone }}"
            aria-label="{{ __('website.contact.call_now') }}"
        >
            <img src="{{ asset('assets/website/icons/01d680a1c802fcae.svg') }}" alt="">
        </a>
        <a
            class="sw-floating-contact__button sw-floating-contact__button--whatsapp"
            href="{{ $whatsappUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="{{ __('website.contact.whatsapp') }}"
        >
            <img src="{{ asset('assets/website/icons/42d70ce663d09b42.svg') }}" alt="">
        </a>
    </div>
@endif
