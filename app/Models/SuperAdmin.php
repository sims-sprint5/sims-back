<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticable;
use Illuminate\Notifications\Notifiable;
Use Laravel\Sanctum\HasApiTokens;

class SuperAdmin extends Authenticable
{
    use HasApiTokens, Notifiable;

    protected $table = 'superadmins';
    protected $connection = 'pgsql';

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];
}
