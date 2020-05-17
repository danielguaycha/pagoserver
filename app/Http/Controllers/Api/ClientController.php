<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Person;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController extends ApiController
{
    use UploadTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|max:100',
            'phones_a' => 'nullable|string|max:13',
            'phones_b' => 'nullable|string|max:13',
            'address_a' => 'nullable|string|max:100',
            'address_b' => 'nullable|string|max:100',
            'fb'=> 'nullable'
        ], $this->messages());

        if (!$request->has('phone_a') && !$request->has('phone_b')) {
            return $this->err('Ingrese al menos un teléfono');
        }

        if (!$request->has('address_a') && !$request->has('address_b')) {
            return $this->err('Ingrese al menos una dirección');
        }

        $p = new Person();
        $p->name = Str::upper($request->name);
        $p->fb = $request->fb;
        $p->phone_a = $request->phone_a;
        $p->phone_b = $request->phone_b;
        // dirección personal
        $p->address_a = $request->address_a;
        $p->lat_a = $request->lat_a;
        $p->lng_a = $request->lng_a;
        // dirección de trabajo
        $p->address_b = $request->address_b;
        $p->lat_b = $request->lat_b;
        $p->lng_b = $request->lng_b;

        if($request->hasFile('ref_a')){
            $p->ref_a = $this->uploadOne($request->file('ref_a'), '/client', 'public');
        }

        if($request->hasFile('ref_b')){
            $p->ref_b = $this->uploadOne($request->file('ref_b'), '/client', 'public');
        }

        $p->user_id = $request->user()->id;

        $p->save();

        return $this->showOne($p);
    }

    public function messages() {
        return [
            'name.required' => 'El nombre del cliente es requerido',
        ];
    }

    public function list(Request $request) {
        $limit = 20;
        // limite
        if ($request->query('limit')) { $limit = $request->query('limit');}

        $p = Person::where([
            ['user_id', $request->user()->id],
        ])
            ->select($this->selectFields())
            ->limit($limit)
            ->orderBy('created_at', 'desc')->get();

        return $this->showAll($p);
    }

    public function selectFields() {
        return ['id', 'name', 'address_a', 'address_b', 'status'];
    }

    public function search(Request $request) {
        if ($request->has('data')) {
            $data = Str::upper($request->query('data'));

            $p = Person::where('name', 'like', "$data%")
                ->orWhere('phone_a', 'like', "$data%")
                ->orWhere('phone_b', 'like', "$data%")
                ->select($this->selectFields())
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get();

            return $this->showAll($p);
        }
        return $this->showAll(null);
    }
}
