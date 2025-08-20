<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'name', 'address'];

    public function organization()
    {return $this->belongsTo(Organization::class);}

    public function meters()
    {return $this->hasMany(Meter::class);}

    public function devices()
    {return $this->hasMany(Device::class);}

    public function company()
    {return $this->belongsTo(\App\Models\Company::class);}
}
