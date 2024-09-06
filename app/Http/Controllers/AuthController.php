<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseController
{

  public function login(LoginRequest $request)
  {
    if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
        /** @var \App\Models\User $user **/
        $user = Auth::user();
        $token = $user->createToken('order-management')->plainTextToken;
        $user->token = $token;
        $data = (new UserResource($user))->additional(['token' => $token]);
        return $this->sendSuccess($data);
    }   else {
        return $this->sendError('auth failed', 401);
    }
  }

}
