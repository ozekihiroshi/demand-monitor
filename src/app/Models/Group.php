<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name','notes'];

    public function users() {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('role');
    }

    public function meters() {
        return $this->hasMany(Meter::class);
    }
}

