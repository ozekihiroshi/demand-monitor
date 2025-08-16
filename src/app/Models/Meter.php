<?php
// app/Models/Meter.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meter extends Model
{
    use HasFactory, SoftDeletes;

    // 既存 + 追加カラムを明示（demand_ip は書かせないため入れない）
    protected $fillable = [
        'facility_id',
        'code',
        'name',
        'kind',     
        'channel',
        'pulse_per_kwh', // 8/10 migration に合わせる
        'config',
        'legacy_uid',
        'group_id',
        'rate_override',
        'threshold_override',
    ];

    protected $casts = [
        'config'             => 'array',
        'rate_override'      => 'array',   // JSONを配列で扱う
        'threshold_override' => 'integer', // 数値は integer で十分
        'pulse_per_kwh'      => 'integer',
    ];

    /* ===== Relations ===== */
    public function facility()
    {return $this->belongsTo(Facility::class);}

    public function demands()
    {return $this->hasMany(Demand::class);}

    public function group()
    {return $this->belongsTo(Group::class);}



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
    public function getEffectiveRateAttribute()
    {
        return $this->rate_override ?? $this->facility?->default_rate;
    }

    public function getEffectiveThresholdAttribute(): ?int
    {
        return $this->threshold_override ?? $this->facility?->default_threshold;
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

    // ★ 追加：Factory を明示（将来 MeterFactory を使うときの保険）
    protected static function newFactory()
    {
        return \Database\Factories\MeterFactory::new ();
    }

    /** 未指定時のデフォルト（テスト・暫定運用用） */
    protected $attributes = [
        'facility_id' => 0,
    ];
}
