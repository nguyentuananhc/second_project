<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class ClaimRequest extends Model
{
    protected $table = 'claim_requests';
    protected $fillable = ['player_id', 'voucher_id'];
}
