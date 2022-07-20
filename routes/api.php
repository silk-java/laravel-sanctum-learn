<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/tokens/create', function (Request $request) {
    $user = \App\Models\User::find(1);
    ////模拟登陆,此时，会将用户的session存储，但是实际通过API认证的时候，此处用不到
    //    \Illuminate\Support\Facades\Auth::login($user);
    $token = $user->createToken($user->name);

    return ['token' => $token->plainTextToken];
})->withoutMiddleware('auth:sanctum');


Route::post('/tokens/create2', function (Request $request) {
    //这里可以写自己的一些验证逻辑
    //用户来获取token，必须携带用户名和密码
    $password = $request->get("password");
    $username = $request->get("username");
    $user = \App\Models\User::where('password', $password)->where('username', $username)->first();
    if (!$user) {
        return [
            'code' => 500,
            'msg' => '用户名密码错误'
        ];
    }
    $token = $user->createToken($user->name);
    return ['token' => $token->plainTextToken];
})->withoutMiddleware('auth:sanctum');

//用来写使用session，不是前后端分离的用户登陆
Route::post('/login', function (Request $request) {
    //laravel内部的验证方式
    if (\Illuminate\Support\Facades\Auth::attempt([
        'username' => $request->get("name"),
        'password' => $request->get("password")])) {
        //登陆成功
        //保存session
    } else {
        //登陆失败
    }
})->withoutMiddleware('auth:sanctum');
