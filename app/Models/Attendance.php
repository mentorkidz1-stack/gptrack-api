<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompanyThroughEmployee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory, BelongsToCompanyThroughEmployee;

    protected $fillable = [
        'employee_id',
        'site_id',
        'attendance_type',
        'latitude',
        'longitude',
        'selfie_photo',
        'face_match_score',
        'is_inside_zone',
        'status',
        'check_time',
        'work_minutes',
        'is_late',
        // Attestation de cours (cahier de texte) — NULL pour un pointage
        // classique, tous inchangés.
        'schedule_id',
        'curriculum_week_id',
        'taux_realise',
        'notes',
    ];

    protected $casts = [
        'is_inside_zone' => 'boolean',
        'is_late' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'face_match_score' => 'float',
        'work_minutes' => 'integer',
        'check_time' => 'datetime',
        'taux_realise' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function curriculumWeek()
    {
        return $this->belongsTo(CurriculumWeek::class);
    }
}