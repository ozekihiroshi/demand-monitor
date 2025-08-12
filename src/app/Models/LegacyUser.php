<?php

// app/Models/LegacyUser.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyUser extends Model
{
    protected $connection = 'legacy';
    protected $table = 'user';         // 旧テーブル名そのまま
    protected $primaryKey = 'uid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = [
        'p_count' => 'integer',
        't_rate'  => 'float',
        'threshold' => 'integer',
        'shikiichi' => 'integer',
    ];
}

