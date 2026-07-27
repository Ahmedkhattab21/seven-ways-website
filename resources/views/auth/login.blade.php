@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="sw-login">
        <section class="sw-login__visual" aria-label="Seven Ways ERP">
            <div class="sw-login__glow"></div>
            <x-brand />
            <div class="sw-login__statement">
                <span class="sw-eyebrow">SEVEN WAYS OPERATIONS</span>
                <h1>كل تفاصيل التشغيل.<br><em>في مسار واحد.</em></h1>
                <p>منصة موحّدة لإدارة أعمال Seven Ways بكفاءة ووضوح، من أول دخول وحتى آخر تقرير.</p>
            </div>
            <div class="sw-login__features">
                <span><x-icon name="lock" :size="17" /> جلسة آمنة</span>
                <span><x-icon name="dashboard" :size="17" /> تجربة موحّدة</span>
                <span><x-icon name="trend" :size="17" /> جاهز للنمو</span>
            </div>
        </section>

        <section class="sw-login__form-panel">
            <div class="sw-login__mobile-brand"><x-brand /></div>
            <div class="sw-login__form-wrap">
                <span class="sw-eyebrow">مرحبًا بعودتك</span>
                <h2>تسجيل الدخول إلى النظام</h2>
                <p>أدخل بيانات حسابك للوصول إلى لوحة التحكم.</p>

                @if(session('status'))
                    <x-alert type="success">{{ session('status') }}</x-alert>
                @endif

                <form method="POST" action="{{ route('login') }}" class="sw-form" data-loading-form>
                    @csrf
                    <x-form.input name="email" type="email" label="البريد الإلكتروني" required
                        autocomplete="email" inputmode="email" placeholder="name@sevenways.com" autofocus>
                        <x-slot name="suffix">
                            <span class="sw-field__icon"><x-icon name="mail" :size="19" /></span>
                        </x-slot>
                    </x-form.input>

                    <x-form.input name="password" type="password" label="كلمة المرور" required
                        autocomplete="current-password" placeholder="أدخل كلمة المرور" data-password-input>
                        <x-slot name="suffix">
                            <button class="sw-field__action" type="button" data-password-toggle aria-label="إظهار كلمة المرور">
                                <x-icon name="eye" :size="19" />
                            </button>
                        </x-slot>
                    </x-form.input>

                    <div class="sw-form__row">
                        <x-form.checkbox name="remember" label="تذكرني على هذا الجهاز" />
                        <span class="sw-form__muted">استعادة كلمة المرور غير مفعّلة</span>
                    </div>

                    <x-button type="submit" class="sw-button--block" data-submit-button>
                        <span data-button-label>دخول آمن</span>
                        <span class="sw-button__loading" data-button-loading hidden>
                            <x-spinner size="sm" /> جارٍ التحقق...
                        </span>
                    </x-button>
                </form>
                <p class="sw-login__support">تحتاج مساعدة؟ تواصل مع مسؤول النظام.</p>
            </div>
        </section>
    </div>
@endsection
