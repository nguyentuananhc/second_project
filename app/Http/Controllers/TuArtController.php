<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Validator;
use App\Player;
use App\GameRequest;
use App\ClaimRequest;
use App\Voucher;
use Illuminate\Http\Request;
use App\Services\TuArtAccountService;
use JWTAuth;
use JWTAuthException;
use Carbon\Carbon;
use Socialite;
use DB;

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

    public function loginFb(Request $request){
        $token = $request->get('access_token');
        $requestedUser = Socialite::driver('facebook')->userFromToken($token);
        // dd($requestedUser);
        $userID = $requestedUser->getId();
        $player = TuArtAccountService::createOrGetUser($userID);
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
        if (!$gameRequest || ($player['facebook_id'] != $gameRequest['player_id'])) {
            return response()->json([
                'error' => 1,
                'message' => 'Request not found!',
            ]);
        }
        if ($gameRequest->status == 1) {
            return response()->json([
                'error' => 1,
                'message' => 'Request failed!',
            ]);
        }
        $gameRequest->score = $request->get('score');
        $gameRequest['sent_at'] = Carbon::now()->toDateTimeString();
        $gameRequest['play_time'] = strtotime(Carbon::now()->toDateTimeString()) - strtotime($gameRequest['created_at']);
        $gameRequest->status = 1;
        $gameRequest->save();
        if ($gameRequestp['play_time'] >= 60){
            $player['lvl'.$gameRequest->lvl] = $gameRequest->score;
            $player->save();
            return response()->json([
                'error' => 0,
                'message' => 'Checking request success!',
            ]);
        }else {
            return response()->json([
                'error' => 1,
                'message' => 'Some errors occurred while processing the requested!',
            ]);
        }
    }

    public function resetGame(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $arr = [1,2,3,4,5,6];
        foreach($arr as $num){
            if($player['lvl'.$num] < 3) {
                return response()->json([
                    'error' => 1,
                    'message' => 'Cannot reset game!',
                ]);
            }
        }
        foreach($arr as $num){
            $player['lvl'.$num] = 0;
        }
        $player['num_replay'] += 1;
        $player->save();
        return response()->json([
            'error' => 0,
            'message' => 'Reset game success!',
        ]);
    }

    public function requestClaim(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $validator = Validator::make($request->all(), [
            'voucher_id'=> 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => 1,
                'message' => $validator->errors(),
            ]);
        }
        if($player['is_claim'] = 1) {
            return response()->json([
                'error' => 1,
                'message' => 'Player Cannot Make Claim Request',
            ]);
        }
        $voucher = Voucher::where('id', '=', $request->get('voucher_id'))->first();
        if(!$voucher){
            return response()->json([
                'error' => 1,
                'message' => 'Incorrect Voucher Id!',
            ]);
        }
        if(!$voucher->amount = 0){
            return response()->json([
                'error' => 1,
                'message' => 'Cannot Claim This Voucher!',
            ]);
        }
        DB::table('claim_requests')->insert([
            ['player_id' => $player['facebook_id'], 'voucher_id' => $request->get('voucher_id')],
        ]);
        $player['is_claim'] = 1;
        $voucher->amount -= 1;
        $player->save();
        return response()->json([
            'error' => 0,
            'message' => 'Request claim success!',
        ]);
    }

    public function getListPrize(Request $request){
        $list = Voucher::all();
        return response()->json([
            'error' => 0,
            'result' => $list,
            'message' => 'Request claim success!',
        ]);
    }
}