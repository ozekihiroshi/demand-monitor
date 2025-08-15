<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name','notes'];

    public function users() {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot('role');
    }

    public function meters() {
        return $this->hasMany(Meter::class);
    }

    protected static function newFactory()
    {
        return \Database\Factories\GroupFactory::new();
    }
}


