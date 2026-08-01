<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'type',
        'email',
        'phone',
        'subscription_plan',
        'subscription_status',
        'trial_ends_at',
        'status',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'status' => 'boolean',
    ];

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function planConfig(): array
    {
        return config('plans.' . $this->subscription_plan) ?? config('plans.starter');
    }

    public function employeeLimit(): ?int
    {
        return $this->planConfig()['max_employees'];
    }

    public function siteLimit(): ?int
    {
        return $this->planConfig()['max_sites'];
    }

    public function isTrialExpired(): bool
    {
        return $this->subscription_status === 'trialing'
            && $this->trial_ends_at !== null
            && now()->greaterThan($this->trial_ends_at);
    }

    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, ['trialing', 'active'], true)
            && !$this->isTrialExpired();
    }
}