<?php
// app/Models/Provider.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Provider extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'valid_from', 'valid_until'])
            ->withTimestamps();
    }

    public function providers()
    {
        return $this->belongsToMany(\App\Models\Provider::class)
            ->withPivot(['role', 'valid_from', 'valid_until'])
            ->withTimestamps();
    }
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}

