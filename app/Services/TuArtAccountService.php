<?php
namespace App\Services;
use App\Player;

class TuArtAccountService
{
    public static function createOrGetUser($facebookId)
    {
        $player = Player::where('facebook_id', '=', $facebookId)->first();
        if ($player) {
            return $player;
        } else {
            $player = Player::create([
                'facebook_id' => $facebookId,
            ]);
            return $player;
        }
    }
}