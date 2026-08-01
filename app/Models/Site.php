<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToCompany;

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'integer',
        'late_tolerance_minutes' => 'integer',
        'require_selfie' => 'boolean',
        'require_face_verification' => 'boolean',
    ];

    protected $fillable = [
       'company_id',
    'name',
    'latitude',
    'longitude',
    'radius',

    'work_start_time',
    'work_end_time',
    'late_tolerance_minutes',

    'require_selfie',
    'require_face_verification'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}