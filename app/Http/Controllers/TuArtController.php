<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Validator;
use App\Player;
use Illuminate\Http\Request;
use App\Services\TuArtAccountService;
use JWTAuth;
use JWTAuthException;
use Hash;
use Config;
use Socialte;

class TuArtController extends Controller
{
    private $player;

    public function __construct(Player $player){
        $this->player = $player;
    }

    public function getUserFromToken($token){
        $player = JWTAuth::toUser($token);
        return $player;
    }

    public function getUserInfo(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        return response()->json(['result' => $player]);
    }

    public function login(Request $request){
        $credentials = $request->only('facebookId');
        $player = TuArtAccountService::createOrGetUser($credentials['facebookId']);
        $token = JWTAuth::fromUser($player);
        return response()->json(compact('token'));
    }

    public function updatePlayerInfor(Request $request){
        $validator = Validator::make($request->all(), [
            'name'=> 'required|string',
            'email' => 'required|string|email|max:255',
            'phone_number' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => 1,
                'message' => $validator->errors(),
            ]);
        }
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $player->update([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'phone_number' => $request->get('phone_number'),
        ]);
        $player->save();
        return response()->json([
            'error' => 0,
            'result' => $player
        ]);
    }
}