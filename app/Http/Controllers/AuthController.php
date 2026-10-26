<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;


class AuthController extends Controller
{
         
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            // 'turnstile_token' => [Rule::requiredIf($turnstile->enabled()), 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation Failed.', Response::HTTP_UNPROCESSABLE_ENTITY, null, [$validator->errors()]);
        }

        $credentials = $request->only('email', 'password');
        $key = strtolower($credentials['email']) . '|' . $request->ip();
        $userEmailchecker = User::where('email', $credentials['email'])->first();

        //Checks if the email exists.
        if (! $userEmailchecker) {
            return $this->error(
                'Incorrect credentials',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Lock Validator
        // if($userEmailchecker->is_locked == true){
        //     return $this->error(
        //         'Your account has been locked.',
        //         Response::HTTP_UNAUTHORIZED,
        //     );
        // }
        // Validation Checker
        if (!$token = auth()->attempt($credentials)) {
            RateLimiter::hit($key);

            if (RateLimiter::tooManyAttempts($key, 5)) {
                // $userEmailchecker->update(['is_locked' => true]);

                return response()->json([
                    'message' => 'Account locked. Please contact the administrator for assistance.',
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            return $this->error(
                'Incorrect credentials',
                $statusCode = Response::HTTP_UNAUTHORIZED,
            );
        }

        // if (! $userEmailchecker->active) {
        //     return $this->error(
        //         'Account is deactivated',
        //         Response::HTTP_FORBIDDEN
        //     );
        // }

        
        
        $success = $this->respondWithToken($token);
        $user =  $request->user();
        // $user->update([ 'isLoggedIn' => true ]);
        // $user->save();

        RateLimiter::clear($key);
        return $this->success(
            $data = [
                'message' => 'Login Successful.',
                'token' => $success,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    // 'admin' => $user->admin ? [
                    //     'first_name' => $user->admin->first_name,
                    //     'last_name' => $user->admin->last_name,
                    //     'middle_name' => $user->admin->middle_name,
                    //     'ext_name' => $user->admin->ext_name,
                    //     'reg_code' => $user->admin->reg_code,
                    // ] : null,
                ]
            ],
            Response::HTTP_OK,
        );
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function refresh()
    {
        return response()->json([
            'access_token' => auth()->refresh(),
        ]);
    }

    public function logout()
    {
        auth()->logout();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    private function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ];
    }
}
