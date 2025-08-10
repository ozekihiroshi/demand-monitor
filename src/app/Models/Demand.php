<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Demand extends Model {
    protected $fillable = ['meter_id','ts','kw','kw_30m'];
    protected $casts = ['ts'=>'datetime'];
    public function meter(){ return $this->belongsTo(Meter::class); }
}

