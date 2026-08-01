<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Isole automatiquement un modèle qui possède une colonne `company_id` :
 * toute lecture est restreinte à l'entreprise de l'utilisateur authentifié
 * (DG/RH ou employé, les deux modèles portent `company_id`), et toute
 * création reçoit automatiquement ce `company_id` sans jamais faire
 * confiance à une valeur envoyée par le client.
 *
 * Sans utilisateur authentifié (ex: recherche d'employé par téléphone
 * avant connexion), aucun filtre n'est appliqué : c'est voulu, la
 * recherche par téléphone doit rester globale à ce stade du flux.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = static::currentCompanyId();
            if ($companyId !== null) {
                $builder->where(
                    $builder->getModel()->getTable() . '.company_id',
                    $companyId
                );
            }
        });

        static::creating(function ($model) {
            $companyId = static::currentCompanyId();
            if ($companyId !== null && empty($model->company_id)) {
                $model->company_id = $companyId;
            }
        });
    }

    protected static function currentCompanyId(): ?int
    {
        $user = request()->user();
        return ($user && !empty($user->company_id)) ? (int) $user->company_id : null;
    }
}
