<?php

namespace App\Services\CentralApproval\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ApprovalHandler
{
    public function modelClass(): string;

    public function module(): string;

    public function pendingStatus(): string;

    public function permission(): string;

    public function documentNumber(Model $document): ?string;

    public function amount(Model $document): ?string;

    public function currencyId(Model $document): ?int;

    public function branchId(Model $document): ?int;

    public function requesterId(Model $document): int;

    public function approve(Model $document): void;

    public function supportsReject(): bool;

    public function reject(Model $document, string $reason): void;

    public function route(Model $document): ?string;
}
