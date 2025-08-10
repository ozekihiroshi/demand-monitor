<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meter extends Model {
    protected $fillable = ['facility_id','code','demand_ip','pulse_const'];
    public function facility(){ return $this->belongsTo(Facility::class); }
    public function demands(){ return $this->hasMany(Demand::class); }
}

