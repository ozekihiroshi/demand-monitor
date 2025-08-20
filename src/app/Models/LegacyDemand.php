<?php
// app/Models/LegacyDemand.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyDemand extends Model {
    protected $connection = 'legacy';   // ← これを忘れずに
    protected $table = 'demand';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $casts = ['date'=>'integer','data'=>'integer','delete_flag'=>'integer'];
    public function scopeForMeter($q, string $code){
        return $q->where('demand_ip',$code)->where('delete_flag',0);
    }
}