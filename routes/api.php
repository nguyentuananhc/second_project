<?php

use Illuminate\Http\Request;
use App\Player;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'jwt.auth'], function () {
    Route::get('player-info', 'TuArtController@getUserInfo');
    Route::get('test-token', 'TuArtController@testTokenHeader');
    Route::post('update-player-info', 'TuArtController@updatePlayerInfor');
    Route::post('create-game-request', 'TuArtController@createGameRequest');
    Route::post('check-game-request', 'TuArtController@checkGameRequest');
    Route::post('reset-game', 'TuArtController@resetGame');
    Route::post('create-claim-request', 'TuArtController@requestClaim');
    Route::post('share-fb', 'TuArtController@shareFacbook');
});
Route::post('auth/loginfb', 'TuArtController@loginFb');
Route::get('get-list-voucher', 'TuArtController@getListPrize');
