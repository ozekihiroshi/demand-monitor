<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /** @var list<string> */
    protected $fillable = ['name','email','password'];

    /** @var list<string> */
    protected $hidden = ['password','remember_token'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Laravel 11 標準
        ];
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class)->withTimestamps()->withPivot('role');
    }
}
