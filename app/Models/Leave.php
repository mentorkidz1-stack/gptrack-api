<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyThroughEmployee;
use Illuminate\Database\Eloquent\Model;

/**
 * Congé/absence planifiée d'un employé — pour qu'une absence déclarée à
 * l'avance ne soit jamais comptée comme un retard/absence non justifiée
 * dans les rapports, le statut en direct ou les alertes.
 */
class Leave extends Model
{
    use BelongsToCompanyThroughEmployee;

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'type',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
