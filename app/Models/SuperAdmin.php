<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notificable;
Use Laravel\Sanctum\HasApiTokens;

class SuperAdmin extends Authenticable
{
    use HasApiTokens, Notifiable;

    protected $table = 'superadmins';
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];
}
