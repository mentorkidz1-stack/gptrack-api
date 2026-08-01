<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Implémente Authenticatable (pas seulement HasApiTokens) : plusieurs
 * mécanismes internes de Laravel — dont le middleware `throttle` sur une
 * route authentifiée — appellent getAuthIdentifier() sur $request->user()
 * et plantent sinon dès qu'un Employee (plutôt qu'un User) est connecté.
 */
class Employee extends Model implements Authenticatable
{
    use HasFactory, HasApiTokens, BelongsToCompany, AuthenticatableTrait;

    // Jamais renvoyés en JSON, même via une relation chargée ailleurs
    // (ex: Schedule::with('employee')) : la photo de référence pèse
    // plusieurs centaines de Ko en base64, et l'OTP / les tokens d'appareil
    // n'ont rien à faire dans une réponse API lue par le dashboard.
    protected $hidden = [
        'reference_photo',
        'otp',
        'otp_expires_at',
        'device_token',
        'fcm_token',
        'device_id',
    ];

    protected $fillable = [
        'company_id',
        'site_id',
        'full_name',
        'phone',

        'reference_photo',
        'device_id',
        'device_token',
        'fcm_token',

        'is_enrolled',
        'status',

        'otp',
        'otp_expires_at',
        'phone_verified',
        'enrolled_at',

        'job_title',
        'employee_code',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}