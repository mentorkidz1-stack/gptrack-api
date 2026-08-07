<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Une semaine de la progression officielle pour une matière+classe
 * (extraite d'une "Fiche de suivi de la progression" du Ministère, ou
 * corrigée manuellement). Le cumulé prévu/réalisé n'est jamais stocké ici
 * — toujours recalculé à la volée à partir de `taux_prevu` (chronologique)
 * et des `Attendance::taux_realise` liées, pour ne jamais diverger de la
 * réalité.
 */
class CurriculumWeek extends Model
{
    use BelongsToCompany;

    protected $casts = [
        'trimester' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'taux_prevu' => 'float',
        'is_teaching_week' => 'boolean',
    ];

    protected $fillable = [
        'company_id',
        'subject_id',
        'class_level_id',
        'trimester',
        'period_start',
        'period_end',
        'situation_apprentissage',
        'activities_text',
        'taux_prevu',
        'is_teaching_week',
    ];

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

    public function notions()
    {
        return $this->hasMany(CurriculumNotion::class)->orderBy('order');
    }
}
