<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([...$this->only('email', 'password'), 'status' => 'active'], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $this->throwAuthenticationException();
        }

        $user = Auth::user();
        if (! $user->hasRole('system_admin')
            && (! $user->company?->is_active || ! $this->hasAccessibleBranch($user))) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            $this->throwAuthenticationException();
        }

        RateLimiter::clear($this->throttleKey());
    }

    private function hasAccessibleBranch(User $user): bool
    {
        if ($user->isCompanyAdministrator()) {
            return $user->company->branches()->where('is_active', true)->exists();
        }

        return $user->company->branches()->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->whereKey($user->branch_id)
                    ->orWhereHas('accessibleUsers', fn ($access) => $access
                        ->whereKey($user->id)->where('user_branch_access.can_view', true));
            })->exists();
    }

    private function throwAuthenticationException(): never
    {
        throw ValidationException::withMessages([
            'email' => 'بيانات تسجيل الدخول غير صحيحة.',
        ]);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "محاولات كثيرة. حاول مرة أخرى بعد {$seconds} ثانية.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
