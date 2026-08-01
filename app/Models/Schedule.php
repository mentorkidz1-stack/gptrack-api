<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyThroughEmployee;
use Illuminate\Database\Eloquent\Model;

/**
 * Créneau hebdomadaire d'un enseignant (jour, heure, matière, classe).
 * Distinct du pointage arrivée/départ général : un enseignant peut avoir
 * plusieurs créneaux le même jour, à des heures variables.
 */
class Schedule extends Model
{
    use BelongsToCompanyThroughEmployee;

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    protected $fillable = [
        'employee_id',
        'subject_id',
        'class_level_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
