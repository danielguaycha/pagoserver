<?php

namespace App;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function person() {
        return $this->belongsTo('App\Person');
    }

    public function isAdmin() {
        return $this->hasAnyRole([Role::ADMIN, Role::ROOT]);
    }

    public function isRoot() {
        return $this->hasRole(Role::ROOT);
    }

    public function findForPassport($username)
    {
        return $this->where('username', $username)->first();
    }
}
