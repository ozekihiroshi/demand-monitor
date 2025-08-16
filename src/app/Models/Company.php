<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $fillable = ['slug', 'name'];

    public function facilities()
    {return $this->hasMany(Facility::class);}

    public function engagements()
    {
        return $this->hasMany(\App\Models\Engagement::class);
    }

    public function engineers() // or admins()
    {
        return $this->belongsToMany(\App\Models\User::class, 'engagements')
            ->withPivot(['role', 'status', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

}
