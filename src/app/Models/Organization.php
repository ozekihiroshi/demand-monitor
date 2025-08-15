<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model {
    use HasFactory;
    protected $fillable = ['name'];
    public function facilities(){ return $this->hasMany(Facility::class); }
    public function users(){ return $this->belongsToMany(User::class)->withTimestamps()->withPivot('role'); }
}

