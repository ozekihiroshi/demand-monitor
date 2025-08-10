<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model {
    protected $fillable = ['facility_id','type','token','label','threshold_kw'];
    public function facility(){ return $this->belongsTo(Facility::class); }
}

