<?php
// app/Models/Meter.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meter extends Model
{
    use SoftDeletes;

    // 既存 + 追加カラムを明示（demand_ip は書かせないため入れない）
    protected $fillable = [
        'facility_id',
        'code',
        'name',
        'channel',
        'pulse_per_kwh',      // 8/10 migration に合わせる
        'config',
        'legacy_uid',
        'group_id',
        'rate_override',
        'threshold_override',
    ];

    protected $casts = [
        'config'             => 'array',
        'rate_override'      => 'decimal:4',  // 現状は数値として運用
        'threshold_override' => 'decimal:4',  // 現状は数値として運用
        'pulse_per_kwh'      => 'integer',
    ];

    /* ===== Relations ===== */
    public function facility() { return $this->belongsTo(Facility::class); }
    public function demands()  { return $this->hasMany(Demand::class); }
    public function group()    { return $this->belongsTo(Group::class); }

    /* ===== 互換レイヤ：旧名を読めるようにする ===== */

    // 旧画面が demand_ip を読む場合でも code を返す（なければDBの demand_ip）
    public function getDemandIpAttribute(): ?string
    {
        return $this->attributes['code'] ?? ($this->attributes['demand_ip'] ?? null);
    }

    // 旧コードが demand_ip に書こうとした場合（互換重視で code も揃える）
    public function setDemandIpAttribute($value): void
    {
        // 将来的に例外にしても良い（厳格運用）
        $this->attributes['demand_ip'] = $value;
        $this->attributes['code']      = $this->attributes['code'] ?? $value;
    }

    // 命名揺れの暫定吸収（pulse_const → pulse_per_kwh）
    public function getPulseConstAttribute(): ?int
    {
        return $this->attributes['pulse_per_kwh'] ?? null;
    }
    public function setPulseConstAttribute($value): void
    {
        $this->attributes['pulse_per_kwh'] = $value;
    }

    /* ===== “実効値”の算出アクセサ（UIで便利） ===== */
    public function getEffectiveRateAttribute(): ?float
    {
        return $this->rate_override ?? null;
    }
    public function getEffectiveThresholdAttribute(): ?float
    {
        return $this->threshold_override ?? null;
    }

    /* ===== ルーティングは code をキーに ===== */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /* ===== code は作成後に変更不可 ===== */
    public function setCodeAttribute($value): void
    {
        if ($this->exists && $this->getOriginal('code') !== null) {
            return; // 既存は変更しない
        }
        $this->attributes['code'] = $value;
    }
}
