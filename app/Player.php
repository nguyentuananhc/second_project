<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Player extends Authenticatable
{
    //
    use Notifiable;
    protected $table = 'players';
    protected $fillable = [
        'name', 'email', 'phone_number', 'score', 'voucher_id', 'facebook_id',
        'lvl1', 'lvl2', 'stars', 'is_share', 'is_tutorial',
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token',
    ];
}
