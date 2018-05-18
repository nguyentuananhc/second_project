<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Validator;
use App\Player;
use App\GameRequest;
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

    public function createGameRequest(Request $request){
        $validator = Validator::make($request->all(), [
            'lvl' => 'required|numeric|min:1|max:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => 1,
                'message' => $validator->errors(),
            ]);
        }
        $token = $request->header('token');
        $level = $request->get('lvl');
        $player = $this->getUserFromToken($token);
        if($player['lvl'.$level] < 3){
            $user = GameRequest::create([
                'player_id' => $player['facebook_id'],
                'score' => 0,
                'lvl' =>  $level,
                'status' => 0,
            ]);
            return response()->json([
                'error' => 0,
                'message' => 'Create Request Success!'
            ]);
        }
        return response()->json([
            'error' => 0,
            'message' => 'Create Request Failed!'
        ]);
    }

    public function checkGameRequest(Request $request){
        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric|min:0|max:3',
            'request_id'=> 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => 1,
                'message' => $validator->errors(),
            ]);
        }
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $requestId = $request->get('request_id');
        $gameRequest = GameRequest::where('id', '=', $requestId)->first();
        if (!$gameRequest) {
            return response()->json([
                'error' => 1,
                'message' => 'Request not found!',
            ]);
        }
        $gameRequest->score = $request->get('score');
        $gameRequest->save();
        $playTime = strtotime($gameRequest['updated_at']) - strtotime($gameRequest['created_at']);
        dd($playTime);
        if($playTime > 60){
            //update score to player
        }else{
            //mess fail
        }
    }
}