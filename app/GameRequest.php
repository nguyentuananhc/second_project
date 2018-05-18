<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class GameRequest extends Model
{
    protected $table = 'game_requests';
    protected $fillable = ['player_id', 'score', 'lvl', 'status'];
}
