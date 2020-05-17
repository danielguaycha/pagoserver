<?php

namespace App\Http\Controllers;

use App\Person;
use App\Role;
use App\Traits\TokenTrait;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;

class AuthController extends ApiController
{
    use TokenTrait;

    private $client;

    public function __construct(){
        $this->client = Client::where("password_client", true)->first();
        $this->middleware("auth:api")->only(['user', 'logout', 'refresh', 'changePw']);
    }

    public function refresh(Request $request) {
        $this->validate($request, [
            'refresh_token' => 'required'
        ]);
        return $this->issueToken($request, 'refresh_token');
    }

    public function logout() {
        $accessToken = Auth::user()->token();
        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->id)
            ->update(['revoked' => true]);
        $accessToken->revoke();
        return response()->json(['ok'=> true], 204);
    }

    public function user(Request $request) {

        $userId = $request->user()->id;
        $root = $request->user()->hasRole(Role::ROOT);
        $admin = $request->user()->hasRole(Role::ADMIN);

        $data = User::findOrFail($userId);
        $data->admin = $admin;
        $data->root = $root;
        $data->person = Person::find($request->user()->person_id);

        /*if($request->user()->isAdmin()) {
            $data->zones = Zone::select('id', 'name')->where('status', Ruta::STATUS_ACTIVE)->get();
        } else {
            $data->zones = $request->user()->rutas()
                ->where('status', Ruta::STATUS_ACTIVE)
                ->select("id", "name")->get();
        }*/


        return $this->custom([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function changePw(Request $request) {
        $request->validate([
            'password' => 'required|string|min:4',
            'password_now' => 'required|string'
        ], [
            'password_now.required' => 'Ingrese su contraseña actual'
        ]);

        $u = User::findOrFail($request->user()->id);

        if (!Hash::check($request->get('password_now'), $u->password)) {
            return $this->err('La contraseña actual es incorrecta');
        }

        $u->password = Hash::make($request->get('password'));

        if($u->save()) {
            return $this->success('Contraseña cambiada con éxito');

        } else return $this->err('No se ha podido cambiar su contraseña');
    }
}
