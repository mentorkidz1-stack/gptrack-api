<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une notion individuelle du programme officiel ("Activité n°1",
 * "Sous-activité n°4.2"...), extraite du PDF ou ajoutée/corrigée
 * manuellement — sélectionnable par l'enseignant lors de l'attestation
 * d'un cours, au lieu d'un simple pourcentage global de la semaine.
 */
class CurriculumNotion extends Model
{
    protected $fillable = [
        'curriculum_week_id',
        'label',
        'text',
        'order',
    ];

    public function week()
    {
        return $this->belongsTo(CurriculumWeek::class, 'curriculum_week_id');
    }

    public function attendances()
    {
        return $this->belongsToMany(Attendance::class, 'attendance_notion');
    }
}
