<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Model;

class ReferenceDataPolicy
{
    public function update(User $user, Model $model): bool
    {
        $permission = match (true) {
            $model instanceof Tax => 'taxes.manage',
            $model instanceof Unit => 'units.manage',
            $model instanceof PaymentMethod => 'payment_methods.manage',
            $model instanceof VehicleSize, $model instanceof VehicleType => 'vehicle_references.manage',
            $model instanceof FiscalYear => 'fiscal_years.manage',
            $model instanceof DocumentSequence => 'document_sequences.manage',
            $model instanceof Currency, $model instanceof VehicleBrand, $model instanceof VehicleModel => null,
            default => null,
        };

        if (! $permission || ! $user->hasPermission($permission)) {
            return false;
        }

        return isset($model->company_id) && (int) $model->company_id === (int) $user->company_id;
    }
}
