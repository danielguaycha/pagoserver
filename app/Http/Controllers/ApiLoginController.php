<?php

namespace App\Http\Controllers;

use Laravel\Passport\Client;
use App\Traits\ApiResponse;
use App\Traits\TokenTrait;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController;

class ApiLoginController extends AccessTokenController
{
    use AuthenticatesUsers,TokenTrait, ApiResponse;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $u = User::select('status')->where('username', $request->username)->first();

        if($u && $u->status === 0) {
            return $this->err( 'Acceso denegado', 401);
        }

        //check if user has reached the max number of login attempts
        if ($this->hasTooManyLoginAttempts($request))
        {
            $this->fireLockoutEvent($request);
            return $this->err('Muchos intentos fallidos de conexión', 401);
        }

        $client = Client::where("password_client", true)->first();
        $requestToken = $this->getCustomToken($request, 'password', "", $client);

        if($requestToken->getStatusCode() === 200) {
            $this->clearLoginAttempts($request);
            return $requestToken;
        }
        else {
            $this->incrementLoginAttempts($request);
            return $this->err("Usuario o Contraseña no válidos", 401);
        }
    }

    /**
     * Determine if the user has too many failed login attempts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function hasTooManyLoginAttempts(Request $request)
    {
        $attempts = 3;
        $lockoutMinites = 10;
        //TODO: Dar de baja al usario y marcar estado como inactivo
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            $attempts,
            $lockoutMinites
        );
    }
}
