<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentNumberService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function next(
        string $documentType,
        int $companyId,
        ?int $branchId = null,
        CarbonInterface|string|null $date = null
    ): string {
        $this->assertTenant($companyId, $branchId);
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?: now());

        return DB::transaction(function () use ($documentType, $companyId, $branchId, $date) {
            $sequences = DocumentSequence::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $template = $sequences->sortByDesc('id')->first();
            if (! $template) {
                $typeLabel = config("document_sequences.types.{$documentType}.label", $documentType);
                $branchLabel = $branchId
                    ? Branch::query()->where('company_id', $companyId)->find($branchId)?->name
                    : 'كل الشركة';

                throw ValidationException::withMessages([
                    'document_type' => "لا يوجد تسلسل نشط لمستند «{$typeLabel}» في الفرع «{$branchLabel}». أضف التسلسل من الإعدادات ثم أعد المحاولة.",
                ]);
            }

            $periodKey = $this->periodKey($template->reset_period, $date);
            $sequence = $sequences->firstWhere('period_key', $periodKey);
            if (! $sequence) {
                $sequence = $template->replicate(['current_number', 'period_key', 'scope_key']);
                $sequence->current_number = 0;
                $sequence->period_key = $periodKey;
                $sequence->scope_key = self::scopeKey($companyId, $branchId, $documentType, $periodKey);
                $sequence->save();
            }

            $sequence->increment('current_number');
            $sequence->refresh();

            return $this->render($sequence, $date);
        });
    }

    public function assertConfigured(string $documentType, int $companyId, ?int $branchId = null): void
    {
        $this->assertTenant($companyId, $branchId);

        $exists = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new BusinessRuleException(
                $documentType === 'work_order'
                    ? 'تسلسل أرقام أوامر العمل غير مُعد لهذا الفرع.'
                    : 'لا يوجد تسلسل أرقام نشط لهذا النوع من المستندات.'
            );
        }
    }

    public static function scopeKey(int $companyId, ?int $branchId, string $documentType, ?string $periodKey): string
    {
        return implode(':', [$companyId, $branchId ?: 0, $documentType, $periodKey ?: 'never']);
    }

    public function periodKey(string $resetPeriod, CarbonInterface $date): ?string
    {
        return match ($resetPeriod) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->format('Ym'),
            default => null,
        };
    }

    private function assertTenant(int $companyId, ?int $branchId): void
    {
        if ($this->tenant->companyId() !== $companyId) {
            throw ValidationException::withMessages(['company_id' => 'الشركة خارج السياق الحالي.']);
        }

        if ($branchId) {
            $branch = Branch::query()->find($branchId);
            if (! $branch || ! $this->tenant->user()?->canAccessBranch($branch)) {
                throw ValidationException::withMessages(['branch_id' => 'الفرع خارج السياق الحالي.']);
            }
        }
    }

    private function render(DocumentSequence $sequence, CarbonInterface $date): string
    {
        $company = Company::query()->findOrFail($sequence->company_id);
        $branch = $sequence->branch_id ? Branch::query()->findOrFail($sequence->branch_id) : null;
        $tokens = [
            '{COMPANY}' => Str::upper(Str::slug($company->name)),
            '{BRANCH}' => $branch?->code ?? 'ALL',
            '{TYPE}' => Str::upper($sequence->document_type),
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ];

        return strtr($sequence->prefix, $tokens)
            .str_pad((string) $sequence->current_number, $sequence->padding, '0', STR_PAD_LEFT);
    }
}
