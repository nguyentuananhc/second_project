<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Player;
use Illuminate\Http\Request;
use JWTAuth;
use JWTAuthException;
use Hash;
use Config;

class TuArtController extends Controller
{
    private $player;

    public function __construct(Player $player){
        $this->player = $player;
    }

    public function getUserInfo(Request $request){
        $player = JWTAuth::toUser($request->token);
        return response()->json(['result' => $player]);
    }

    public function login(Request $request){
        $credentials = $request->only('phone_number', 'name');
        $player = Player::where('phone_number', '=', $credentials['phone_number'])->first();
        $token = JWTAuth::fromUser($player);
        return response()->json(compact('token'));
    }
}