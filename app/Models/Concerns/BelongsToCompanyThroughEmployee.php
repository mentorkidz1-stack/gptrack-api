<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isole un modèle qui n'a pas de `company_id` propre mais référence un
 * `employee_id` (ex: Attendance, DailyPriority) : restreint aux employés
 * de l'entreprise de l'utilisateur authentifié.
 */
trait BelongsToCompanyThroughEmployee
{
    protected static function bootBelongsToCompanyThroughEmployee(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $user = request()->user();
            if ($user && !empty($user->company_id)) {
                $builder->whereIn(
                    $builder->getModel()->getTable() . '.employee_id',
                    Employee::withoutGlobalScopes()
                        ->where('company_id', $user->company_id)
                        ->select('id')
                );
            }
        });
    }
}
