<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyThroughEmployee;
use Illuminate\Database\Eloquent\Model;

/**
 * Rituel du jour (matin + soir), régime Priorités.
 *
 * Zones de confidentialité (doc "Interface employé" §1.1) — appliquées ici,
 * pas seulement à l'affichage : aucun contrôleur DG/RH ne doit jamais
 * sélectionner les champs marqués Zone 3.
 *
 * Zone 1 (remonte au responsable, dans sa portée) :
 *   main_priority, secondary_1, secondary_2, obstacle_self, obstacle_other,
 *   skipped, evening_status, evening_note, secondary_1_done, secondary_2_done,
 *   evening_obstacle_self, evening_obstacle_other, evening_smooth_day.
 *
 * Zone 3 (for intérieur — personne, sauf l'employé et un futur coach IA dédié) :
 *   parade, private_reflection, ai_evening_answer.
 *
 * Aujourd'hui : aucun endpoint DG/RH ne lit ce modèle du tout (voir
 * DashboardController/ReportController/EmployeeController) — l'étanchéité
 * est donc totale par omission. Le jour où une vue manager sur le rituel
 * existera, elle devra explicitement exclure les champs Zone 3 ci-dessus.
 */
class DailyPriority extends Model
{
   use BelongsToCompanyThroughEmployee;

   protected $fillable = [
        'employee_id',
        'priority_date',
        'main_priority',
        'secondary_1',
        'secondary_2',
        'obstacle_self',
        'obstacle_other',
        'parade',
        'skipped',

        'evening_status',
        'evening_note',
        'secondary_1_done',
        'secondary_2_done',
        'evening_obstacle_self',
        'evening_obstacle_other',
        'evening_smooth_day',
        'private_reflection',
        'evening_completed_at',
        'ai_evening_question',
        'ai_evening_answer',
    ];

    protected $casts = [
        'priority_date' => 'date',
        'skipped' => 'boolean',
        'secondary_1_done' => 'boolean',
        'secondary_2_done' => 'boolean',
        'evening_smooth_day' => 'boolean',
        'evening_completed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}