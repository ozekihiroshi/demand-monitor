<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    // 既存 + 追加カラムを明示
    protected $fillable = [
        'facility_id',
        'code',
        'name',
        'channel',
        'pulse_per_kwh',       // ← 8/10 migration ではこちら。※後述
        'config',
        'legacy_uid',
        'group_id',
        'rate_override',
        'threshold_override',
        // 'demand_ip',        // ← 書き込みは避けたいので fillable には入れない
    ];

    protected $casts = [
        'config'             => 'array',
        'rate_override'      => 'decimal:4',
        'threshold_override' => 'decimal:4',
    ];

    /* ===== Relations ===== */
    public function facility()  { return $this->belongsTo(Facility::class); }
    public function demands()   { return $this->hasMany(Demand::class); }
    public function group()     { return $this->belongsTo(Group::class); }

    // （任意）旧DBユーザを引く場合
    // public function legacyUser(){ return $this->belongsTo(\App\Models\Legacy\LegacyUser::class, 'legacy_uid', 'uid'); }

    /* ===== 互換レイヤ：旧名を読めるようにする ===== */

    // 旧画面が demand_ip を読む場合でも code を返す（なければDBの demand_ip）
    public function getDemandIpAttribute(): ?string
    {
        return $this->attributes['code'] ?? ($this->attributes['demand_ip'] ?? null);
    }

    // 旧コードが demand_ip に書こうとした場合の挙動（互換重視で code も揃える）
    public function setDemandIpAttribute($value): void
    {
        // 将来は例外にしても良い（厳格運用）
        $this->attributes['demand_ip'] = $value;
        $this->attributes['code']      = $this->attributes['code'] ?? $value;
    }

    /* ===== 命名揺れの暫定吸収（pulse_const → pulse_per_kwh） ===== */

    // 既存コードが pulse_const を読むと pulse_per_kwh を返す
    public function getPulseConstAttribute(): ?int
    {
        return $this->attributes['pulse_per_kwh'] ?? null;
    }

    // 既存コードが pulse_const に書いた場合も pulse_per_kwh に保存
    public function setPulseConstAttribute($value): void
    {
        $this->attributes['pulse_per_kwh'] = $value;
    }

    /* ===== “実効値”の算出アクセサ（UIで便利） ===== */

    public function getEffectiveRateAttribute(): ?float
    {
        // 例：新DBに上書きがあればそれ、なければ旧DBの値を使う…など
        return $this->rate_override ?? null;
    }

    public function getEffectiveThresholdAttribute(): ?float
    {
        return $this->threshold_override ?? null;
    }
}

