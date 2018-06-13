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

    public function getStarFromUser($player){
        $star = $player['lvl1'] + $player['lvl2'] + $player['num_replay']*6 + $player['is_share']*3;
        return $star;
    }


    public function getUserFromToken($token){
        $player = JWTAuth::toUser($token);
        return $player;
    }

    public function getUserInfo(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $star = $this->getStarFromUser($player);
        $player->stars = $star;
        $player->save();
        return response()->json(['result' => $player]);
    }

    public function loginFb(Request $request){
        $token = $request->get('access_token');
        $requestedUser = Socialite::driver('facebook')->userFromToken($token);
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
            'lvl' => 'required|numeric|min:1|max:2',
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
            $gameRequest = GameRequest::create([
                'player_id' => $player['id'],
                'score' => 0,
                'lvl' =>  $level,
                'status' => 0,
            ]);
            $obj = new \stdClass;
            $obj->id = $gameRequest->id;
            $obj->id = $gameRequest->id;
            return response()->json([
                'error' => 0,
                'message' => 'Create Request Success!',
                'result' => $obj,
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
        if (!$gameRequest || ($player['id'] != $gameRequest['player_id'])) {
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
        if ($gameRequest['play_time'] >= 10){
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
        $arr = [1,2];
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
        if($player['is_claim'] == 1) {
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
        if($voucher->amount == 0){
            return response()->json([
                'error' => 1,
                'message' => 'Cannot Claim This Voucher!',
            ]);
        }
        if($voucher->value > $player->stars){
            return response()->json([
                'error' => 1,
                'message' => 'Cannot Claim This Voucher, Dont Enough Star!',
            ]);
        }
        DB::table('claim_requests')->insert([
            ['player_id' => $player['id'], 'voucher_id' => $request->get('voucher_id')],
        ]);
        $player['is_claim'] = 1;
        $player['voucher_id'] = $voucher->id;
        $voucher->amount -= 1;
        $player->save();
        return response()->json([
            'error' => 0,
            'message' => 'Request Claim Success!',
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

    public function shareFacbook(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $player['is_share'] = 1;
        $player->save();
        return response()->json([
            'error' => 0,
            'message' => 'Shared FaceBook claim success!',
        ]);
    }

    public function passTutorial(Request $request){
        $token = $request->header('token');
        $player = $this->getUserFromToken($token);
        $player['is_tutorial'] = 1;
        $player->save();
        return response()->json([
            'error' => 0,
            'message' => 'Player Have Pass Tutorial!',
        ]);
    }
}