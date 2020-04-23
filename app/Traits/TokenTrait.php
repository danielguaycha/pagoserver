<?php
namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


trait TokenTrait {


    public function getCustomToken(Request $request, $grantType, $scope = "", $client = null){

        if($client === null ) { return null; }

        $params = [
            'grant_type' => $grantType,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'scope' => $scope
        ];

        $params['username'] = $request->username ?: $request->email;

        $request->request->add($params);
        $proxy = Request::create('/api/oauth/token', 'POST');
        return Route::dispatch($proxy);
    }

    public function getCustom(Request $request, $grantType, $scope = ""){

        if(!$this->client) { return null; }

        $params = [
            'grant_type' => $grantType,
            'client_id' => $this->client->id,
            'client_secret' => $this->client->secret,
            'scope' => $scope
        ];

        $params['username'] = $request->username ?: $request->email;

        $request->request->add($params);
        $proxy = Request::create('/api/oauth/token', 'POST');
        return Route::dispatch($proxy);
    }

}
