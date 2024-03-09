<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\SendResponse;
use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;

/**
 * AuthController
 * @author TestMate <dev@testmate.org>
 */
class AuthController extends Controller
{
    /**
     * @Route(path="api/v1/login", methods={"POST"})
     *
     * Get api token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = request(['email', 'password']);
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('personal-token')->plainTextToken;
            return SendResponse::acceptCustom([
                'status' => 'success',
                'token' => $token,
            ]);
        }
        return SendResponse::acceptData('invalid-credentials');
    }
}
