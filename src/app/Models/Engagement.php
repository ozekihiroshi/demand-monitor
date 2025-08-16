<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'role', 'status', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    // Relations
    public function user()    { return $this->belongsTo(User::class); }
    public function company() { return $this->belongsTo(Company::class); }

    // Scopes
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active')
                 ->whereDate('effective_from', '<=', now())
                 ->where(function($w){
                     $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', now());
                 });
    }
    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }
    public function scopeRoleIn(Builder $q, array $roles): Builder
    {
        return $q->whereIn('role', $roles);
    }
}
