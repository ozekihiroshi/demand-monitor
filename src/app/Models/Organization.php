<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model {
    protected $fillable = ['name'];
    public function facilities(){ return $this->hasMany(Facility::class); }
    public function users(){ return $this->belongsToMany(User::class)->withTimestamps()->withPivot('role'); }
}

