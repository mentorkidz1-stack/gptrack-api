<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function curriculumWeeks()
    {
        return $this->hasMany(CurriculumWeek::class);
    }
}
